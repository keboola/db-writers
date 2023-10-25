<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Query;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use SplFileInfo;

interface QueryBuilder
{
    public function dropQueryStatement(Connection $connection, string $tableName): string;

    /**
     * @param ItemConfig[] $items
     */
    public function createQueryStatement(
        Connection $connection,
        string $tableName,
        bool $isTempTable,
        array $items,
    ): string;

    public function writeDataQueryStatement(string $tableName, SplFileInfo $csv): string;

    public function upsertQueryStatement(ExportConfig $exportConfig, string $stageTableName): string;
}
