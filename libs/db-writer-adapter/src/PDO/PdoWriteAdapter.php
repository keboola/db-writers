<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use PDO;
use PDOException;

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
        if (!$this->tableExists($exportConfig->getDbName())) {
            $this->create($exportConfig->getDbName(), false, $exportConfig->getItems());
        }

        $columns = array_map(function ($item) {
            return $item->getDbName();
        }, $exportConfig->getItems());

        if ($exportConfig->hasPrimaryKey()) {
            $this->connection->query(
                $this->queryBuilder->upsertUpdateRowsQueryStatement($this->connection, $exportConfig, $stageTableName),
            );

            // delete updated from temp table
            $this->connection->query(
                $this->queryBuilder->upsertDeleteRowsQueryStatement($this->connection, $exportConfig, $stageTableName),
            );
        }

        $this->connection->query(
            $this->queryBuilder->upsertQueryStatement($this->connection, $exportConfig, $stageTableName),
        );
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
    public function showTables(): array
    {
        $stmt = $this->connection->getConnection()->query(
            $this->queryBuilder->listTablesQueryStatement($this->connection),
        );
        if (!$stmt) {
            return [];
        }
        $res = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $res;
    }

    /**
     * @param ItemConfig[] $items
     */
    public function validateTable(string $tableName, array $items): void
    {
        $dbBolumns = $this->getTableInfo($tableName);
        foreach ($items as $item) {
            $dbBolumn = array_filter(
                $dbBolumns,
                function ($column) use ($item) {
                    return $column['Field'] === $item->getDbName();
                },
            );

            if (count($dbBolumn) !== 1) {
                throw new UserException(sprintf(
                    'Column \'%s\' not found in destination table \'%s\'',
                    $item->getDbName(),
                    $tableName,
                ));
            }

            $dbDataType = (string) preg_replace(
                '/\(.*\)/',
                '',
                current($dbBolumn)['Type'],
            );

            if (strtolower($dbDataType) !== strtolower($item->getType())) {
                throw new UserException(sprintf(
                    'Data type mismatch. Column \'%s\' is of type \'%s\' in writer, '.
                    'but is \'%s\' in destination table \'%s\'',
                    $item->getDbName(),
                    $item->getType(),
                    $dbDataType,
                    $tableName,
                ));
            }
        }
    }

    /**
     * @return array{Field: string, Type: string}[]
     */
    public function getTableInfo(string $tableName): array
    {
        $stmt = $this->connection->getConnection()->query(
            $this->queryBuilder->tableInfoQueryStatement($this->connection, $tableName),
        );
        if (!$stmt) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
