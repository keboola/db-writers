<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\Query\DefaultQueryBuilder;
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
        Assert::assertTrue(true);
    }

    public function testTableExistsQuery(): void
    {
        Assert::assertTrue(true);
    }

    public function testUpsertUpdateRowsQuery(): void
    {
        Assert::assertTrue(true);
    }

    public function testUpsertDeleteRowsQuery(): void
    {
        Assert::assertTrue(true);
    }

    public function testUpsertQuery(): void
    {
        Assert::assertTrue(true);
    }

    public function testListTablesQuery(): void
    {
        Assert::assertTrue(true);
    }

    public function testTableInfoQuery(): void
    {
        Assert::assertTrue(true);
    }
}
