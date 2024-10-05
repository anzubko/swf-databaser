<?php declare(strict_types=1);

namespace SWF\Interface;

use SWF\Enum\DatabaserResultModeEnum;

interface DatabaserResultInterface
{
    /**
     * Fetches all result rows as associative array, numeric array, or object.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class
     *
     * @return array<T|mixed>
     */
    public function fetchAll(?string $class = null): array;

    /**
     * Iterates next result row as numeric array.
     *
     * @return iterable<mixed>
     */
    public function iterateRow(): iterable;

    /**
     * Fetches next result row as numeric array.
     *
     * @return mixed[]|false
     */
    public function fetchRow(): array|false;

    /**
     * Iterates next result row as associative array.
     *
     * @return iterable<mixed>
     */
    public function iterateAssoc(): iterable;

    /**
     * Fetches next result row as associative array.
     *
     * @return mixed[]|false
     */
    public function fetchAssoc(): array|false;

    /**
     * Iterates next result row as object.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class
     *
     * @return iterable<T>
     */
    public function iterateObject(?string $class = null): iterable;

    /**
     * Fetches next result row as object.
     *
     * @template T of object
     *
     * @param class-string<T>|null $class
     *
     * @return T|false
     */
    public function fetchObject(?string $class = null);

    /**
     * Iterates next result row column.
     *
     * @return iterable<mixed>
     */
    public function iterateColumn(int $i = 0): iterable;

    /**
     * Fetches next result row column.
     */
    public function fetchColumn(int $i = 0): mixed;

    /**
     * Fetches all result rows columns.
     *
     * @return mixed[]
     */
    public function fetchAllColumns(int $i = 0): array;

    /**
     * Moves internal result pointer.
     */
    public function seek(int $i = 0): self;

    /**
     * Gets number of affected rows.
     */
    public function affectedRows(): int;

    /**
     * Gets the number of result rows.
     */
    public function numRows(): int;

    /**
     * Sets mode for fetchAll() method.
     */
    public function setMode(?DatabaserResultModeEnum $mode): self;

    /**
     * Convert result to camel case.
     */
    public function setCamelize(bool $camelize): self;
}
