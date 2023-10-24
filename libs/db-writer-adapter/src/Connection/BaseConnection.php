<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Connection;

use Keboola\Component\UserException;
use Keboola\DbExtractor\Adapter\Exception\UserRetriedException;
use Keboola\DbWriterAdapter\Exception\DeadConnectionException;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Throwable;

abstract class BaseConnection
{
    public const CONNECT_DEFAULT_MAX_RETRIES = 3;

    public const DEFAULT_MAX_RETRIES = 5;

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

    abstract protected function doQuery(string $query): void;

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

    public function query(string $query, int $maxRetries = self::DEFAULT_MAX_RETRIES): void
    {
        $this->callWithRetry(
            $maxRetries,
            function () use ($query): void {
                $this->queryReconnectOnError($query);
            },
        );
    }

    /**
     * @throws UserRetriedException|Throwable
     */
    public function queryAndProcess(string $query, int $maxRetries): void
    {
        $this->callWithRetry(
            $maxRetries,
            function () use ($query): void {
                $this->queryReconnectOnError($query);
                $this->isAlive();
            },
        );
    }

    protected function queryReconnectOnError(string $query): void
    {
        $this->logger->debug(sprintf('Running query "%s".', $query));
        try {
            $this->doQuery($query);
        } catch (Throwable $e) {
            try {
                // Reconnect
                $this->connect();
            } catch (Throwable $e) {
            };
            throw $e;
        }
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

    protected function callWithRetry(int $maxRetries, callable $callback): void
    {
        $proxy = $this->createRetryProxy($maxRetries);
        try {
            $proxy->call($callback);
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
            $this->doQuery($userInitQuery);
        }
    }
}
