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
     * @param bool|null $persistent Makes connection persistent.
     * @param string|null $charset Default charset.
     * @param int|null $mode Mode for fetchAll() method.
     * @param bool $camelize Convert result to camel case.
     *
     * @see Databaser
     */
    public function __construct(
        protected ?string $host = null,
        protected ?int $port = null,
        protected ?string $db = null,
        protected ?string $user = null,
        protected ?string $pass = null,
        protected ?bool $persistent = null,
        protected ?string $charset = null,
        ?int $mode = null,
        bool $camelize = false,
    ) {
        $this->mode = $mode;
        $this->camelize = $camelize;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    protected function connect(): void
    {
        if (isset($this->connection)) {
            return;
        }

        $connect = ($this->persistent ?? false) ? 'pg_pconnect' : 'pg_connect';
        $connection = $connect(
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
            throw (new DatabaserException('Error in the process of establishing a connection'))
                ->addSqlStateToMessage()
            ;
        }

        pg_set_error_verbosity($connection, PGSQL_ERRORS_VERBOSE);

        $charset = $this->charset ?? 'utf-8';
        if (-1 === pg_set_client_encoding($connection, $charset)) {
            throw (new DatabaserException(sprintf('Unable to set charset %s', $charset)))
                ->addSqlStateToMessage()
            ;
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
            $result = new PgsqlDatabaserResult($result);
        } else {
            $result = new EmptyDatabaserResult();
        }

        return $result->setMode($this->mode)->setCamelize($this->camelize);
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
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
     *
     * @throws DatabaserException
     */
    protected function escapeString(string $string): string
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        return (string) pg_escape_literal($this->connection, $string);
    }
}
