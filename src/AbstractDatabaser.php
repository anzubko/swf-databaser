<?php declare(strict_types=1);

namespace SWF;

use Closure;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserInterface;
use SWF\Interface\DatabaserResultInterface;
use function count;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_object;
use function is_scalar;

abstract class AbstractDatabaser implements DatabaserInterface
{
    /**
     * Special mark for regular query.
     */
    protected const REGULAR = 0;

    /**
     * Special mark for begin query.
     */
    protected const BEGIN = 1;

    /**
     * Special mark for commit query.
     */
    protected const COMMIT = 2;

    /**
     * Special mark for rollback query.
     */
    protected const ROLLBACK = 3;

    /**
     * Queries queue.
     *
     * @var array{int,string}[]
     */
    protected array $queries = [];

    /**
     * In transaction flag.
     */
    protected bool $inTrans = false;

    /**
     * Mode for fetchAll() method.
     */
    protected ?int $mode = null;

    /**
     * Convert result to camel case.
     */
    protected bool $camelize = false;

    /**
     * External profiler for queries.
     */
    protected Closure $profiler;

    /**
     * Timer of executed queries.
     */
    protected static float $timer = 0.0;

    /**
     * Count of executed queries.
     */
    protected static int $counter = 0;

    /**
     * Connects to database on demand.
     *
     * @throws DatabaserException
     */
    abstract protected function connect(): void;

    /**
     * Begin command is different at different databases.
     */
    abstract protected function makeBeginCommand(?string $isolation): string;

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function begin(?string $isolation = null): self
    {
        if ($this->inTrans) {
            $this->rollback();
        }

        $this->queries[] = [self::BEGIN, $this->makeBeginCommand($isolation)];

        $this->inTrans = true;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function commit(): self
    {
        if ($this->queries && self::BEGIN === end($this->queries)[0]) {
            array_pop($this->queries);
        } else {
            $this->queries[] = [self::COMMIT, 'COMMIT'];
        }

        $this->execute();

        $this->inTrans = false;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function rollback(): self
    {
        if ($this->queries && self::BEGIN === end($this->queries)[0]) {
            array_pop($this->queries);
        } else {
            $this->queries[] = [self::ROLLBACK, 'ROLLBACK'];
        }

        $this->execute();

        $this->inTrans = false;

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function queue(string $query): self
    {
        $this->queries[] = [self::REGULAR, $query];
        if (count($this->queries) > 64) {
            $this->execute();
        }

        return $this;
    }

    /**
     * Assigns result to local class.
     */
    abstract protected function assignResult(object|false $result): DatabaserResultInterface;

    /**
     * @inheritDoc
     */
    public function lastInsertId(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function query(string $query): DatabaserResultInterface
    {
        $this->queries[] = [self::REGULAR, $query];

        return $this->assignResult($this->execute());
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function flush(): self
    {
        $this->execute();

        return $this;
    }

    /**
     * Executes bundle queries at once.
     *
     * @throws DatabaserException
     */
    abstract protected function executeQueries(string $queries): object|false;

    /**
     * Executes all queued queries and returns result.
     *
     * @throws DatabaserException
     */
    protected function execute(): object|false
    {
        if (empty($this->queries)) {
            return false;
        }

        $queries = array_column($this->queries, 1);

        $this->queries = [];

        $timer = gettimeofday(true);

        try {
            $result = $this->executeQueries(implode('; ', $queries));
        } finally {
            $timer = gettimeofday(true) - $timer;

            self::$timer += $timer;

            self::$counter++;

            if (isset($this->profiler)) {
                ($this->profiler)($timer, $queries);
            }
        }

        return $result;
    }

    /**
     * Escapes special characters in a string.
     *
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

        return '0';
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

        return $boolean ? 'true' : 'false';
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function string(mixed $string, string $null = 'null'): string
    {
        switch (true) {
            case null === $string:
                return $null;
            case is_scalar($string):
                return $this->escapeString((string) $string);
            case is_array($string):
                $strings = [];
                foreach ($string as $value) {
                    $strings[] = $this->string($value, $null);
                }
                return $this->commas($strings, '');
        }

        return '';
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    public function scalar(mixed $scalar, string $null = 'null'): string
    {
        switch (true) {
            case null === $scalar:
                return $null;
            case is_numeric($scalar):
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

        return '';
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
        return $this->inTrans;
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
    public function setMode(?int $mode): self
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
}
