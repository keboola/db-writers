<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\DefaultQueryBuilder;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class DefaultQueryBuilderTest extends TestCase
{
    private DefaultQueryBuilder $queryBuilder;

    private Connection $connection;

    public function __construct()
    {
        $this->queryBuilder = new DefaultQueryBuilder();
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('quoteIdentifier')->willReturnCallback(
            function (string $value) {
                return "`{$value}`";
            },
        );
        $this->connection->method('quote')->willReturnCallback(
            function (string $value) {
                return "'{$value}'";
            },
        );

        parent::__construct();
    }

    public function testDropTableQuery(): void
    {
        $this->assertEquals(
            'DROP TABLE IF EXISTS `test`;',
            $this->queryBuilder->dropQueryStatement($this->connection, 'test'),
        );
    }

    public function testCreateTableQuery(): void
    {
        $items = [];
        $items[] = ItemConfig::fromArray(
            [
                'name' => 'id',
                'dbName' => 'id',
                'type' => 'INT',
                'size' => null,
                'nullable' => false,
                'default' => null,
            ],
        );
        $items[] = ItemConfig::fromArray(
            [
                'name' => 'name',
                'dbName' => 'name',
                'type' => 'VARCHAR',
                'size' => '255',
                'nullable' => false,
                'default' => null,
            ],
        );
        $items[] = ItemConfig::fromArray(
            [
                'name' => 'age',
                'dbName' => 'age',
                'type' => 'INT',
                'size' => null,
                'nullable' => true,
                'default' => '18',
            ],
        );

        $expectedColumsDefinition = [
            '`id` INT NOT NULL',
            '`name` VARCHAR(255) NOT NULL',
            '`age` INT NULL DEFAULT \'18\'',
        ];

        $this->assertEquals(
            sprintf(
                'CREATE  TABLE `test` (%s) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
                implode(' ,', $expectedColumsDefinition),
            ),
            $this->queryBuilder->createQueryStatement(
                $this->connection,
                'test',
                false,
                $items,
            ),
        );

        $this->assertEquals(
            sprintf(
                'CREATE TEMPORARY TABLE `test` (%s) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
                implode(' ,', $expectedColumsDefinition),
            ),
            $this->queryBuilder->createQueryStatement(
                $this->connection,
                'test',
                true,
                $items,
            ),
        );

        $this->assertEquals(
            sprintf(
                'CREATE  TABLE `test` (%s,PRIMARY KEY (`id`,`name`)) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;',
                implode(' ,', $expectedColumsDefinition),
            ),
            $this->queryBuilder->createQueryStatement(
                $this->connection,
                'test',
                false,
                $items,
                [
                    'id',
                    'name',
                ],
            ),
        );
    }

    public function testWriteDataQuery(): void
    {
        $exportConfig = ExportConfig::fromArray(
            [
                'data_dir' => '/path/to/data',
                'writer_class' => 'Common',
                'tableId' => 'test',
                'dbName' => 'test',
                'db' => [
                    'database' => 'test',
                    'user' => 'test',
                ],
                'items' => [
                    [
                        'name' => 'id',
                        'dbName' => 'id',
                        'type' => 'INT',
                        'size' => null,
                        'nullable' => false,
                        'default' => null,
                    ],
                    [
                        'name' => 'name',
                        'dbName' => 'name',
                        'type' => 'VARCHAR',
                        'size' => '255',
                        'nullable' => false,
                        'default' => null,
                    ],
                    [
                        'name' => 'age',
                        'dbName' => 'age',
                        'type' => 'INT',
                    ],
                ],
            ],
            [
                [
                    'source' => 'test',
                    'destination' => 'test',
                    'columns' => [
                        'id',
                        'name',
                        'age',
                    ],
                ],
            ],
        );
        $query = $this->queryBuilder->writeDataQueryStatement(
            $this->connection,
            'test',
            $exportConfig,
        );

        $expectedQuery = <<<SQL
LOAD DATA LOCAL INFILE '/path/to/data/in/tables/test'
INTO TABLE `test`
CHARACTER SET utf8
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '\"'
ESCAPED BY ''
IGNORE 1 LINES
SQL;

        Assert::assertEquals(
            $expectedQuery,
            $query,
        );
    }

    public function testTableExistsQuery(): void
    {
        Assert::assertEquals(
            'SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = \'test\'',
            $this->queryBuilder->tableExistsQueryStatement($this->connection, 'test'),
        );
    }

    public function testUpsertUpdateRowsQuery(): void
    {
        $config = $this->getExportConfig();

        $query = $this->queryBuilder->upsertUpdateRowsQueryStatement(
            $this->connection,
            $config,
            'test',
        );

        Assert::assertEquals(
            'UPDATE `test` a INNER JOIN `test` b ON a.`id` = b.`id` AND a.`name` = b.`name` ' .
            'SET a.`id` = b.`id`,a.`name` = b.`name`,a.`age` = b.`age`;',
            $query,
        );
    }

    public function testUpsertDeleteRowsQuery(): void
    {
        $config = $this->getExportConfig();

        $query = $this->queryBuilder->upsertDeleteRowsQueryStatement(
            $this->connection,
            $config,
            'test',
        );

        Assert::assertEquals(
            'DELETE a.* FROM `test` a INNER JOIN `test` b ON a.`id` = b.`id` AND a.`name` = b.`name`',
            $query,
        );
    }

    public function testUpsertQuery(): void
    {
        $config = $this->getExportConfig();

        $query = $this->queryBuilder->upsertQueryStatement(
            $this->connection,
            $config,
            'test',
        );

        Assert::assertEquals(
            'INSERT INTO `test` (`id`, `name`, `age`) SELECT * FROM `test`',
            $query,
        );
    }

    public function testListTablesQuery(): void
    {
        Assert::assertEquals(
            $this->queryBuilder->listTablesQueryStatement($this->connection),
            'SHOW TABLES',
        );
    }

    public function testTableInfoQuery(): void
    {
        Assert::assertEquals(
            $this->queryBuilder->tableInfoQueryStatement($this->connection, 'test'),
            'DESCRIBE `test`',
        );
    }

    private function getExportConfig(): ExportConfig
    {
        $exportConfig = [
            'tableId' => 'test',
            'dbName' => 'test',
            'data_dir' => '/data',
            'writer_class' => 'Common',
            'db' => [
                'database' => 'test',
                'user' => 'test',
            ],
            'primaryKey' => [
                'id',
                'name',
            ],
            'items' => [
                [
                    'name' => 'id',
                    'dbName' => 'id',
                    'type' => 'INT',
                    'size' => null,
                    'nullable' => false,
                    'default' => null,
                ],
                [
                    'name' => 'name',
                    'dbName' => 'name',
                    'type' => 'VARCHAR',
                    'size' => '255',
                    'nullable' => false,
                    'default' => null,
                ],
                [
                    'name' => 'age',
                    'dbName' => 'age',
                    'type' => 'INT',
                    'size' => null,
                    'nullable' => true,
                    'default' => '18',
                ],
            ],
        ];

        $inputMapping = [
            [
                'source' => 'test',
                'destination' => 'test',
                'columns' => [
                    'id',
                    'name',
                    'age',
                ],
            ],
        ];

        return ExportConfig::fromArray($exportConfig, $inputMapping);
    }
}
