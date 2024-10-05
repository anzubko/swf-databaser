<?php declare(strict_types=1);

namespace SWF;

use Closure;
use SWF\Enum\DatabaserResultModeEnum;
use SWF\Enum\DatabaserResultTypeEnum;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;
use function is_array;
use function is_object;
use function is_string;

abstract class AbstractDatabaserResult implements DatabaserResultInterface
{
    /**
     * @var string[]
     */
    protected array $colNames = [];

    /**
     * @var DatabaserResultTypeEnum[]
     */
    protected array $colTypes = [];

    protected ?Closure $denormalizer = null;

    protected ?DatabaserResultModeEnum $mode = null;

    protected bool $camelize = false;

    /**
     * @return mixed[][]
     */
    protected function fetchAllRows(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(?string $class = null): array
    {
        if (null === $class) {
            $mode = $this->mode ?? DatabaserResultModeEnum::ASSOC;
        } elseif (null === $this->denormalizer) {
            throw new DatabaserException('For use denormalization you must set denormalizer before');
        } else {
            $mode = DatabaserResultModeEnum::OBJECT;
        }

        $rows = [];
        foreach ($this->fetchAllRows() as $row) {
            switch ($mode) {
                case DatabaserResultModeEnum::ASSOC:
                    $row = array_combine($this->colNames, $this->typifyRow($row));
                    if ($this->camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    break;
                case DatabaserResultModeEnum::OBJECT:
                    $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
                    if ($this->camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    $row = null === $class ? (object) $row : ($this->denormalizer)($row, $class);
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
     * @return mixed[]|false
     */
    protected function fetchNextRow(): array|false
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function iterateRow(): iterable
    {
        while (false !== ($row = $this->fetchNextRow())) {
            $row = $this->typifyRow($row);
            if ($this->camelize) {
                $row = $this->camelizeRow($row);
            }

            yield $row;
        }
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
    public function iterateAssoc(): iterable
    {
        while (false !== ($row = $this->fetchNextRow())) {
            $row = array_combine($this->colNames, $this->typifyRow($row));
            if ($this->camelize) {
                $row = $this->camelizeRow($row);
            }

            yield $row;
        }
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
     */
    public function iterateObject(?string $class = null): iterable
    {
        if (null !== $class) {
            if (null === $this->denormalizer) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        while (false !== ($row = $this->fetchNextRow())) {
            $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
            if ($this->camelize) {
                $row = $this->camelizeRow($row);
            }

            yield null === $class ? (object) $row : ($this->denormalizer)($row, $class);
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchObject(?string $class = null)
    {
        if (null !== $class) {
            if (null === $this->denormalizer) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        $row = $this->fetchNextRow();
        if (false === $row) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
        if ($this->camelize) {
            $row = $this->camelizeRow($row);
        }

        return null === $class ? (object) $row : ($this->denormalizer)($row, $class);
    }

    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function iterateColumn(int $i = 0): iterable
    {
        while (false !== ($column = $this->fetchNextRowColumn($i))) {
            if (isset($column, $this->colTypes[$i])) {
                $column = $this->typify($column, $this->colTypes[$i]);
                if (is_array($column)) {
                    return $this->camelizeRow($column);
                }
            }

            yield $column;
        }
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
    public function seek(int $i = 0): static
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
    public function setMode(?DatabaserResultModeEnum $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setCamelize(bool $camelize): static
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
    protected function typifyRow(array $row, bool $assoc = true): array
    {
        foreach ($this->colTypes as $i => $type) {
            if (null === $row[$i]) {
                continue;
            }

            $row[$i] = match ($type) {
                DatabaserResultTypeEnum::INT => (int) $row[$i],
                DatabaserResultTypeEnum::FLOAT => (float) $row[$i],
                DatabaserResultTypeEnum::BOOL => ('t' === $row[$i]),
                DatabaserResultTypeEnum::JSON => json_decode((string) $row[$i], $assoc),
            };
        }

        return $row;
    }

    protected function typify(mixed $value, DatabaserResultTypeEnum $type): mixed
    {
        return match ($type) {
            DatabaserResultTypeEnum::INT => (int) $value,
            DatabaserResultTypeEnum::FLOAT => (float) $value,
            DatabaserResultTypeEnum::BOOL => ('t' === $value),
            DatabaserResultTypeEnum::JSON => json_decode((string) $value, true),
        };
    }
}
