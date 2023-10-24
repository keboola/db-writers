<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use ErrorException;
use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\Connection\BaseConnection;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;

class PdoConnection extends BaseConnection
{
    protected string $dsn;

    protected string $user;

    protected string $password;

    /** @var array<int, int|string> */
    protected array $options;

    /** @var callable|null */
    protected $init;

    protected PDO $pdo;

    /**
     * @param array<int, int|string> $options
     * @param string[] $userInitQueries
     * @throws UserException
     */
    public function __construct(
        LoggerInterface $logger,
        string $dsn,
        string $user,
        string $password,
        array $options,
        ?callable $init = null,
        int $connectMaxRetries = self::CONNECT_DEFAULT_MAX_RETRIES,
        array $userInitQueries = [],
    ) {
        // Convert errors to PDOExceptions
        $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;

        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
        $this->options = $options;
        $this->init = $init;
        parent::__construct($logger, $connectMaxRetries, $userInitQueries);
    }

    protected function connect(): void
    {
        $this->logger->info(sprintf('Creating PDO connection to "%s".', $this->dsn));
        $this->pdo = new PDO($this->dsn, $this->user, $this->password, $this->options);
        if ($this->init) {
            ($this->init)($this->pdo);
        }

        $this->runUserInitQueries();
    }

    public function testConnection(): void
    {
        $this->query('SELECT 1', 1);
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public function quote(string $str): string
    {
        return $this->pdo->quote($str);
    }

    public function quoteIdentifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    protected function doQuery(string $query): void
    {
        /** @var PDOStatement $stmt */
        $stmt = $this->pdo->prepare($query);
        $stmt->execute();
    }


    /**
     * @return string[]
     */
    protected function getExpectedExceptionClasses(): array
    {
        return array_merge(self::BASE_RETRIED_EXCEPTIONS, [
            PDOException::class,
            ErrorException::class, // eg. ErrorException: Warning: Empty row packet body
        ]);
    }
}
