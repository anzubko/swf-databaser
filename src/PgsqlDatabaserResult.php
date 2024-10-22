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

    /**
     * @inheritDoc
     */
    public function getAffectedRowsCount(): int
    {
        return pg_affected_rows($this->result);
    }

    /**
     * @inheritDoc
     */
    public function getRowsCount(): int
    {
        return pg_num_rows($this->result);
    }

    /**
     * @inheritDoc
     */
    public function seek(int $i = 0): static
    {
        pg_result_seek($this->result, $i);

        return $this;
    }

    protected function fetchAllRows(): array
    {
        return pg_fetch_all($this->result, PGSQL_NUM);
    }

    protected function fetchNextRow(): array|false
    {
        return pg_fetch_row($this->result);
    }

    protected function fetchNextRowColumn(int $i): false|float|int|null|string
    {
        return pg_fetch_result($this->result, $i);
    }

    protected function fetchAllRowsColumns(int $i): array
    {
        return pg_fetch_all_columns($this->result, $i);
    }

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
