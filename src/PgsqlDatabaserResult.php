<?php
declare(strict_types=1);

namespace SWF;

use PgSql\Result as PgSqlResult;
use SWF\Enum\DatabaserResultTypeEnum;

class PgsqlDatabaserResult extends AbstractDatabaserResult
{
    public function __construct(
        private readonly PgSqlResult $result,
    ) {
        $numFields = pg_num_fields($this->result);

        for ($i = 0; $i < $numFields; $i++) {
            $this->colNames[$i] = pg_field_name($this->result, $i);

            switch (pg_field_type($this->result, $i)) {
                case 'int2':
                case 'int4':
                case 'int8':
                    $this->colTypes[$i] = DatabaserResultTypeEnum::INT;
                    break;
                case 'float4':
                case 'float8':
                    $this->colTypes[$i] = DatabaserResultTypeEnum::FLOAT;
                    break;
                case 'bool':
                    $this->colTypes[$i] = DatabaserResultTypeEnum::BOOL;
                    break;
                case 'json':
                    $this->colTypes[$i] = DatabaserResultTypeEnum::JSON;
                    break;
            }
        }
    }

    protected function fetchAllRows(): array
    {
        return pg_fetch_all($this->result, PGSQL_NUM);
    }

    protected function fetchNextRow(): array|false
    {
        return pg_fetch_row($this->result);
    }

    protected function fetchNextRowColumn(int $i): false|null|string
    {
        return pg_fetch_result($this->result, $i);
    }

    protected function fetchAllRowsColumns(int $i): array
    {
        return pg_fetch_all_columns($this->result, $i);
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
    {
        pg_result_seek($this->result, $i);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function affectedRows(): int
    {
        return pg_affected_rows($this->result);
    }

    /**
     * @inheritDoc
     */
    public function numRows(): int
    {
        return pg_num_rows($this->result);
    }
}
