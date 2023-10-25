<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests\ValueObject;

use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ExportConfigTest extends TestCase
{
    /**
     * @throws PropertyNotSetException
     */
    public function testFullConfig(): void
    {
        $config = [
            'data_dir' => 'data_dir',
            'writer_class' => 'writer_class',
            'db' => [
                'host' => 'host',
                'port' => '123',
                'database' => 'database',
                'user' => 'user',
                '#password' => 'password',
            ],
            'tableId' => 'tableId',
            'dbName' => 'dbName',
            'incremental' => true,
            'export' => false,
            'primary_key' => ['pk1', 'pk2'],
            'items' => [
                [
                    'name' => 'nameValue',
                    'dbName' => 'dbNameValues',
                    'type' => 'typeValue',
                    'size' => 'sizeValue',
                    'nullable' => true,
                    'default' => 'defaultValue',
                ],
            ],
        ];

        $exportConfig = ExportConfig::fromArray($config);

        Assert::assertSame($config['data_dir'], $exportConfig->getDataDir());
        Assert::assertSame($config['writer_class'], $exportConfig->getWriterClass());
        Assert::assertSame($config['tableId'], $exportConfig->getTableId());
        Assert::assertSame($config['dbName'], $exportConfig->getDbName());
        Assert::assertSame($config['incremental'], $exportConfig->isIncremental());
        Assert::assertSame($config['export'], $exportConfig->isExport());
        Assert::assertSame($config['primary_key'], $exportConfig->getPrimaryKey());

        $databaseConfig = $exportConfig->getDatabaseConfig();
        Assert::assertSame($config['db']['host'], $databaseConfig->getHost());
        Assert::assertSame($config['db']['port'], $databaseConfig->getPort());
        Assert::assertSame($config['db']['database'], $databaseConfig->getDatabase());
        Assert::assertSame($config['db']['user'], $databaseConfig->getUser());
        Assert::assertSame($config['db']['#password'], $databaseConfig->getPassword());
        Assert::assertFalse($databaseConfig->hasSchema());

        $itemConfig = $exportConfig->getItems()[0];
        Assert::assertSame($config['items'][0]['name'], $itemConfig->getName());
        Assert::assertSame($config['items'][0]['dbName'], $itemConfig->getDbName());
        Assert::assertSame($config['items'][0]['type'], $itemConfig->getType());
        Assert::assertSame($config['items'][0]['size'], $itemConfig->getSize());
        Assert::assertSame($config['items'][0]['nullable'], $itemConfig->getNullable());
        Assert::assertSame($config['items'][0]['default'], $itemConfig->getDefault());
    }

    public function testOnlyRequiredParams(): void
    {
        $config = [
            'data_dir' => 'data_dir',
            'writer_class' => 'writer_class',
            'db' => [
                'database' => 'database',
                'user' => 'user',
            ],
            'tableId' => 'tableId',
            'dbName' => 'dbName',
            'items' => [
                [
                    'name' => 'nameValue',
                    'dbName' => 'dbNameValues',
                    'type' => 'typeValue',
                ],
            ],
        ];

        $exportConfig = ExportConfig::fromArray($config);
        Assert::assertFalse($exportConfig->hasPrimaryKey());

        $databaseConfig = $exportConfig->getDatabaseConfig();
        Assert::assertFalse($databaseConfig->hasSchema());
        Assert::assertFalse($databaseConfig->hasPort());
        Assert::assertFalse($databaseConfig->hasPassword());
        Assert::assertFalse($databaseConfig->hasSshConfig());
        Assert::assertFalse($databaseConfig->hasHost());

        $itemConfig = $exportConfig->getItems()[0];
        Assert::assertFalse($itemConfig->hasSize());
        Assert::assertFalse($itemConfig->getNullable());
        Assert::assertFalse($itemConfig->hasDefault());
    }
}
