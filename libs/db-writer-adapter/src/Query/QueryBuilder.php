<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Query;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;

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
        ?array $primaryKeys = null,
    ): string;

    public function writeDataQueryStatement(
        Connection $connection,
        string $tableName,
        ExportConfig $exportConfig,
    ): string;

    public function tableExistsQueryStatement(Connection $connection, string $tableName): string;

    public function listTablesQueryStatement(Connection $connection): string;

    public function tableInfoQueryStatement(Connection $connection, string $dbName): string;

    public function upsertUpdateRowsQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string;

    public function upsertDeleteRowsQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string;

    public function upsertQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string;
}
