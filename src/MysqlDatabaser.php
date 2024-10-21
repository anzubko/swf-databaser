<?php
declare(strict_types=1);

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
        private readonly ?string $name = null,
        ?string $host = null,
        ?int $port = null,
        ?string $db = null,
        ?string $user = null,
        ?string $pass = null,
        bool $persistent = false,
        string $charset = 'utf8mb4',
    ) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $socket = null;
        if (null === $host) {
            $host = ini_get('mysqli.default_host');
            if (false === $host || '' === $host) {
                $host = 'localhost';
            }
        } elseif (str_starts_with($host, '/')) {
            $host = 'localhost';
            $socket = $host;
        }

        if ($persistent) {
            $host = sprintf('p:%s', $host);
        }

        try {
            $connection = new mysqli($host, $user, $pass, $db, $port, $socket);
            $connection->set_charset($charset);
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setState($e->getSqlState())->stateToMessage();
        }

        $this->connection = $connection;
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
            return new MysqlDatabaserResult($result, (int) $this->connection->affected_rows);
        }

        return new EmptyDatabaserResult();
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    protected function executeQueries(string $queries): ?object
    {
        try {
            $this->connection->multi_query($queries);

            do {
                $result = $this->connection->store_result();
            } while ($this->connection->next_result());
        } catch (mysqli_sql_exception $e) {
            throw (new DatabaserException($e->getMessage()))->setState($e->getSqlState())->stateToMessage();
        }

        return false === $result ? null : $result;
    }

    protected function escapeString(string $string): string
    {
        return sprintf("'%s'", $this->connection->real_escape_string($string));
    }
}
