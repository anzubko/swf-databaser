<?php
declare(strict_types=1);

namespace SWF;

use mysqli_result;
use SWF\Enum\DatabaserResultTypeEnum;

class MysqlDatabaserResult extends AbstractDatabaserResult
{
    public function __construct(
        private readonly mysqli_result $result,
        private readonly int $affectedRows,
    ) {
        if (0 === $this->result->field_count) {
            return;
        }

        foreach ($result->fetch_fields() as $i => $field) {
            $this->colNames[$i] = $field->name;

            switch ($field->type) {
                case MYSQLI_TYPE_BIT:
                case MYSQLI_TYPE_TINY:
                case MYSQLI_TYPE_SHORT:
                case MYSQLI_TYPE_LONG:
                case MYSQLI_TYPE_LONGLONG:
                case MYSQLI_TYPE_INT24:
                case MYSQLI_TYPE_YEAR:
                case MYSQLI_TYPE_ENUM:
                    $this->colTypes[$i] = DatabaserResultTypeEnum::INT;
                    break;
                case MYSQLI_TYPE_FLOAT:
                case MYSQLI_TYPE_DOUBLE:
                    $this->colTypes[$i] = DatabaserResultTypeEnum::FLOAT;
                    break;
                case MYSQLI_TYPE_JSON:
                    $this->colTypes[$i] = DatabaserResultTypeEnum::JSON;
                    break;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getAffectedRowsCount(): int
    {
        return $this->affectedRows;
    }

    /**
     * @inheritDoc
     */
    public function getRowsCount(): int
    {
        return (int) $this->result->num_rows;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
    {
        $this->result->data_seek($i);

        return $this;
    }

    protected function fetchAllRows(): array
    {
        return $this->result->fetch_all();
    }

    protected function fetchNextRow(): array|false
    {
        return $this->result->fetch_row() ?? false;
    }

    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return $this->result->fetch_column($i);
    }

    protected function fetchAllRowsColumns(int $i): array
    {
        $columns = [];
        while (false !== ($column = $this->result->fetch_column($i))) {
            $columns[] = $column;
        }

        return $columns;
    }

    protected function typifyRow(array $row, bool $assoc = true): array
    {
        foreach ($this->colTypes as $i => $type) {
            if (null !== $row[$i]) {
                continue;
            }

            $row[$i] = match ($type) {
                DatabaserResultTypeEnum::INT => (int) $row[$i],
                DatabaserResultTypeEnum::FLOAT => (float) $row[$i],
                DatabaserResultTypeEnum::JSON => json_decode((string) $row[$i], $assoc),
                default => null,
            };
        }

        return $row;
    }

    protected function typify(mixed $value, DatabaserResultTypeEnum $type): mixed
    {
        return match ($type) {
            DatabaserResultTypeEnum::INT => (int) $value,
            DatabaserResultTypeEnum::FLOAT => (float) $value,
            DatabaserResultTypeEnum::JSON => json_decode((string) $value, true),
            default => null,
        };
    }
}
