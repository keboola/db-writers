<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use PDOException;
use SplFileInfo;

class PdoWriteAdapter extends BaseWriteAdapter
{
    public function __construct(
        readonly protected PdoConnection $connection,
        readonly protected QueryBuilder $queryBuilder,
    ) {
        parent::__construct();
    }

    public function drop(string $tableName): void
    {
        $this->connection->query(
            $this->queryBuilder->dropQueryStatement($this->connection, $tableName),
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

    public function writeData(string $tableName, string $csvPath): void
    {
        $query = $this->queryBuilder->writeDataQueryStatement($this->connection, $tableName, $csvPath);
        try {
            $this->connection->query($query);
        } catch (PDOException $e) {
            throw new UserException('Query failed: ' . $e->getMessage(), 400, $e);
        }
    }

    public function upsert(ExportConfig $exportConfig, string $stageTableName): void
    {
        // TODO: Implement upsert() method.
    }

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->connection->getConnection()->query(
            $this->queryBuilder->tableExistsQueryStatement($this->connection, $tableName),
        );
        if (!$stmt) {
            return false;
        }
        $res = $stmt->fetchAll();
        return !empty($res);
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
