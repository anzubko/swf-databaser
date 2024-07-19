<?php declare(strict_types=1);

namespace SWF;

use PgSql\Result as PgSqlResult;

class PgsqlDatabaserResult extends AbstractDatabaserResult
{
    public function __construct(
        private readonly PgSqlResult $result,
        ?int $mode,
        bool $camelize,
    ) {
        $this->mode = $mode;
        $this->camelize = $camelize;

        $numFields = pg_num_fields($this->result);

        for ($i = 0; $i < $numFields; $i++) {
            $this->colNames[$i] = pg_field_name($this->result, $i);

            switch (pg_field_type($this->result, $i)) {
                case 'int2':
                case 'int4':
                case 'int8':
                    $this->colTypes[$i] = self::INT;
                    break;
                case 'float4':
                case 'float8':
                    $this->colTypes[$i] = self::FLOAT;
                    break;
                case 'bool':
                    $this->colTypes[$i] = self::BOOL;
                    break;
                case 'json':
                    $this->colTypes[$i] = self::JSON;
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
    public function seek(int $i = 0): self
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

    protected function typifyRow(array $row, bool $assoc = true): array
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

    protected function typify(mixed $value, int $type): mixed
    {
        return match ($type) {
            self::INT => (int) $value,
            self::FLOAT => (float) $value,
            self::BOOL => ('t' === $value),
            self::JSON => json_decode((string) $value, true),
            default => $value,
        };
    }
}
