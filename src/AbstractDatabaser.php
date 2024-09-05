<?php declare(strict_types=1);

namespace SWF;

use Closure;
use DateTimeInterface;
use SWF\Enum\DatabaserQueueTypeEnum;
use SWF\Enum\DatabaserResultModeEnum;
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
    protected string $beginCommand;

    protected string $beginWithIsolationCommand;

    protected string $commitCommand;

    protected string $rollbackCommand;

    protected ?string $createSavePointCommand = null;

    protected ?string $releaseSavePointCommand = null;

    protected ?string $rollbackToSavePointCommand = null;

    protected DatabaserResultModeEnum $mode = DatabaserResultModeEnum::ASSOC;

    protected bool $camelize = true;

    private DatabaserQueue $queue;

    private DatabaserDepth $depth;

    private Closure $profiler;

    private static float $timer = 0.0;

    private static int $counter = 0;

    public function __construct()
    {
        $this->queue = new DatabaserQueue();
        $this->depth = new DatabaserDepth();
    }

    /**
     * @inheritDoc
     */
    public function begin(?string $isolation = null): self
    {
        $this->depth->inc();

        if (1 === $this->depth->get()) {
            if (null === $isolation) {
                $this->queue->add($this->beginCommand, DatabaserQueueTypeEnum::BEGIN);
            } else {
                $this->queue->add(sprintf($this->beginWithIsolationCommand, $isolation), DatabaserQueueTypeEnum::BEGIN);
            }
        } elseif (null !== $this->createSavePointCommand) {
            $this->queue->add(sprintf($this->createSavePointCommand, $this->getSavePointName()), DatabaserQueueTypeEnum::SAVEPOINT);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function commit(): self
    {
        if (null !== $this->createSavePointCommand) {
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->queue->getLastType()) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->queue->getLastType()) {
            $this->queue->pop();
            $this->depth->dec();
            $this->execute();
        } elseif ($this->depth->get() > 1) {
            if (null === $this->releaseSavePointCommand) {
                $this->depth->dec();
                $this->execute();
            } else {
                $this->queue->add(sprintf($this->releaseSavePointCommand, $this->getSavePointName()));
                $this->execute();
                $this->depth->dec();
            }
        } else {
            $this->queue->add($this->commitCommand);
            $this->execute();
            $this->depth->dec();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function rollback(bool $full = false): self
    {
        if ($full) {
            if ($this->depth->get() > 0) {
                $this->queue->add($this->rollbackCommand);
                $this->execute();
                $this->depth->reset();
            }

            $this->queue->clear();

            return $this;
        }

        if (null !== $this->createSavePointCommand) {
            while (DatabaserQueueTypeEnum::SAVEPOINT === $this->queue->getLastType()) {
                $this->queue->pop();
                $this->depth->dec();
            }
        }

        if (DatabaserQueueTypeEnum::BEGIN === $this->queue->getLastType()) {
            $this->queue->pop();
            $this->depth->dec();
            $this->execute();
        } elseif ($this->depth->get() > 1) {
            if (null === $this->rollbackToSavePointCommand) {
                $this->depth->dec();
                $this->execute();
            } else {
                $this->queue(sprintf($this->rollbackToSavePointCommand, $this->getSavePointName()))->flush();
                $this->depth->dec();
            }
        } else {
            $this->queue($this->rollbackCommand)->flush();
            $this->depth->dec();
        }

        return $this;
    }

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
    public function queue(string $query): self
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
    public function flush(): self
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
        if (0 === $this->queue->count()) {
            return null;
        }

        $timer = gettimeofday(true);

        $queries = $this->queue->takeAwayQueries();

        try {
            return $this->executeQueries(implode('; ', $queries));
        } finally {
            self::$timer += $timer = gettimeofday(true) - $timer;

            self::$counter++;

            if (isset($this->profiler)) {
                ($this->profiler)($timer, $queries);
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
    public function getTimer(): float
    {
        return self::$timer;
    }

    /**
     * @inheritDoc
     */
    public function getCounter(): int
    {
        return self::$counter;
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
    public function setProfiler(callable $profiler): self
    {
        $this->profiler = $profiler(...);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setMode(DatabaserResultModeEnum $mode): self
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setCamelize(bool $camelize): self
    {
        $this->camelize = $camelize;

        return $this;
    }

    private function getSavePointName(): string
    {
        return sprintf('SWF_POINT_%d', $this->depth->get() - 1);
    }
}
