<?php declare(strict_types=1);

namespace SWF;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use SWF\Exception\DatabaserException;
use SWF\Interface\DatabaserResultInterface;
use function strlen;

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
     * @param string|null $name Optional name for connection.
     */
    public function __construct(
        private readonly ?string $host = null,
        private readonly ?int $port = null,
        private readonly ?string $db = null,
        private readonly ?string $user = null,
        private readonly ?string $pass = null,
        private readonly bool $persistent = false,
        private readonly string $charset = 'utf8mb4',
        private readonly ?string $name = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name ?? 'Mysql';
    }

    protected function assignResult(?object $result): DatabaserResultInterface
    {
        if ($result instanceof mysqli_result) {
            return new MysqlDatabaserResult($result, (int) $this->getConnection()->affected_rows);
        }

        return new EmptyDatabaserResult();
    }

    public function lastInsertId(): int
    {
        return (int) $this->getConnection()->insert_id;
    }

    protected function executeQueries(string $queries): ?object
    {
        try {
            $this->getConnection()->multi_query($queries);

            do {
                $result = $this->getConnection()->store_result();
            } while ($this->getConnection()->next_result());
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setState($e->getSqlState())->stateToMessage();
        }

        return false === $result ? null : $result;
    }

    protected function escapeString(string $string): string
    {
        return sprintf("'%s'", $this->getConnection()->real_escape_string($string));
    }

    /**
     * @throws DatabaserException
     */
    private function getConnection(): mysqli
    {
        return $this->connection ??= $this->connect();
    }

    /**
     * @throws DatabaserException
     */
    private function connect(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $host = $this->host;
        $socket = null;

        if (null === $host) {
            $defaultHost = ini_get('mysqli.default_host');
            if (false !== $defaultHost && strlen($defaultHost) > 0) {
                $host = $defaultHost;
            } else {
                $host = 'localhost';
            }
        } elseif (str_starts_with($host, '/')) {
            $host = 'localhost';
            $socket = $host;
        }

        if ($this->persistent) {
            $host = sprintf('p:%s', $host);
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
            throw (new DatabaserException($e->getMessage()))->setState($e->getSqlState())->stateToMessage();
        }

        return $connection;
    }
}
