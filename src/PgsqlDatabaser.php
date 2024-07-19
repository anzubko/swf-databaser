<?php declare(strict_types=1);

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
     * @param int $mode Mode for fetchAll() method.
     * @param bool $camelize Convert result to camel case.
     */
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        private readonly ?string $db = null,
        private readonly ?string $user = null,
        private readonly ?string $pass = null,
        private readonly bool $persistent = false,
        private readonly string $charset = 'utf-8',
        protected int $mode = Databaser::ASSOC,
        protected bool $camelize = true,
    ) {
        parent::__construct();
    }

    protected function assignResult(object|false $result): DatabaserResultInterface
    {
        if ($result instanceof PgSqlResult) {
            return new PgsqlDatabaserResult($result, $this->mode, $this->camelize);
        }

        return new EmptyDatabaserResult();
    }

    protected function executeQueries(string $queries): object
    {
        $this->connection ??= $this->connect();

        $result = @pg_query($this->connection, $queries);
        if (false !== $result) {
            return $result;
        }

        $lastError = pg_last_error($this->connection);
        if (preg_match('/^ERROR:\s*([\dA-Z]{5}):\s*(.+)/u', $lastError, $M)) {
            throw (new DatabaserException($M[2]))->setSqlState($M[1])->addSqlStateToMessage();
        }

        throw (new DatabaserException($lastError))->addSqlStateToMessage();
    }

    protected function escapeString(string $string): string
    {
        $this->connection ??= $this->connect();

        return (string) pg_escape_literal($this->connection, $string);
    }

    /**
     * @throws DatabaserException
     */
    private function connect(): PgSqlConnection
    {
        $connect = $this->persistent ? 'pg_pconnect' : 'pg_connect';

        $connection = $connect(
            sprintf(
                'host=%s port=%s dbname=%s user=%s password=%s',
                $this->host ?? '',
                $this->port ?? '',
                $this->db ?? '',
                $this->user ?? '',
                $this->pass ?? '',
            ), PGSQL_CONNECT_FORCE_NEW,
        );

        if (false === $connection) {
            throw (new DatabaserException('Error in the process of establishing a connection'))->addSqlStateToMessage();
        }

        pg_set_error_verbosity($connection, PGSQL_ERRORS_VERBOSE);

        if (-1 === pg_set_client_encoding($connection, $this->charset)) {
            throw (new DatabaserException(sprintf('Unable to set charset %s', $this->charset)))->addSqlStateToMessage();
        }

        return $connection;
    }
}
