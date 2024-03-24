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

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $socket = null;
        if (null === $this->host) {
            $this->host = 'localhost';
        } elseif (str_starts_with($this->host, '/')) {
            $socket = $this->host;
            $this->host = 'localhost';
        }

        if ($this->persistent ?? false) {
            $this->host = sprintf('p:%s', $this->host);
        }

        try {
            $this->connection = new mysqli(
                $this->host,
                $this->user,
                $this->pass,
                $this->db,
                $this->port ?? 3306,
                $socket,
            );

            $this->connection->set_charset($this->charset ?? 'utf8mb4');
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))
                ->setSqlState($e->getSqlState())
                ->addSqlStateToMessage()
            ;
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
            $result = new MysqlDatabaserResult($result, (int) $this->connection->affected_rows);
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
    public function lastInsertId(): int
    {
        if (!isset($this->connection)) {
            $this->connect();
        }

        return (int) $this->connection->insert_id;
    }

    /**
     * @inheritDoc
     *
     * @throws DatabaserException
     */
    protected function executeQueries(string $queries): mysqli_result|false
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
            throw (new DatabaserException($e->getMessage()))
                ->setSqlState($e->getSqlState())
                ->addSqlStateToMessage()
            ;
        }

        return $result;
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

        return '\'' . $this->connection->real_escape_string($string) . '\'';
    }
}
