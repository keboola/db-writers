<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use Symfony\Component\Finder\SplFileInfo;

class PdoWriteAdapter extends BaseWriteAdapter
{
    public function drop(string $tableName): void
    {
        // TODO: Implement drop() method.
    }

    /**
     * @param ItemConfig[] $items
     */
    public function create(string $tableName, bool $isTempTable, array $items): void
    {
        // TODO: Implement create() method.
    }

    public function writeData(string $tableName, SplFileInfo $csv): void
    {
        // TODO: Implement writeData() method.
    }

    public function upsert(ExportConfig $exportConfig, string $stageTableName): void
    {
        // TODO: Implement upsert() method.
    }

    public function tableExists(string $tableName): bool
    {
        return false;
    }

    public function generateTmpName(string $tableName): string
    {
        return '';
    }

    /**
     * @return string[]
     */
    public function showTables(string $dbName): array
    {
        return [];
    }

    /**
     * @param ItemConfig[] $items
     */
    public function validateTable(string $tableName, array $items): void
    {
        // TODO: Implement validateTable() method.
    }
}
