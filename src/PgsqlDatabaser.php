<?php
declare(strict_types=1);

namespace SWF;

use PgSql\Connection as PgSqlConnection;
use PgSql\Result as PgSqlResult;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;

class PgsqlDatabaser extends AbstractDatabaser
{
    private PgSqlConnection $connection;

    /**
     * @param string|null $name Optional name for connection.
     * @param string|null $host Host or socket to connect.
     * @param int|null $port Port to connect.
     * @param string|null $db Database name.
     * @param string|null $user Username.
     * @param string|null $pass Password.
     * @param bool $persistent Makes connection persistent.
     * @param string $charset Default charset.
     *
     * @throws DatabaserException
     */
    public function __construct(
        ?string $name = null,
        ?string $host = null,
        ?int $port = null,
        ?string $db = null,
        ?string $user = null,
        ?string $pass = null,
        bool $persistent = false,
        string $charset = 'utf-8',
    ) {
        $params = [];
        if (null !== $host) {
            $params[] = sprintf('host=%s', $host);
        }
        if (null !== $port) {
            $params[] = sprintf('port=%d', $port);
        }
        if (null !== $db) {
            $params[] = sprintf('dbname=%s', $db);
        }
        if (null !== $user) {
            $params[] = sprintf('user=%s', $user);
        }
        if (null !== $pass) {
            $params[] = sprintf('password=%s', $pass);
        }

        if ($persistent) {
            $connection = pg_pconnect(implode(' ', $params), PGSQL_CONNECT_FORCE_NEW);
        } else {
            $connection = pg_connect(implode(' ', $params), PGSQL_CONNECT_FORCE_NEW);
        }

        if (false === $connection) {
            throw (new DatabaserException('Error in the process of establishing a connection'))->stateToMessage();
        }

        pg_set_error_verbosity($connection, PGSQL_ERRORS_VERBOSE);

        if (-1 === pg_set_client_encoding($connection, $charset)) {
            throw (new DatabaserException(sprintf('Unable to set charset %s', $charset)))->stateToMessage();
        }

        $this->connection = $connection;

        parent::__construct($name ?? 'Pgsql');
    }

    protected function makeBeginCommand(?string $isolation = null): string
    {
        if (null === $isolation) {
            return 'START TRANSACTION';
        }

        return sprintf('START TRANSACTION %s', $isolation);
    }

    protected function assignResult(?object $result): DatabaserResultInterface
    {
        if ($result instanceof PgSqlResult) {
            return new PgsqlDatabaserResult($result);
        }

        return new EmptyDatabaserResult();
    }

    protected function executeQueries(string $queries): object
    {
        $result = @pg_query($this->connection, $queries);
        if (false !== $result) {
            return $result;
        }

        $lastError = pg_last_error($this->connection);
        if (preg_match('/^ERROR:\s*([\dA-Z]{5}):\s*(.+)/u', $lastError, $M)) {
            throw (new DatabaserException($M[2]))->setState($M[1])->stateToMessage();
        }

        throw (new DatabaserException($lastError))->stateToMessage();
    }

    protected function escapeString(string $string): string
    {
        return (string) pg_escape_literal($this->connection, $string);
    }
}
