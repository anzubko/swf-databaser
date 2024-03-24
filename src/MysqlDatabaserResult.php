<?php declare(strict_types=1);

namespace SWF;

use mysqli_result;

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
                    $this->colTypes[$i] = self::INT;
                    break;
                case MYSQLI_TYPE_FLOAT:
                case MYSQLI_TYPE_DOUBLE:
                    $this->colTypes[$i] = self::FLOAT;
                    break;
                case MYSQLI_TYPE_JSON:
                    $this->colTypes[$i] = self::JSON;
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function fetchAllRows(): array
    {
        return $this->result->fetch_all();
    }

    /**
     * @inheritDoc
     */
    protected function fetchNextRow(): array|false
    {
        return $this->result->fetch_row() ?? false;
    }

    /**
     * @inheritDoc
     */
    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return $this->result->fetch_column($i);
    }

    /**
     * @inheritDoc
     */
    protected function fetchAllRowsColumns(int $i): array
    {
        $columns = [];
        while (false !== ($column = $this->result->fetch_column($i))) {
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): self
    {
        $this->result->data_seek($i);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function affectedRows(): int
    {
        return $this->affectedRows;
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return (int) $this->result->num_rows;
    }
}
