<?php declare(strict_types=1);

namespace SWF;

use PgSql\Connection as PgSqlConnection;
use PgSql\Result as PgSqlResult;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;

class PgsqlDatabaser extends AbstractDatabaser
{
    protected PgSqlConnection $connection;

    /**
     * @param string|null $host Host or socket to connect.
     * @param int|null $port Port to connect.
     * @param string|null $db Database name.
     * @param string|null $user Username.
     * @param string|null $pass Password.
     * @param bool $persistent Makes connection persistent.
     * @param string $charset Default charset.
     * @param int $mode Mode for fetchAll() method.
     * @param bool $camelize Convert result to camel case.
     */
    public function __construct(
        protected ?string $host = 'localhost',
        protected ?int $port = 5432,
        protected ?string $db = null,
        protected ?string $user = null,
        protected ?string $pass = null,
        protected bool $persistent = false,
        protected string $charset = 'utf-8',
        int $mode = Databaser::ASSOC,
        bool $camelize = true,
    ) {
        $this->mode = $mode;
        $this->camelize = $camelize;
    }

    /**
     * @inheritDoc
     */
    protected function connect(): void
    {
        if (isset($this->connection)) {
            return;
        }

        $connection = ($this->persistent ? 'pg_pconnect' : 'pg_connect')(
            sprintf(
                'host=%s port=%s dbname=%s user=%s password=%s',
                $this->host ?? 'localhost',
                $this->port ?? 5432,
                $this->db ?? '',
                $this->user ?? '',
                $this->pass ?? '',
            ),
            PGSQL_CONNECT_FORCE_NEW,
        );

        if (false === $connection) {
            throw (new DatabaserException('Error in the process of establishing a connection'))->addSqlStateToMessage();
        }

        pg_set_error_verbosity($connection, PGSQL_ERRORS_VERBOSE);

        if (-1 === pg_set_client_encoding($connection, $this->charset)) {
            throw (new DatabaserException(sprintf('Unable to set charset %s', $this->charset)))->addSqlStateToMessage();
        }

        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    protected function makeBeginCommand(?string $isolation): string
    {
        if (null !== $isolation) {
            return sprintf('START TRANSACTION %s', $isolation);
        }

        return 'START TRANSACTION';
    }

    /**
     * @inheritDoc
     */
    protected function assignResult(object|false $result): DatabaserResultInterface
    {
        if ($result instanceof PgSqlResult) {
            return new PgsqlDatabaserResult($result, $this->mode, $this->camelize);
        }

        return new EmptyDatabaserResult();
    }

    /**
     * @inheritDoc
     */
    protected function executeQueries(string $queries): object
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        $result = @pg_query($this->connection, $queries);
        if (false !== $result) {
            return $result;
        }

        $lastError = pg_last_error($this->connection);
        if (preg_match('/^ERROR:\s*([\dA-Z]{5}):\s*(.+)/u', $lastError, $M)) {
            throw (new DatabaserException($M[2]))->setSqlState($M[1])->addSqlStateToMessage();
        } else {
            throw (new DatabaserException($lastError))->addSqlStateToMessage();
        }
    }

    /**
     * @inheritDoc
     */
    protected function escapeString(string $string): string
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        return (string) pg_escape_literal($this->connection, $string);
    }
}
