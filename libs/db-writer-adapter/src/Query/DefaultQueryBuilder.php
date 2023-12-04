<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Query;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;

class DefaultQueryBuilder implements QueryBuilder
{
    protected string $charset = 'utf8';

    public function dropQueryStatement(Connection $connection, string $tableName): string
    {
        return sprintf('DROP TABLE IF EXISTS %s;', $connection->quoteIdentifier($tableName));
    }

    /**
     * @param ItemConfig[] $items
     * @throws PropertyNotSetException
     */
    public function createQueryStatement(
        Connection $connection,
        string $tableName,
        bool $isTempTable,
        array $items,
        ?array $primaryKeys = null,
    ): string {
        $createTable = sprintf(
            'CREATE %s TABLE `%s`',
            $isTempTable ? 'TEMPORARY' : '',
            $tableName,
        );

        $filteredItems = array_filter(
            $items,
            function (ItemConfig $itemConfig) {
                return strtolower($itemConfig->getType()) !== 'ignore';
            },
        );

        $columnsDefinition = array_map(
            function (ItemConfig $itemConfig) use ($connection) {
                return sprintf(
                    '%s %s%s %s %s',
                    $connection->quoteIdentifier($itemConfig->getDbName()),
                    $itemConfig->getType(),
                    $itemConfig->hasSize() ? sprintf('(%s)', $itemConfig->getSize()) : '',
                    $itemConfig->getNullable() ? 'NULL' : 'NOT NULL',
                    $itemConfig->hasDefault() && $itemConfig->getType() !== 'TEXT' ?
                        'DEFAULT ' . $connection->quote($itemConfig->getDefault()) :
                        '',
                );
            },
            $filteredItems,
        );

        if ($primaryKeys) {
            $columnsDefinition[] = sprintf(
                'PRIMARY KEY (%s)',
                implode(',', array_map(fn($item) => $connection->quoteIdentifier($item), $primaryKeys)),
            );
        }

        $defaultValues = sprintf(
            'DEFAULT CHARSET=%s COLLATE=%s_unicode_ci;',
            $this->charset,
            $this->charset,
        );

        return sprintf(
            '%s (%s) %s',
            $createTable,
            implode(',', $columnsDefinition),
            $defaultValues,
        );
    }

    public function writeDataQueryStatement(
        Connection $connection,
        string $tableName,
        ExportConfig $exportConfig,
    ): string {
        $query = <<<SQL
LOAD DATA LOCAL INFILE '%s'
INTO TABLE %s
CHARACTER SET utf8
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '\"'
ESCAPED BY ''
IGNORE 1 LINES
SQL;

        return sprintf(
            $query,
            $exportConfig->getTableFilePath(),
            $connection->quoteIdentifier($tableName),
        );
    }

    public function tableExistsQueryStatement(Connection $connection, string $tableName): string
    {
        return sprintf(
            'SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = %s',
            $connection->quote($tableName),
        );
    }

    public function upsertUpdateRowsQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string {
        $columns = array_map(function ($item) {
            return $item->getDbName();
        }, $exportConfig->getItems());

        // update data
        $joinClauseArr = array_map(fn($item) => sprintf(
            'a.%s = b.%s',
            $connection->quoteIdentifier($item),
            $connection->quoteIdentifier($item),
        ), $exportConfig->getPrimaryKey());
        $joinClause = implode(' AND ', $joinClauseArr);

        $valuesClauseArr = array_map(fn($item) => sprintf(
            'a.%s = b.%s',
            $connection->quoteIdentifier($item),
            $connection->quoteIdentifier($item),
        ), $columns);
        $valuesClause = implode(',', $valuesClauseArr);

        return sprintf(
            'UPDATE %s a INNER JOIN %s b ON %s SET %s;',
            $connection->quoteIdentifier($exportConfig->getDbName()),
            $connection->quoteIdentifier($stageTableName),
            $joinClause,
            $valuesClause,
        );
    }

    public function upsertDeleteRowsQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string {
        $joinClauseArr = array_map(fn($item) => sprintf(
            'a.%s = b.%s',
            $connection->quoteIdentifier($item),
            $connection->quoteIdentifier($item),
        ), $exportConfig->getPrimaryKey());
        $joinClause = implode(' AND ', $joinClauseArr);

        return sprintf(
            'DELETE a.* FROM %s a INNER JOIN %s b ON %s',
            $connection->quoteIdentifier($stageTableName),
            $connection->quoteIdentifier($exportConfig->getDbName()),
            $joinClause,
        );
    }

    public function upsertQueryStatement(
        Connection $connection,
        ExportConfig $exportConfig,
        string $stageTableName,
    ): string {
        $columns = array_map(function ($item) use ($connection) {
            return $connection->quoteIdentifier($item->getDbName());
        }, $exportConfig->getItems());

        return sprintf(
            'INSERT INTO %s (%s) SELECT * FROM %s',
            $connection->quoteIdentifier($exportConfig->getDbName()),
            implode(', ', $columns),
            $connection->quoteIdentifier($stageTableName),
        );
    }

    public function listTablesQueryStatement(Connection $connection): string
    {
        return 'SHOW TABLES';
    }

    public function tableInfoQueryStatement(Connection $connection, string $dbName): string
    {
        return sprintf(
            'DESCRIBE %s',
            $connection->quoteIdentifier($dbName),
        );
    }
}
