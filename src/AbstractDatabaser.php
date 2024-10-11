<?php declare(strict_types=1);

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
    private const SAVEPOINT_PATTERN = 'SWF_POINT_%d';

    protected string $beginCommand;

    protected string $beginWithIsolationCommand;

    protected string $commitCommand;

    protected string $rollbackCommand;

    protected ?string $createSavePointCommand = null;

    protected ?string $releaseSavePointCommand = null;

    protected ?string $rollbackToSavePointCommand = null;

    private float $timer = 0.0;

    private int $counter = 0;

    private DatabaserQueue $queue;

    private DatabaserDepth $depth;

    /**
     * @inheritDoc
     */
    public function begin(?string $isolation = null): static
    {
        $this->getDepth()->inc();

        if (1 === $this->getDepth()->get()) {
            if (null === $isolation) {
                $this->getQueue()->add($this->beginCommand, DatabaserQueueTypeEnum::BEGIN);
            } else {
                $this->getQueue()->add(sprintf($this->beginWithIsolationCommand, $isolation), DatabaserQueueTypeEnum::BEGIN);
            }
        } elseif (null !== $this->createSavePointCommand) {
            $this->getQueue()->add(sprintf($this->createSavePointCommand, sprintf(self::SAVEPOINT_PATTERN, $this->getDepth()->get() - 1)), DatabaserQueueTypeEnum::SAVEPOINT);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function commit(): static
    {
        if (null !== $this->createSavePointCommand) {
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->getQueue()->getLastType()) {
                $this->getQueue()->pop();
                $this->getDepth()->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->getQueue()->getLastType()) {
            $this->getQueue()->pop();
            $this->getDepth()->dec();
            $this->execute();
        } elseif ($this->getDepth()->get() > 1) {
            if (null === $this->releaseSavePointCommand) {
                $this->getDepth()->dec();
                $this->execute();
            } else {
                $this->getQueue()->add(sprintf($this->releaseSavePointCommand, sprintf(self::SAVEPOINT_PATTERN, $this->getDepth()->get() - 1)));
                $this->execute();
                $this->getDepth()->dec();
            }
        } else {
            $this->getQueue()->add($this->commitCommand);
            $this->execute();
            $this->getDepth()->dec();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function rollback(bool $full = false): static
    {
        if ($full) {
            if ($this->getDepth()->get() > 0) {
                $this->getQueue()->add($this->rollbackCommand);
                $this->execute();
                $this->getDepth()->reset();
            }

            $this->getQueue()->clear();

            return $this;
        }

        if (null !== $this->createSavePointCommand) {
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->getQueue()->getLastType()) {
                $this->getQueue()->pop();
                $this->getDepth()->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->getQueue()->getLastType()) {
            $this->getQueue()->pop();
            $this->getDepth()->dec();
            $this->execute();
        } elseif ($this->getDepth()->get() > 1) {
            if (null === $this->rollbackToSavePointCommand) {
                $this->getDepth()->dec();
                $this->execute();
            } else {
                $this->queue(sprintf($this->rollbackToSavePointCommand, sprintf(self::SAVEPOINT_PATTERN, $this->getDepth()->get() - 1)))->flush();
                $this->getDepth()->dec();
            }
        } else {
            $this->queue($this->rollbackCommand)->flush();
            $this->getDepth()->dec();
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
        $this->getQueue()->add($query);

        return $this->assignResult($this->execute());
    }

    /**
     * @inheritDoc
     */
    public function queue(string $query): static
    {
        $this->getQueue()->add($query);
        if ($this->getQueue()->count() > 64) {
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
    public function lastInsertId(): int
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
    protected function execute(): ?object
    {
        if (0 === $this->getQueue()->count()) {
            return null;
        }

        $timer = gettimeofday(true);

        $queries = $this->getQueue()->takeAwayQueries();

        try {
            return $this->executeQueries(implode('; ', $queries));
        } finally {
            $timer = gettimeofday(true) - $timer;

            $this->timer += $timer;
            DatabaserParams::$timer += $timer;

            $this->counter++;
            DatabaserParams::$counter++;

            if (null !== DatabaserParams::$profiler) {
                (DatabaserParams::$profiler)($this, $timer, $queries);
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
        return $this->getDepth()->get() > 0;
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

    private function getDepth(): DatabaserDepth
    {
        return $this->depth ??= new DatabaserDepth();
    }

    private function getQueue(): DatabaserQueue
    {
        return $this->queue ??= new DatabaserQueue();
    }
}
