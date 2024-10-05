<?php declare(strict_types=1);

namespace SWF\Interface;

use Closure;
use SWF\Enum\DatabaserResultModeEnum;
use SWF\Exception\DatabaserException;

interface DatabaserInterface
{
    /**
     * Begins transaction.
     */
    public function begin(?string $isolation = null): static;

    /**
     * Commits transaction. If nothing was after begin, then ignores begin.
     *
     * @throws DatabaserException
     */
    public function commit(): static;

    /**
     * Rollbacks transaction.
     *
     * @throws DatabaserException
     */
    public function rollback(bool $full = false): static;

    /**
     * Executes query and returns result.
     *
     * @throws DatabaserException
     */
    public function query(string $query): DatabaserResultInterface;

    /**
     * Queues query.
     *
     * @throws DatabaserException
     */
    public function queue(string $query): static;

    /**
     * Executes all queued queries.
     *
     * @throws DatabaserException
     */
    public function flush(): static;

    /**
     * Returns the ID of the last inserted row or sequence value.
     */
    public function lastInsertId(): int;

    /**
     * Formats numbers for queries.
     *
     * @throws DatabaserException
     */
    public function number(mixed $number, string $null = 'null'): string;

    /**
     * Formats booleans for queries.
     *
     * @throws DatabaserException
     */
    public function boolean(mixed $boolean, string $null = 'null'): string;

    /**
     * Formats and escapes strings for queries.
     *
     * @throws DatabaserException
     */
    public function string(mixed $string, string $null = 'null'): string;

    /**
     * Formats and escapes strings, booleans and numerics for queries depending on types.
     *
     * @throws DatabaserException
     */
    public function scalar(mixed $scalar, string $null = 'null'): string;

    /**
     * Joins expressions for WHERE.
     *
     * @param string[] $expressions
     */
    public function every(array $expressions, string $default = 'true'): string;

    /**
     * Joins expressions for WHERE.
     *
     * @param string[] $expressions
     */
    public function any(array $expressions, string $default = 'true'): string;

    /**
     * Joins expressions for SELECT or ORDER.
     *
     * @param string[] $expressions
     */
    public function commas(array $expressions, string $default = 'true'): string;

    /**
     * Joins expressions with parentheses for multi INSERT.
     *
     * @param string[] $expressions
     */
    public function parentheses(array $expressions, string $default = ''): string;

    /**
     * Joins expressions with pluses.
     *
     * @param string[] $expressions
     */
    public function pluses(array $expressions, string $default = ''): string;

    /**
     * Joins expressions with spaces.
     *
     * @param string[] $expressions
     */
    public function spaces(array $expressions, string $default = ''): string;

    /**
     * Gets timer of executed queries.
     */
    public function getTimer(): float;

    /**
     * Gets count of executed queries.
     */
    public function getCounter(): int;

    /**
     * Gets transaction status.
     */
    public function isInTrans(): bool;

    /**
     * Sets external profiler for queries.
     */
    public function setProfiler(Closure $profiler): static;

    /**
     * Sets external denormalizer for array to object conversions.
     */
    public function setDenormalizer(Closure $denormalizer): static;

    /**
     * Sets mode for fetchAll() method.
     */
    public function setMode(DatabaserResultModeEnum $mode): static;

    /**
     * Convert result to camel case.
     */
    public function setCamelize(bool $camelize): static;
}
