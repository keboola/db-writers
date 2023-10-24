<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Connection;

interface Connection
{
    public const CONNECT_DEFAULT_MAX_RETRIES = 3;

    public const DEFAULT_MAX_RETRIES = 5;

    /**
     * Returns low-level connection resource or object.
     * @return resource|object
     */
    public function getConnection();

    public function testConnection(): void;

    public function isAlive(): void;

    public function quote(string $str): string;

    public function quoteIdentifier(string $str): string;

    public function query(string $query, int $maxRetries): void;
}
