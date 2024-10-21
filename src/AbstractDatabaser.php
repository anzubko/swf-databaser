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

    protected function makeBeginCommand(?string $isolation = null): string
    {
        if (null === $isolation) {
            return 'START TRANSACTION';
        }

        return sprintf('SET TRANSACTION %s; START TRANSACTION', $isolation);
    }

    protected function isSavePointsSupported(): bool
    {
        return true;
    }

    protected function makeSavePointName(int $savePointId): string
    {
        return sprintf('SWF_POINT_%d', $savePointId);
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
        $this->depth->inc();

        if (1 === $this->depth->get()) {
            $this->queue->add($this->makeBeginCommand($isolation), DatabaserQueueTypeEnum::BEGIN);
        } elseif ($this->isSavePointsSupported()) {
            $this->queue->add($this->makeCreateSavePointCommand($this->depth->get() - 1), DatabaserQueueTypeEnum::SAVEPOINT);
        }

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
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->queue->getLastType()) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->queue->getLastType()) {
            $this->queue->pop();
            $this->depth->dec();
            $this->execute();
        } elseif ($this->depth->get() > 1 && $this->isSavePointsSupported()) {
            $this->queue->add($this->makeReleaseSavePointCommand($this->depth->get() - 1));
            $this->execute();
            $this->depth->dec();
        } elseif ($this->depth->get() > 1) {
            $this->depth->dec();
            $this->execute();
        } else {
            $this->queue->add($this->makeCommitCommand());
            $this->execute();
            $this->depth->dec();
        }

        return $this;
    }

    protected function makeRollbackCommand(?int $savePointId = null): string
    {
        if (null === $savePointId) {
            return 'ROLLBACK';
        }

        return sprintf('ROLLBACK TO %s', $this->makeSavePointName($savePointId));
    }

    /**
     * @inheritDoc
     */
    public function rollback(bool $full = false): static
    {
        if ($full) {
            if ($this->depth->get() > 0) {
                $this->queue->add($this->makeRollbackCommand());
                $this->execute();
                $this->depth->reset();
            }

            $this->queue->clear();

            return $this;
        }

        if ($this->isSavePointsSupported()) {
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->queue->getLastType()) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->queue->getLastType()) {
            $this->queue->pop();
            $this->depth->dec();
            $this->execute();
        } elseif ($this->depth->get() > 1 && $this->isSavePointsSupported()) {
            $this->queue->add($this->makeRollbackCommand($this->depth->get() - 1));
            $this->execute();
            $this->depth->dec();
        } elseif ($this->depth->get() > 1) {
            $this->depth->dec();
            $this->execute();
        } else {
            $this->queue->add($this->makeRollbackCommand());
            $this->execute();
            $this->depth->dec();
        }

        return $this;
    }

    /**
     * @throws DatabaserException
     */
    abstract protected function assignResult(?object $result): DatabaserResultInterface;

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

    /**
     * @inheritDoc
     */
    public function getLastInsertId(): int
    {
        return 0;
    }

    /**
     * @throws DatabaserException
     */
    abstract protected function executeQueries(string $queries): ?object;

    /**
     * @throws DatabaserException
     */
    private function execute(): ?object
    {
        if (0 === $this->queue->count()) {
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
     * @throws DatabaserException
     */
    abstract protected function escapeString(string $string): string;

    /**
     * @inheritDoc
     */
    public function number(mixed $number, string $null = 'null'): string
    {
        switch (true) {
            case null === $number:
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
            case null === $boolean:
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
            case null === $string:
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
            case null === $scalar:
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
        return count($expressions) === 0 ? $default : implode(' AND ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function any(array $expressions, string $default = 'true'): string
    {
        return count($expressions) === 0 ? $default : implode(' OR ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function commas(array $expressions, string $default = 'true'): string
    {
        return count($expressions) === 0 ? $default : implode(', ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function parentheses(array $expressions, string $default = ''): string
    {
        return count($expressions) === 0 ? $default : implode(', ', array_map(fn($e) => sprintf('(%s)', $e), $expressions));
    }

    /**
     * @inheritDoc
     */
    public function pluses(array $expressions, string $default = ''): string
    {
        return count($expressions) === 0 ? $default : implode(' + ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function spaces(array $expressions, string $default = ''): string
    {
        return count($expressions) === 0 ? $default : implode(' ', $expressions);
    }

    /**
     * @inheritDoc
     */
    public function isInTrans(): bool
    {
        return $this->depth->get() > 0;
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
