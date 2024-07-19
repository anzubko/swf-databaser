<?php declare(strict_types=1);

namespace SWF;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;

class MysqlDatabaser extends AbstractDatabaser
{
    protected string $beginCommand = 'START TRANSACTION';

    protected string $beginWithIsolationCommand = 'SET TRANSACTION %s; START TRANSACTION';

    protected string $commitCommand = 'COMMIT';

    protected string $rollbackCommand = 'ROLLBACK';

    protected ?string $createSavePointCommand = 'SAVEPOINT %s';

    protected ?string $releaseSavePointCommand = 'RELEASE SAVEPOINT %s';

    protected ?string $rollbackToSavePointCommand = 'ROLLBACK TO %s';

    private mysqli $connection;

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
        private readonly string $charset = 'utf8mb4',
        protected int $mode = Databaser::ASSOC,
        protected bool $camelize = true,
    ) {
        parent::__construct();
    }

    protected function assignResult(object|false $result): DatabaserResultInterface
    {
        if ($result instanceof mysqli_result) {
            return new MysqlDatabaserResult($result, (int) $this->connection->affected_rows, $this->mode, $this->camelize);
        }

        return new EmptyDatabaserResult();
    }

    public function lastInsertId(): int
    {
        return isset($this->connection) ? (int) $this->connection->insert_id : 0;
    }

    protected function executeQueries(string $queries): object|false
    {
        $this->connection ??= $this->connect();

        try {
            $this->connection->multi_query($queries);

            do {
                $result = $this->connection->store_result();
            } while ($this->connection->next_result());
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setSqlState($e->getSqlState())->addSqlStateToMessage();
        }

        return $result;
    }

    protected function escapeString(string $string): string
    {
        $this->connection ??= $this->connect();

        return sprintf("'%s'", $this->connection->real_escape_string($string));
    }

    /**
     * @throws DatabaserException
     */
    private function connect(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $host = $this->host;
        $socket = null;

        if (null !== $host && str_starts_with($host, '/')) {
            $host = 'localhost';
            $socket = $host;
        }

        if ($this->persistent) {
            $host = sprintf('p:%s', $host ?? ini_get('mysqli.default_host'));
        }

        try {
            $connection = new mysqli(
                hostname: $host,
                username: $this->user,
                password: $this->pass,
                database: $this->db,
                port: $this->port,
                socket: $socket,
            );

            $connection->set_charset($this->charset);
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setSqlState($e->getSqlState())->addSqlStateToMessage();
        }

        return $connection;
    }
}
