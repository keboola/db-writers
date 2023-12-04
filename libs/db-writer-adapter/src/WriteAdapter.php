<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter;

use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;

interface WriteAdapter
{
    public function drop(string $tableName): void;

    /**
     * @param ItemConfig[] $items
     */
    public function create(
        string $tableName,
        bool $isTempTable,
        array $items,
        ?array $primaryKeys = null,
    ): void;

    public function writeData(string $tableName, ExportConfig $exportConfig): void;

    public function upsert(ExportConfig $exportConfig, string $stageTableName): void;

    public function tableExists(string $tableName): bool;

    public function generateTmpName(string $tableName): string;

    /**
     * @return string[]
     */
    public function showTables(): array;

    /**
     * @return array{Field: string, Type: string}[]
     */
    public function getTableInfo(string $tableName): array;

    /**
     * @param ItemConfig[] $items
     */
    public function validateTable(string $tableName, array $items): void;
}
