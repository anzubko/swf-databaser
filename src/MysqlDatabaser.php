<?php declare(strict_types=1);

namespace SWF;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;

class MysqlDatabaser extends AbstractDatabaser
{
    protected mysqli $connection;

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
        protected ?int $port = 3306,
        protected ?string $db = null,
        protected ?string $user = null,
        protected ?string $pass = null,
        protected bool $persistent = false,
        protected string $charset = 'utf8mb4',
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

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $socket = null;
        if (null === $this->host) {
            $this->host = 'localhost';
        } elseif (str_starts_with($this->host, '/')) {
            $socket = $this->host;
            $this->host = 'localhost';
        }

        if ($this->persistent) {
            $this->host = sprintf('p:%s', $this->host);
        }

        try {
            $this->connection = new mysqli(
                hostname: $this->host,
                username: $this->user,
                password: $this->pass,
                database: $this->db,
                port: $this->port ?? 3306,
                socket: $socket,
            );

            $this->connection->set_charset($this->charset);
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setSqlState($e->getSqlState())->addSqlStateToMessage();
        }
    }

    /**
     * @inheritDoc
     */
    protected function makeBeginCommand(?string $isolation): string
    {
        if (null !== $isolation) {
            return sprintf('SET TRANSACTION %s; START TRANSACTION', $isolation);
        }

        return 'START TRANSACTION';
    }

    /**
     * @inheritDoc
     */
    protected function assignResult(object|false $result): DatabaserResultInterface
    {
        if ($result instanceof mysqli_result) {
            return new MysqlDatabaserResult($result, (int) $this->connection->affected_rows, $this->mode, $this->camelize);
        }

        return new EmptyDatabaserResult();
    }

    /**
     * @inheritDoc
     */
    public function lastInsertId(): int
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        return (int) $this->connection->insert_id;
    }

    /**
     * @inheritDoc
     */
    protected function executeQueries(string $queries): object|false
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

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

    /**
     * @inheritDoc
     */
    protected function escapeString(string $string): string
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        return "'" . $this->connection->real_escape_string($string) . "'";
    }
}
