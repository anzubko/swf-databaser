<?php declare(strict_types=1);

namespace SWF;

use SWF\Interface\DatabaserResultInterface;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use function is_array;
use function is_object;
use function is_string;

abstract class AbstractDatabaserResult implements DatabaserResultInterface
{
    protected const INT = 1;
    protected const FLOAT = 2;
    protected const BOOL = 3;
    protected const JSON = 4;

    /**
     * @var string[]
     */
    protected array $colNames = [];

    /**
     * @var int[]
     */
    protected array $colTypes = [];

    private ?int $mode = null;

    private bool $camelize = false;

    private Serializer $serializer;

    /**
     * Fetches all result rows as numeric array.
     *
     * @return mixed[][]
     */
    protected function fetchAllRows(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException
     */
    public function fetchAll(?string $className = null): array
    {
        if (null !== $className) {
            $mode = Databaser::OBJECT;
            $this->serializer ??= $this->getSerializer();
        } else {
            $mode = $this->mode ?? Databaser::ASSOC;
        }

        $rows = [];
        foreach ($this->fetchAllRows() as $row) {
            switch ($mode) {
                case Databaser::ASSOC:
                    $row = array_combine($this->colNames, $this->typifyRow($row));
                    if ($this->camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    break;
                case Databaser::OBJECT:
                    $row = array_combine($this->colNames, $this->typifyRow($row, null !== $className));
                    if ($this->camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    if (null !== $className) {
                        $row = $this->serializer->denormalize($row, $className);
                    } else {
                        $row = (object) $row;
                    }
                    break;
                default:
                    $row = $this->typifyRow($row);
                    if ($this->camelize) {
                        $row = $this->camelizeRow($row);
                    }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Fetches next result row as numeric array.
     *
     * @return mixed[]|false
     */
    protected function fetchNextRow(): array|false
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchRow(): array|false
    {
        $row = $this->fetchNextRow();
        if (false === $row) {
            return false;
        }

        $row = $this->typifyRow($row);
        if ($this->camelize) {
            $row = $this->camelizeRow($row);
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): array|false
    {
        $row = $this->fetchNextRow();
        if (false === $row) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row));
        if ($this->camelize) {
            $row = $this->camelizeRow($row);
        }

        return $row;
    }

    /**
     * @inheritDoc
     *
     * @throws NotNormalizableValueException
     * @throws PartialDenormalizationException
     */
    public function fetchObject(?string $className = null): object|false
    {
        $row = $this->fetchNextRow();
        if (false === $row) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row, null !== $className));
        if ($this->camelize) {
            $row = $this->camelizeRow($row);
        }

        if (null === $className) {
            return (object) $row;
        }

        $this->serializer ??= $this->getSerializer();

        $row = $this->serializer->denormalize($row, $className);

        return is_object($row) ? $row : false;
    }

    /**
     * Fetches next result row column.
     */
    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn(int $i = 0): mixed
    {
        $column = $this->fetchNextRowColumn($i);
        if (false === $column) {
            return false;
        }

        if (isset($column, $this->colTypes[$i])) {
            $column = $this->typify($column, $this->colTypes[$i]);
            if (is_array($column)) {
                return $this->camelizeRow($column);
            }
        }

        return $column;
    }

    /**
     * Fetches all result rows columns.
     *
     * @return mixed[]
     */
    protected function fetchAllRowsColumns(int $i): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function fetchAllColumns(int $i = 0): array
    {
        $columns = $this->fetchAllRowsColumns($i);
        if (isset($this->colTypes[$i])) {
            foreach ($columns as $j => $column) {
                if (null === $column) {
                    continue;
                }

                $columns[$j] = $this->typify($column, $this->colTypes[$i]);
                if (is_array($columns[$j])) {
                    $columns[$j] = $this->camelizeRow($columns[$j]);
                }
            }
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): self
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function affectedRows(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return 0;
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

    /**
     * @param mixed[]|object $row
     *
     * @return mixed[]
     */
    private function camelizeRow(array|object $row): array
    {
        $result = [];
        foreach ((array) $row as $key => $value) {
            if (is_string($key)) {
                $key = lcfirst(strtr(ucwords($key, '_'), ['_' => '']));
            }

            if (is_array($value)) {
                $result[$key] = $this->camelizeRow($value);
            } elseif (is_object($value)) {
                $result[$key] = (object) $this->camelizeRow($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param mixed[] $row
     *
     * @return mixed[]
     */
    private function typifyRow(array $row, bool $assoc = true): array
    {
        foreach ($this->colTypes as $i => $type) {
            if (null === $row[$i]) {
                continue;
            }

            $row[$i] = match ($type) {
                self::INT => (int) $row[$i],
                self::FLOAT => (float) $row[$i],
                self::BOOL => ('t' === $row[$i]),
                self::JSON => json_decode((string) $row[$i], $assoc),
                default => $row[$i],
            };
        }

        return $row;
    }

    private function typify(mixed $value, int $type): mixed
    {
        return match ($type) {
            self::INT => (int) $value,
            self::FLOAT => (float) $value,
            self::BOOL => ('t' === $value),
            self::JSON => json_decode((string) $value, true),
            default => $value,
        };
    }

    private function getSerializer(): Serializer
    {
        return new Serializer([
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                propertyTypeExtractor: new PropertyInfoExtractor(
                    typeExtractors: [
                        new PhpDocExtractor(),
                    ],
                ),
            ),
        ]);
    }
}
