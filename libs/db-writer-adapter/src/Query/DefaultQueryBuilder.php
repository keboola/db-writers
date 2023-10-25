<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Query;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use SplFileInfo;

class DefaultQueryBuilder implements QueryBuilder
{
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
    ): string {
        $createTable = sprintf(
            'CREATE %s TABLE `%s`',
            $isTempTable ? 'TEMPORARY' : '',
            $tableName,
        );

        $filteredItems = array_filter(
            $items,
            function (ItemConfig $itemConfig) {
                return $itemConfig->getType() !== 'IGNORE';
            },
        );

        $columnsDefinition = implode(
            ',',
            array_map(
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
            ),
        );

        $defaultValues = 'DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;';

        return sprintf(
            '%s (%s) %s',
            $createTable,
            $columnsDefinition,
            $defaultValues,
        );
    }

    public function writeDataQueryStatement(string $tableName, SplFileInfo $csv): string
    {
        return '';
    }

    public function upsertQueryStatement(ExportConfig $exportConfig, string $stageTableName): string
    {
        return '';
    }
}
