<?php

namespace Keboola\DbWriter\Writer;

use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use SplFileInfo;

abstract class BaseWriter
{
    protected Connection $connection;

    public function __construct(DatabaseConfig $databaseConfig)
    {
        $this->connection = $this->createConnection($databaseConfig);
    }

    public function write(ExportConfig $exportConfig): void
    {
        if ($exportConfig->isIncremental()) {
            $this->writeIncremental($exportConfig);
        } else {
            $this->writeFull($exportConfig);
        }
    }

    abstract public function testConnection(): void;

    abstract public function getTableInfo(string $tableName): array;

    protected function writeIncremental(ExportConfig $exportConfig): void
    {
        // write to staging table
        $stageTableName = $this->generateTmpName($exportConfig->getDbName());

        $this->drop($stageTableName);
        $this->create($stageTableName, true, $exportConfig->getItems());
        $this->writeData($stageTableName, $exportConfig->getCsv());

        // create destination table if not exists
        if (!$this->tableExists($exportConfig->getDbName())) {
            $this->create($exportConfig->getDbName(), false, $exportConfig->getItems());
        }
        $this->validateTable($exportConfig->getDbName(), $exportConfig->getItems());

        // upsert from staging to destination table
        $this->upsert($exportConfig, $stageTableName);
    }

    protected function writeFull(ExportConfig $exportConfig): void
    {
        $this->drop($exportConfig->getDbName());
        $this->create($exportConfig->getDbName(), false, $exportConfig->getItems());
        $this->write($exportConfig);
    }

    abstract protected function createConnection(DatabaseConfig $databaseConfig): Connection;

    abstract protected function drop(string $tableName): void;

    /**
     * @param ItemConfig[] $columns
     */
    abstract protected function create(
        string $tableName,
        bool $createTemporaryTable,
        array $columns,
    ): void;

    abstract protected function writeData(string $tableName, SplFileInfo $csv): void;

    abstract protected function upsert(ExportConfig $exportConfig, string $stageTableName): void;

    abstract protected function tableExists(string $tableName): bool;

    abstract protected function generateTmpName(string $tableName): string;

    abstract protected function showTables(string $dbName): array;

    abstract protected static function getAllowedTypes(): array;

    /**
     * @param ItemConfig[] $columns
     */
    abstract protected function validateTable(string $tableName, array $columns): void;
}