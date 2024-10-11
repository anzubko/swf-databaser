<?php
declare(strict_types=1);

namespace SWF;

use PgSql\Connection as PgSqlConnection;
use PgSql\Result as PgSqlResult;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;

class PgsqlDatabaser extends AbstractDatabaser
{
    protected string $beginCommand = 'START TRANSACTION';

    protected string $beginWithIsolationCommand = 'START TRANSACTION %s';

    protected string $commitCommand = 'COMMIT';

    protected string $rollbackCommand = 'ROLLBACK';

    protected ?string $createSavePointCommand = 'SAVEPOINT %s';

    protected ?string $releaseSavePointCommand = 'RELEASE SAVEPOINT %s';

    protected ?string $rollbackToSavePointCommand = 'ROLLBACK TO %s';

    private PgSqlConnection $connection;

    /**
     * @param string|null $host Host or socket to connect.
     * @param int|null $port Port to connect.
     * @param string|null $db Database name.
     * @param string|null $user Username.
     * @param string|null $pass Password.
     * @param bool $persistent Makes connection persistent.
     * @param string $charset Default charset.
     * @param string|null $name Optional name for connection.
     */
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        private readonly ?string $db = null,
        private readonly ?string $user = null,
        private readonly ?string $pass = null,
        private readonly bool $persistent = false,
        private readonly string $charset = 'utf-8',
        private readonly ?string $name = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name ?? 'Pgsql';
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
        $result = @pg_query($this->getConnection(), $queries);
        if (false !== $result) {
            return $result;
        }

        $lastError = pg_last_error($this->getConnection());
        if (preg_match('/^ERROR:\s*([\dA-Z]{5}):\s*(.+)/u', $lastError, $M)) {
            throw (new DatabaserException($M[2]))->setState($M[1])->stateToMessage();
        }

        throw (new DatabaserException($lastError))->stateToMessage();
    }

    protected function escapeString(string $string): string
    {
        return (string) pg_escape_literal($this->getConnection(), $string);
    }

    /**
     * @throws DatabaserException
     */
    private function getConnection(): PgSqlConnection
    {
        return $this->connection ??= $this->connect();
    }

    /**
     * @throws DatabaserException
     */
    private function connect(): PgSqlConnection
    {
        $params = [];
        if (null !== $this->host) {
            $params[] = sprintf('host=%s', $this->host);
        }
        if (null !== $this->port) {
            $params[] = sprintf('port=%d', $this->port);
        }
        if (null !== $this->db) {
            $params[] = sprintf('dbname=%s', $this->db);
        }
        if (null !== $this->user) {
            $params[] = sprintf('user=%s', $this->user);
        }
        if (null !== $this->pass) {
            $params[] = sprintf('password=%s', $this->pass);
        }

        if ($this->persistent) {
            $connection = pg_pconnect(implode(' ', $params), PGSQL_CONNECT_FORCE_NEW);
        } else {
            $connection = pg_connect(implode(' ', $params), PGSQL_CONNECT_FORCE_NEW);
        }

        if (false === $connection) {
            throw (new DatabaserException('Error in the process of establishing a connection'))->stateToMessage();
        }

        pg_set_error_verbosity($connection, PGSQL_ERRORS_VERBOSE);

        if (-1 === pg_set_client_encoding($connection, $this->charset)) {
            throw (new DatabaserException(sprintf('Unable to set charset %s', $this->charset)))->stateToMessage();
        }

        return $connection;
    }
}
