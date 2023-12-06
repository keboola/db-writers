<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Connection;

use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\Exception\DeadConnectionException;
use Keboola\DbWriterAdapter\Exception\UserRetriedException;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Throwable;

abstract class BaseConnection implements Connection
{
    public const BASE_RETRIED_EXCEPTIONS = [
        DeadConnectionException::class, // see BaseDbConnection:isAlive()];
    ];

    protected LoggerInterface $logger;

    protected int $connectMaxRetries;

    /** @var string[] */
    protected array $userInitQueries;

    /**
     * Returns low-level connection resource or object.
     * @return resource|object
     */
    abstract public function getConnection();

    abstract public function testConnection(): void;

    abstract public function quote(string $str): string;

    abstract public function quoteIdentifier(string $str): string;

    abstract protected function connect(): void;

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract protected function doQuery(string $queryType, string $query): ?array;

    /**
     * @return string[]
     */
    abstract protected function getExpectedExceptionClasses(): array;

    /**
     * @param string[] $userInitQueries
     * @throws UserException
     */
    public function __construct(
        LoggerInterface $logger,
        int $connectMaxRetries = self::CONNECT_DEFAULT_MAX_RETRIES,
        array $userInitQueries = [],
    ) {
        $this->logger = $logger;
        $this->connectMaxRetries = max($connectMaxRetries, 1);
        $this->userInitQueries = $userInitQueries;
        $this->connectWithRetry();
    }

    public function isAlive(): void
    {
        try {
            $this->testConnection();
        } catch (UserException $e) {
            throw new DeadConnectionException('Dead connection: ' . $e->getMessage());
        }
    }

    public function exec(string $query, int $maxRetries = self::DEFAULT_MAX_RETRIES): void
    {
        $this->logger->debug(sprintf('Running query "%s".', $query));
        $this->callWithRetry(
            $maxRetries,
            function () use ($query): void {
                $this->doQuery(Connection::QUERY_TYPE_EXEC, $query);
            },
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $query, int $maxRetries = self::DEFAULT_MAX_RETRIES): array
    {
        $this->logger->debug(sprintf('Running query "%s".', $query));
        return $this->callWithRetry(
            $maxRetries,
            function () use ($query): array {
                return $this->doQuery(Connection::QUERY_TYPE_FETCH_ALL, $query) ?? [];
            },
        ) ?? [];
    }

    protected function connectWithRetry(): void
    {
        try {
            $this
                ->createRetryProxy($this->connectMaxRetries)
                ->call(function (): void {
                    $this->connect();
                });
        } catch (Throwable $e) {
            throw new UserException('Error connecting to DB: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return null|array<int, array<string, mixed>>
     * @throws Throwable|UserRetriedException
     */
    protected function callWithRetry(int $maxRetries, callable $callback): ?array
    {
        $proxy = $this->createRetryProxy($maxRetries);
        try {
            /** @var null|array<int, array<string, mixed>> $res */
            $res = $proxy->call($callback);
            return $res;
        } catch (Throwable $e) {
            throw in_array(get_class($e), $this->getExpectedExceptionClasses(), true) ?
                new UserRetriedException($proxy->getTryCount(), $e->getMessage(), 0, $e) :
                $e;
        }
    }

    protected function createRetryProxy(int $maxRetries): RetryProxy
    {
        $retryPolicy = new SimpleRetryPolicy($maxRetries, $this->getExpectedExceptionClasses());
        $backoffPolicy = new ExponentialBackOffPolicy(1000);
        return new RetryProxy($retryPolicy, $backoffPolicy, $this->logger);
    }

    protected function runUserInitQueries(): void
    {
        foreach ($this->userInitQueries as $userInitQuery) {
            $this->logger->info(sprintf('Running query "%s".', $userInitQuery));
            $this->doQuery(Connection::QUERY_TYPE_EXEC, $userInitQuery);
        }
    }
}
