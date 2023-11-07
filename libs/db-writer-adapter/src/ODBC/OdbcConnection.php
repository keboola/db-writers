<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\ODBC;

use Keboola\DbWriterAdapter\Connection\BaseConnection;
use Keboola\DbWriterAdapter\Exception\OdbcException;
use Psr\Log\LoggerInterface;
use Throwable;

class OdbcConnection extends BaseConnection
{
    protected string $dsn;

    protected string $user;

    protected string $password;

    protected int $odbcCursorType;

    protected int $odbcCursorMode;

    /** @var callable|null */
    protected $init;

    /** @var resource */
    protected $connection;

    /**
     * Note:
     *     $odbcCursorType AND $odbcCursorMode can help solve the speed/cache problems with some ODBC drivers.
     *     These default values are good for most drivers.
     *     You can try $odbcCursorMode = SQL_CUR_USE_ODBC, if you have speed problems.
     * @param string[] $initQueries
     */
    public function __construct(
        LoggerInterface $logger,
        string $dsn,
        string $user,
        string $password,
        ?callable $init = null,
        int $connectMaxRetries = self::CONNECT_DEFAULT_MAX_RETRIES,
        int $odbcCursorType = SQL_CURSOR_FORWARD_ONLY,
        int $odbcCursorMode = SQL_CUR_USE_DRIVER,
        array $initQueries = [],
    ) {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->init = $init;
        $this->odbcCursorType = $odbcCursorType;
        $this->odbcCursorMode = $odbcCursorMode;
        parent::__construct($logger, $connectMaxRetries, $initQueries);
    }
    protected function connect(): void
    {
        $this->logger->info(sprintf('Creating ODBC connection to "%s".', $this->dsn));
        ini_set('odbc.default_cursortype', (string) $this->odbcCursorType);

        try {
            /** @var resource|false $connection */
            $connection = @odbc_connect($this->dsn, $this->user, $this->password, $this->odbcCursorMode);
        } catch (Throwable $e) {
            $this->handleConnectionError($e->getMessage(), $e->getCode(), $e);
            throw new OdbcException($e->getMessage(), $e->getCode(), $e);
        }

        // "odbc_connect" can generate warning, if "set_error_handler" is not set, so we are checking it manually
        if ($connection === false) {
            $message = odbc_errormsg() . ' ' . odbc_error();
            $this->handleConnectionError($message);
            throw new OdbcException($message);
        }

        if ($this->init) {
            ($this->init)($connection);
        }

        $this->connection = $connection;
    }

    /**
     * @return resource
     */
    public function getConnection()
    {
        return $this->connection;
    }

    public function testConnection(): void
    {
        $this->exec('SELECT 1', 1);
    }

    public function quote(string $str): string
    {
        return "'" . str_replace("'", "''", $str) . "'";
    }

    public function quoteIdentifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    /**
     * @return null|array<int, array<string, mixed>>
     */
    protected function doQuery(string $queryType, string $query): ?array
    {
        try {
            $stmt = odbc_exec($this->connection, $query);
        } catch (Throwable $e) {
            throw new OdbcException($e->getMessage(), $e->getCode(), $e);
        }

        if ($stmt === false) {
            throw new OdbcException(odbc_errormsg($this->connection) . ' ' . odbc_error($this->connection));
        }

        switch ($queryType) {
            case self::QUERY_TYPE_EXEC:
                return null;
            case self::QUERY_TYPE_FETCH_ALL:
                $result = [];
                while ($row = odbc_fetch_array($stmt)) {
                    $result[] = $row;
                }
                return $result;
            default:
                throw new OdbcException(sprintf('Unknown query type "%s".', $queryType));
        }
    }

    /**
     * @return string[]
     */
    protected function getExpectedExceptionClasses(): array
    {
        return array_merge(self::BASE_RETRIED_EXCEPTIONS, [
            OdbcException::class,
        ]);
    }

    protected function handleConnectionError(
        string $error,
        int $code = 0,
        ?Throwable $previousException = null,
    ): void {
    }
}
