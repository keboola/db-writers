<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Connection;

use Keboola\DbExtractor\Adapter\Exception\UserRetriedException;

interface Connection
{
    public const CONNECT_DEFAULT_MAX_RETRIES = 3;

    public const DEFAULT_MAX_RETRIES = 5;

    public const QUERY_TYPE_EXEC = 'exec';

    public const QUERY_TYPE_FETCH_ALL = 'fetchAll';

    /**
     * Returns low-level connection resource or object.
     * @return resource|object
     */
    public function getConnection();

    public function testConnection(): void;

    public function isAlive(): void;

    public function quote(string $str): string;

    public function quoteIdentifier(string $str): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $query, int $maxRetries): array;

    public function exec(string $query, int $maxRetries): void;
}
