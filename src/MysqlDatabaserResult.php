<?php declare(strict_types=1);

namespace SWF;

use Closure;
use mysqli_result;
use SWF\Enum\DatabaserResultModeEnum;
use SWF\Enum\DatabaserResultTypeEnum;

class MysqlDatabaserResult extends AbstractDatabaserResult
{
    public function __construct(
        private readonly mysqli_result $result,
        private readonly int $affectedRows,
        protected ?Closure $denormalizer,
        protected ?DatabaserResultModeEnum $mode,
        protected bool $camelize,
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
            }
        }
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

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
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
