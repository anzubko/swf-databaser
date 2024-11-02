<?php
declare(strict_types=1);

namespace SWF;

use DateTimeInterface;
use SWF\Enum\DatabaserQueueTypeEnum;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use SWF\Interface\DatabaserResultInterface;
use function count;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;

/**
 * @internal
 */
abstract class AbstractDatabaser implements DatabaserInterface
{
    private float $timer = 0.0;

    private int $counter = 0;

    private DatabaserQueue $queue;

    private DatabaserDepth $depth;

    public function __construct(
        private readonly string $name,
    ) {
        $this->depth = new DatabaserDepth();

        $this->queue = new DatabaserQueue();
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @throws DatabaserException
     */
    abstract protected function assignResult(?object $result): DatabaserResultInterface;

    /**
     * @throws DatabaserException
     */
    abstract protected function executeQueries(string $queries): ?object;

    /**
     * @throws DatabaserException
     */
    private function execute(): ?object
    {
        if ($this->queue->count() === 0) {
            return null;
        }

        $timer = gettimeofday(true);

        $queries = $this->queue->takeAwayQueries();

        try {
            return $this->executeQueries(implode('; ', $queries));
        } finally {
            $timer = gettimeofday(true) - $timer;

            $this->timer += $timer;
            DatabaserRegistry::$timer += $timer;

            $this->counter++;
            DatabaserRegistry::$counter++;

            if (null !== DatabaserRegistry::$profiler) {
                (DatabaserRegistry::$profiler)($this, $timer, $queries);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function query(string $query): DatabaserResultInterface
    {
        $this->queue->add($query);

        return $this->assignResult($this->execute());
    }

    /**
     * @inheritDoc
     */
    public function queue(string $query): static
    {
        $this->queue->add($query);
        if ($this->queue->count() > 64) {
            $this->execute();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function flush(): static
    {
        $this->execute();

        return $this;
    }

    protected function makeBeginCommand(): string
    {
        return 'START TRANSACTION';
    }

    protected function makeParametrizedBeginCommand(string $isolation): string
    {
        return sprintf('SET TRANSACTION %s; START TRANSACTION', $isolation);
    }

    protected function isSavePointsSupported(): bool
    {
        return true;
    }

    protected function makeSavePointName(int $savePointId): string
    {
        return sprintf('SWF_SAVEPOINT_%d', $savePointId);
    }

    protected function makeCreateSavePointCommand(int $savePointId): string
    {
        return sprintf('SAVEPOINT %s', $this->makeSavePointName($savePointId));
    }

    /**
     * @inheritDoc
     */
    public function begin(?string $isolation = null): static
    {
        if ($this->depth->get() === 0) {
            $this->execute();
            if ($isolation === null) {
                $this->queue->add($this->makeBeginCommand(), DatabaserQueueTypeEnum::BEGIN);
            } else {
                $this->queue->add($this->makeParametrizedBeginCommand($isolation), DatabaserQueueTypeEnum::BEGIN);
            }
        } elseif ($this->isSavePointsSupported()) {
            $this->queue->add($this->makeCreateSavePointCommand($this->depth->get()), DatabaserQueueTypeEnum::SAVEPOINT);
        }

        $this->depth->inc();

        return $this;
    }

    protected function makeCommitCommand(): string
    {
        return 'COMMIT';
    }

    protected function makeReleaseSavePointCommand(int $savePointId): string
    {
        return sprintf('RELEASE SAVEPOINT %s', $this->makeSavePointName($savePointId));
    }

    /**
     * @inheritDoc
     */
    public function commit(): static
    {
        if ($this->isSavePointsSupported()) {
            while ($this->queue->getLastType() === DatabaserQueueTypeEnum::SAVEPOINT) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if ($this->queue->getLastType() === DatabaserQueueTypeEnum::BEGIN) {
            $this->queue->pop();
        } elseif ($this->depth->get() === 1) {
            $this->queue->add($this->makeCommitCommand());
            $this->execute();
        } elseif ($this->depth->get() > 1) {
            if ($this->isSavePointsSupported()) {
                $this->queue->add($this->makeReleaseSavePointCommand($this->depth->get() - 1));
            }

            $this->execute();
        }

        $this->depth->dec();

        return $this;
    }

    protected function makeRollbackCommand(): string
    {
        return 'ROLLBACK';
    }

    protected function makeRollbackToSavePointCommand(int $savePointId): string
    {
        return sprintf('ROLLBACK TO %s', $this->makeSavePointName($savePointId));
    }

    /**
     * @inheritDoc
     */
    public function rollback(): static
    {
        if ($this->isSavePointsSupported()) {
            while ($this->queue->getLastType() === DatabaserQueueTypeEnum::SAVEPOINT) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if ($this->queue->getLastType() === DatabaserQueueTypeEnum::BEGIN) {
            $this->queue->pop();
        } elseif ($this->depth->get() === 1) {
            $this->queue->add($this->makeRollbackCommand());
            $this->execute();
        } elseif ($this->depth->get() > 1) {
            if ($this->isSavePointsSupported()) {
                $this->queue->add($this->makeRollbackToSavePointCommand($this->depth->get() - 1));
            }

            $this->execute();
        }

        $this->depth->dec();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isInTrans(): bool
    {
        return $this->depth->get() > 0;
    }

    /**
     * @throws DatabaserException
     */
    abstract protected function escapeString(string $string): string;

    /**
     * @inheritDoc
     */
    public function number(mixed $number, string $null = 'null'): string
    {
        switch (true) {
            case $number === null:
                return $null;
            case is_scalar($number):
                return (string) (float) $number;
            case is_iterable($number):
                $numbers = [];
                foreach ($number as $value) {
                    $numbers[] = $this->number($value, $null);
                }
                return $this->commas($numbers, '');
        }

        throw new DatabaserException(sprintf('Unable convert to number value: %s', var_export($number, true)));
    }

    /**
     * @inheritDoc
     */
    public function boolean(mixed $boolean, string $null = 'null'): string
    {
        switch (true) {
            case $boolean === null:
                return $null;
            case is_scalar($boolean):
                return $boolean ? 'true' : 'false';
            case is_iterable($boolean):
                $booleans = [];
                foreach ($boolean as $value) {
                    $booleans[] = $this->boolean($value, $null);
                }
                return $this->commas($booleans, '');
        }

        throw new DatabaserException(sprintf('Unable convert to boolean value: %s', var_export($boolean, true)));
    }

    /**
     * @inheritDoc
     */
    public function string(mixed $string, string $null = 'null'): string
    {
        switch (true) {
            case $string === null:
                return $null;
            case $string instanceof DateTimeInterface:
                return $this->escapeString($string->format('Y-m-d H:i:s.u'));
            case is_scalar($string):
                return $this->escapeString((string) $string);
            case is_iterable($string):
                $strings = [];
                foreach ($string as $value) {
                    $strings[] = $this->string($value, $null);
                }
                return $this->commas($strings, '');
        }

        throw new DatabaserException(sprintf('Unable convert to string value: %s', var_export($string, true)));
    }

    /**
     * @inheritDoc
     */
    public function scalar(mixed $scalar, string $null = 'null'): string
    {
        switch (true) {
            case $scalar === null:
                return $null;
            case $scalar instanceof DateTimeInterface:
                return $this->escapeString($scalar->format('Y-m-d H:i:s.u'));
            case is_int($scalar):
            case is_float($scalar):
                return (string) $scalar;
            case is_bool($scalar):
                return $scalar ? 'true' : 'false';
            case is_scalar($scalar):
                return $this->escapeString((string) $scalar);
            case is_iterable($scalar):
                $scalars = [];
                foreach ($scalar as $value) {
                    $scalars[] = $this->scalar($value, $null);
                }
                return $this->commas($scalars, '');
        }

        throw new DatabaserException(sprintf('Unable convert to scalar value: %s', var_export($scalar, true)));
    }

    /**
     * @inheritDoc
     */
    public function every(array $expressions, string $default = 'true'): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        return implode(' AND ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function any(array $expressions, string $default = 'true'): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        return implode(' OR ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function commas(array $expressions, string $default = 'true'): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        return implode(', ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function parentheses(array $expressions, string $default = ''): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        foreach ($expressions as $i => $expression) {
            $expressions[$i] = sprintf('(%s)', $expression);
        }

        return implode(', ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function pluses(array $expressions, string $default = ''): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        return implode(' + ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function spaces(array $expressions, string $default = ''): string
    {
        if (count($expressions) === 0) {
            return $default;
        }

        return implode(' ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function getTimer(): float
    {
        return $this->timer;
    }

    /**
     * @inheritDoc
     */
    public function getCounter(): int
    {
        return $this->counter;
    }
}
