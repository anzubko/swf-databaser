<?php declare(strict_types=1);

namespace SWF\Interface;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;

interface DatabaserResultInterface
{
    /**
     * Fetches all result rows as associative array, numeric array, or object.
     *
     * @return mixed[]
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException
     */
    public function fetchAll(?string $className = null): array;

    /**
     * Fetches next result row as numeric array.
     *
     * @return mixed[]|false
     */
    public function fetchRow(): array|false;

    /**
     * Fetches next result row as associative array.
     *
     * @return array<int|string,mixed>|false
     */
    public function fetchAssoc(): array|false;

    /**
     * Fetches next result row as object.
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException
     */
    public function fetchObject(?string $className = null): object|false;

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
    public function setMode(?int $mode): self;

    /**
     * Convert result to camel case.
     */
    public function setCamelize(bool $camelize): self;
}
