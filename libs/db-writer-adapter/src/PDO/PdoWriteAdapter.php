<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use SplFileInfo;

class PdoWriteAdapter extends BaseWriteAdapter
{
    public function __construct(
        readonly protected Connection $connection,
        readonly protected QueryBuilder $queryBuilder,
    ) {
        parent::__construct();
    }

    public function drop(string $tableName): void
    {
        $this->connection->query(
            $this->queryBuilder->dropQueryStatement($this->connection, $tableName),
            Connection::DEFAULT_MAX_RETRIES,
        );
    }

    /**
     * @param ItemConfig[] $items
     */
    public function create(string $tableName, bool $isTempTable, array $items): void
    {
        $this->connection->query(
            $this->queryBuilder->createQueryStatement($this->connection, $tableName, $isTempTable, $items),
            Connection::DEFAULT_MAX_RETRIES,
        );
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
        $tmpId = '_temp_' . uniqid();
        return mb_substr($tableName, 0, 64 - mb_strlen($tmpId)) . $tmpId;
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
