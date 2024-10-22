<?php
declare(strict_types=1);

namespace SWF;

use SWF\Enum\DatabaserResultModeEnum;
use SWF\Enum\DatabaserResultTypeEnum;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;
use function is_array;
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

    /**
     * @inheritDoc
     */
    public function fetchAll(?string $class = null): array
    {
        if (null === $class) {
            $fetchMode = DatabaserRegistry::$fetchMode ?? DatabaserResultModeEnum::ASSOC;
        } elseif (null === DatabaserRegistry::$denormalizer) {
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
                    $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
                    if (DatabaserRegistry::$camelize) {
                        $row = $this->camelizeRow($row);
                    }
                    $row = null === $class ? (object) $row : (DatabaserRegistry::$denormalizer)($row, $class);
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
     * @inheritDoc
     */
    public function iterateRow(): iterable
    {
        while (false !== ($row = $this->fetchNextRow())) {
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
        if (false === $row) {
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
        while (false !== ($row = $this->fetchNextRow())) {
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
        if (false === $row) {
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
        if (null !== $class) {
            if (null === DatabaserRegistry::$denormalizer) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        while (false !== ($row = $this->fetchNextRow())) {
            $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
            if (DatabaserRegistry::$camelize) {
                $row = $this->camelizeRow($row);
            }

            yield null === $class ? (object) $row : (DatabaserRegistry::$denormalizer)($row, $class);
        }
    }

    /**
     * @inheritDoc
     */
    public function fetchObject(?string $class = null)
    {
        if (null !== $class) {
            if (null === DatabaserRegistry::$denormalizer) {
                throw new DatabaserException('For use denormalization you must set denormalizer before');
            }
        }

        $row = $this->fetchNextRow();
        if (false === $row) {
            return false;
        }

        $row = array_combine($this->colNames, $this->typifyRow($row, null !== $class));
        if (DatabaserRegistry::$camelize) {
            $row = $this->camelizeRow($row);
        }

        return null === $class ? (object) $row : (DatabaserRegistry::$denormalizer)($row, $class);
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
     * @return mixed[][]
     */
    protected function fetchAllRows(): array
    {
        return [];
    }

    /**
     * @return mixed[]|false
     */
    protected function fetchNextRow(): array|false
    {
        return false;
    }

    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return false;
    }

    /**
     * @return mixed[]
     */
    protected function fetchAllRowsColumns(int $i): array
    {
        return [];
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
