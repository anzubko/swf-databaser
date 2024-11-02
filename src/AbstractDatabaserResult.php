<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\DatabaserResultModeEnum;
use SWF\Enum\DatabaserResultTypeEnum;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;
use function is_array;
use function is_string;

/**
 * @internal
 */
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
        if ($class === null) {
            $fetchMode = DatabaserRegistry::$fetchMode ?? DatabaserResultModeEnum::ASSOC;
        } elseif (DatabaserRegistry::$denormalizer === null) {
            throw new DatabaserException('For use denormalization you must set denormalizer before');
        } else {
            $fetchMode = DatabaserResultModeEnum::OBJECT;
        }

        $rows = [];
        foreach ($this->fetchAllRows() as $row) {
            switch ($fetchMode) {
                case DatabaserResultModeEnum::ASSOC:
                    $row = array_combine($this->colNames, $this->typifyRow($row));
                    if (DatabaserRegistry::$camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    break;
                case DatabaserResultModeEnum::OBJECT:
                    $row = array_combine($this->colNames, $this->typifyRow($row, $class !== null));
                    if (DatabaserRegistry::$camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    if ($class === null) {
                        $row = (object) $row;
                    } else {
                        $row = (DatabaserRegistry::$denormalizer)($row, $class);
                    }
                    break;
                default:
                    $row = $this->typifyRow($row);
                    if (DatabaserRegistry::$camelize) {
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
        while (($row = $this->fetchNextRow()) !== false) {
            $row = $this->typifyRow($row);
            if (DatabaserRegistry::$camelize) {
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
        if ($row === false) {
            return false;
        }

        $row = $this->typifyRow($row);
        if (DatabaserRegistry::$camelize) {
            $row = $this->camelizeRow($row);
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function iterateAssoc(): iterable
    {
        while (($row = $this->fetchNextRow()) !== false) {
            $row = array_combine($this->colNames, $this->typifyRow($row));
            if (DatabaserRegistry::$camelize) {
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
        if ($row === false) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row));
        if (DatabaserRegistry::$camelize) {
            $row = $this->camelizeRow($row);
        }

        return $row;
    }

    /**
     * @inheritDoc
     */
    public function iterateObject(?string $class = null): iterable
    {
        if ($class !== null) {
            if (DatabaserRegistry::$denormalizer === null) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        while (($row = $this->fetchNextRow()) !== false) {
            $row = array_combine($this->colNames, $this->typifyRow($row, $class !== null));
            if (DatabaserRegistry::$camelize) {
                $row = $this->camelizeRow($row);
            }

            yield $class === null ? (object) $row : (DatabaserRegistry::$denormalizer)($row, $class);
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchObject(?string $class = null)
    {
        if ($class !== null) {
            if (DatabaserRegistry::$denormalizer === null) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        $row = $this->fetchNextRow();
        if ($row === false) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row, $class !== null));
        if (DatabaserRegistry::$camelize) {
            $row = $this->camelizeRow($row);
        }

        return $class === null ? (object) $row : (DatabaserRegistry::$denormalizer)($row, $class);
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
        while (($column = $this->fetchNextRowColumn($i)) !== false) {
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
        if ($column === false) {
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
                if ($column === null) {
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
    public function getAffectedRowsCount(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function getRowsCount(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
    {
        return $this;
    }

    /**
     * @param mixed[] $row
     *
     * @return mixed[]
     */
    protected function typifyRow(array $row, bool $assoc = true): array
    {
        return $row;
    }

    protected function typify(mixed $value, DatabaserResultTypeEnum $type): mixed
    {
        return $value;
    }

    /**
     * @param mixed[] $row
     *
     * @return mixed[]
     */
    private function camelizeRow(array $row): array
    {
        $result = [];
        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $key = lcfirst(strtr(ucwords($key, '_'), ['_' => '']));
            }

            $result[$key] = is_array($value) ? $this->camelizeRow($value) : $value;
        }

        return $result;
    }
}
