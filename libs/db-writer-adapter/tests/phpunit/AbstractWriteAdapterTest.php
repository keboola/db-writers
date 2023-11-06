<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\WriteAdapter;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use PDO;
use PHPUnit\Framework\Assert;
use function _PHPStan_1308c520e\RingCentral\Psr7\str;

abstract class AbstractWriteAdapterTest extends BaseTest
{
    abstract protected function createWriteAdapter(): WriteAdapter;

    public function testCreateTable(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        $this->compareTableStructure($config);
    }

    public function testCreateTempTable(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            true,
            $config->getItems(),
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Temporary Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertFalse($this->createWriteAdapter()->tableExists($config->getDbName()));

        /** @var array<int<0, max>,array<string, string>> $dbTable */
        $dbTable = $this->connection->fetchAll(
            'SHOW CREATE TABLE  ' . $config->getDbName(),
            Connection::DEFAULT_MAX_RETRIES,
        );

        Assert::assertCount(1, $dbTable);
        Assert::assertStringContainsString('CREATE TEMPORARY TABLE', (string) $dbTable[0]['Create Table']);
    }

    public function testCreateTableWithPK(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
            $config->hasPrimaryKey() ? $config->getPrimaryKey() : null,
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        $this->compareTableStructure($config);
    }

    public function testCreateTableIgnoreColumns(): void
    {
        $config = $this->createExportConfig([
            'items' => [
                [
                    'name' => 'int',
                    'dbName' => 'int',
                    'type' => 'IGNORE',
                    'size' => '55',
                    'nullable' => false,
                ],
                [
                    'name' => 'varchar',
                    'dbName' => 'varchar',
                    'type' => 'varchar',
                    'size' => '1023',
                    'nullable' => true,
                ],
            ],
        ]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        $expectedConfig = $this->createExportConfig([
            'items' => [
                [
                    'name' => 'varchar',
                    'dbName' => 'varchar',
                    'type' => 'varchar',
                    'size' => '1023',
                    'nullable' => true,
                ],
            ],
        ]);
        $this->compareTableStructure($expectedConfig);
    }

    public function testWriteTable(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );
        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        $data = $this->generateDataFile($config);

        $this->createWriteAdapter()->writeData(
            $config->getDbName(),
            $config->getTableFilePath(),
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Data written to table "%s".',
            $config->getDbName(),
        )));

        $this->compareTableData($config, $data);
    }

    public function testWriteTableWithMultipleColumnTypes(): void
    {
        $config = $this->createExportConfig([
            'items' => [
                [
                    'name' => 'int',
                    'dbName' => 'int',
                    'type' => 'int',
                    'size' => '55',
                    'nullable' => false,
                ],
                [
                    'name' => 'varchar',
                    'dbName' => 'varchar',
                    'type' => 'varchar',
                    'size' => '1023',
                    'nullable' => true,
                ],
                [
                    'name' => 'date',
                    'dbName' => 'date',
                    'type' => 'date',
                    'size' => null,
                    'nullable' => false,
                ],
                [
                    'name' => 'datetime',
                    'dbName' => 'datetime',
                    'type' => 'datetime',
                    'size' => null,
                    'nullable' => false,
                ],
                [
                    'name' => 'bool',
                    'dbName' => 'bool',
                    'type' => 'bool',
                    'size' => null,
                    'nullable' => false,
                ],
                [
                    'name' => 'decimal',
                    'dbName' => 'decimal',
                    'type' => 'decimal',
                    'size' => '10,5',
                    'nullable' => false,
                ],
            ],
        ]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" created',
            $config->getDbName(),
        )));
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        $data = $this->generateDataFile($config);

        $this->createWriteAdapter()->writeData(
            $config->getDbName(),
            $config->getTableFilePath(),
        );
        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Data written to table "%s".',
            $config->getDbName(),
        )));

        $this->compareTableStructure($config);
        $this->compareTableData($config, $data);
    }

    public function testTableExists(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        // Table Exists
        Assert::assertTrue($this->createWriteAdapter()->tableExists($config->getDbName()));

        // Table does not exist
        Assert::assertFalse($this->createWriteAdapter()->tableExists('non-existing-table'));
    }

    public function testGenerateTempTableName(): void
    {
        $tempTable = $this->createWriteAdapter()->generateTmpName('test');

        Assert::assertStringStartsWith('test_temp_', $tempTable);
    }

    public function testUpsert(): void
    {
        $config = $this->createExportConfig([]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        $data = $this->generateDataFile($config, 5);

        $this->createWriteAdapter()->writeData(
            $config->getDbName(),
            $config->getTableFilePath(),
        );

        $tmpTableName = $this->createWriteAdapter()->generateTmpName($config->getDbName());
        $this->createWriteAdapter()->create(
            $tmpTableName,
            true,
            $config->getItems(),
        );

        $dataForTempTable = array_slice($data, 0, 2);
        array_walk(
            $dataForTempTable,
            function (array &$row): void {
                $row['name'] = $row['name'] . '_updated';
            },
        );
        $data2 = $this->generateDataFile(
            $config,
            5,
            $dataForTempTable,
        );
        $this->createWriteAdapter()->writeData(
            $tmpTableName,
            $config->getTableFilePath(),
        );

        $this->createWriteAdapter()->upsert(
            $config,
            $tmpTableName,
        );

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Data upserted to table "%s".',
            $config->getDbName(),
        )));

        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Table "%s" has primary key, using upsert.',
            $config->getDbName(),
        )));

        $expectedData = $data;
        foreach ($data2 as $item) {
            $findKey = array_search($item['id'], array_column($expectedData, 'id'), true);
            if ($findKey !== false) {
                $expectedData[$findKey] = $item;
            } else {
                $expectedData[] = $item;
            }
        }
        $this->compareTableData($config, $expectedData);
    }

    public function testUpsertNoPK(): void
    {
        $config = $this->createExportConfig([
            'primary_key' => null,
        ]);

        $this->createWriteAdapter()->create(
            $config->getDbName(),
            false,
            $config->getItems(),
        );

        $data = $this->generateDataFile($config, 5);

        $this->createWriteAdapter()->writeData(
            $config->getDbName(),
            $config->getTableFilePath(),
        );

        $tmpTableName = $this->createWriteAdapter()->generateTmpName($config->getDbName());
        $this->createWriteAdapter()->create(
            $tmpTableName,
            true,
            $config->getItems(),
        );

        $data2 = $this->generateDataFile($config, 5);
        $this->createWriteAdapter()->writeData(
            $config->getDbName(),
            $config->getTableFilePath(),
        );

        $this->createWriteAdapter()->upsert(
            $config,
            $tmpTableName,
        );
        Assert::assertTrue($this->logger->hasInfo(sprintf(
            'Data upserted to table "%s".',
            $config->getDbName(),
        )));

        $this->compareTableData($config, array_merge($data, $data2));
    }

    private function compareTableStructure(ExportConfig $config): void
    {
        $tableData = $this->getTableStructure($config->getDbName());
        Assert::assertCount(count($config->getItems()), $tableData);

        foreach ($config->getItems() as $item) {
            $dbColumn = array_filter(
                $tableData,
                function (array $column) use ($item) {
                    return $column['Field'] === $item->getDbName();
                },
            );
            Assert::assertCount(1, $dbColumn);

            $dbColumn = current($dbColumn);

            preg_match('/^(\w+)(\((\d+,?\d*)\))?$/', $dbColumn['Type'], $matches);
            $type = $matches[1];
            match ($type) {
                'tinyint' => $type = 'bool',
                default => null,
            };

            $size = $matches[3] ?? null;

            Assert::assertEquals($item->getType(), $type);
            Assert::assertEquals($item->getNullable(), $dbColumn['Null'] === 'YES');
            Assert::assertEquals($item->hasDefault(), $dbColumn['Default'] !== null);
            if ($item->hasDefault()) {
                Assert::assertEquals($item->getDefault(), $dbColumn['Default']);
            }
            if ($item->hasSize()) {
                Assert::assertEquals($item->getSize(), $size);
            }
            if ($dbColumn['Key'] === 'PRI') {
                Assert::assertTrue($config->hasPrimaryKey());
                Assert::assertContains($item->getDbName(), $config->getPrimaryKey());
            }
        }
    }

    /**
     * @param array<string, mixed>[] $data
     */
    private function compareTableData(ExportConfig $config, array $data): void
    {
        $dbData = $this->getTableData($config->getDbName());
        Assert::assertCount(count($data), $dbData);
        Assert::assertEquals($data, $dbData);
    }
}
