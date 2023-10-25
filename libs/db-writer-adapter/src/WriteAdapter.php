<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter;

use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use SplFileInfo;

interface WriteAdapter
{
    public function drop(string $tableName): void;

    /**
     * @param ItemConfig[] $items
     */
    public function create(string $tableName, bool $isTempTable, array $items): void;

    public function writeData(string $tableName, SplFileInfo $csv): void;

    public function upsert(ExportConfig $exportConfig, string $stageTableName): void;

    public function tableExists(string $tableName): bool;

    public function generateTmpName(string $tableName): string;

    /**
     * @return string[]
     */
    public function showTables(string $dbName): array;

    /**
     * @param ItemConfig[] $items
     */
    public function validateTable(string $tableName, array $items): void;
}
