<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter;

use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use PDOException;
use Psr\Log\LoggerInterface;

abstract class BaseWriteAdapter implements WriteAdapter
{
    public function __construct(
        readonly protected Connection $connection,
        readonly protected QueryBuilder $queryBuilder,
        readonly protected LoggerInterface $logger,
    ) {
    }

    public function drop(string $tableName): void
    {
        $this->connection->exec(
            $this->queryBuilder->dropQueryStatement($this->connection, $tableName),
        );
    }

    /**
     * @param ItemConfig[] $items
     */
    public function create(
        string $tableName,
        bool $isTempTable,
        array $items,
        ?array $primaryKeys = null,
    ): void {
        $this->connection->exec(
            $this->queryBuilder->createQueryStatement(
                $this->connection,
                $tableName,
                $isTempTable,
                $items,
                $primaryKeys,
            ),
        );
        $this->logger->info(sprintf(
            '%sTable "%s" created',
            $isTempTable ? 'Temporary ' : '',
            $tableName,
        ));
    }

    /**
     * @throws UserException
     */
    public function writeData(string $tableName, string $csvPath): void
    {
        $query = $this->queryBuilder->writeDataQueryStatement($this->connection, $tableName, $csvPath);
        try {
            $this->connection->exec($query);
        } catch (PDOException $e) {
            throw new UserException('Query failed: ' . $e->getMessage(), 400, $e);
        }
        $this->logger->info(sprintf(
            'Data written to table "%s".',
            $tableName,
        ));
    }

    public function upsert(ExportConfig $exportConfig, string $stageTableName): void
    {
        if ($exportConfig->hasPrimaryKey()) {
            $this->logger->info(sprintf(
                'Table "%s" has primary key, using upsert.',
                $exportConfig->getDbName(),
            ));
            $this->connection->exec(
                $this->queryBuilder->upsertUpdateRowsQueryStatement($this->connection, $exportConfig, $stageTableName),
            );

            // delete updated from temp table
            $this->connection->exec(
                $this->queryBuilder->upsertDeleteRowsQueryStatement($this->connection, $exportConfig, $stageTableName),
            );
        }

        $this->connection->exec(
            $this->queryBuilder->upsertQueryStatement($this->connection, $exportConfig, $stageTableName),
        );
        $this->logger->info(sprintf(
            'Data upserted to table "%s".',
            $exportConfig->getDbName(),
        ));
    }

    public function tableExists(string $tableName): bool
    {
        $res = $this->connection->fetchAll(
            $this->queryBuilder->tableExistsQueryStatement($this->connection, $tableName),
            Connection::DEFAULT_MAX_RETRIES,
        );
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
        /** @var array<int, array<string, string>> $res */
        $res = $this->connection->fetchAll(
            $this->queryBuilder->listTablesQueryStatement($this->connection),
            Connection::DEFAULT_MAX_RETRIES,
        );

        return array_map(fn(array $item) => (string) array_shift($item), $res);
    }

    /**
     * @param ItemConfig[] $items
     * @throws UserException
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
        /** @var array{Field: string, Type: string}[] $res */
        $res = $this->connection->fetchAll(
            $this->queryBuilder->tableInfoQueryStatement($this->connection, $tableName),
            Connection::DEFAULT_MAX_RETRIES,
        );

        return $res;
    }
}
