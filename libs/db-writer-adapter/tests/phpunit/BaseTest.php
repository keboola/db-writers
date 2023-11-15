<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests;

use Exception;
use Ihsw\Toxiproxy\Toxiproxy;
use Keboola\Csv\CsvWriter;
use Keboola\DbWriterAdapter\Tests\Traits\PdoCreateConnectionTrait;
use Keboola\DbWriterAdapter\Tests\Traits\TestDataTrait;
use Keboola\DbWriterAdapter\Tests\Traits\ToxiProxyTrait;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use Keboola\DbWriterConfig\Exception\UserException;
use Keboola\Temp\Temp;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Symfony\Component\Filesystem\Filesystem;

abstract class BaseTest extends TestCase
{
    use TestDataTrait;
    use PdoCreateConnectionTrait;
    use ToxiProxyTrait;

    protected TestLogger $logger;

    protected Temp $dataDir;

    protected const TOXIPROXY_HOST = 'toxiproxy';

    public function setUp(): void
    {
        parent::setUp();
        $this->logger = new TestLogger();
        $this->dataDir = new Temp();
        $this->toxiproxy = new Toxiproxy('http://toxiproxy:8474');
        $this->pdoConnection = $this->createPdoConnection();
        $this->dropAllTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clear all proxies
        $this->clearAllProxies();

//        $this->dropAllTables();
    }

    /**
     * @param array{
     *     data_dir?: string,
     *     tableId?: string,
     *     dbName?: string,
     *     writer_class?: string,
     *     export?: bool,
     *     items?: array<int, array<string, mixed>>,
     *     primaryKey?: ?array<int, string>,
     *     db?: array<string, string>
     * } $data
     * @throws UserException
     */
    protected function createExportConfig(array $data, ?array $inputMapping = null): ExportConfig
    {
        $data['data_dir'] = $data['data_dir'] ?? $this->dataDir->getTmpFolder();
        $data['tableId'] = $data['tableId'] ?? 'simple';
        $data['dbName'] = $data['dbName'] ?? 'simple';
        $data['writer_class'] = $data['writer_class'] ?? 'MariaDb';
        $data['export'] = $data['export'] ?? true;
        $data['items'] = $data['items'] ?? [
            [
                'name' => 'id',
                'dbName' => 'id',
                'type' => 'int',
                'size' => null,
                'nullable' => false,
            ],
            [
                'name' => 'name',
                'dbName' => 'name',
                'type' => 'varchar',
                'size' => '255',
                'nullable' => true,
            ],
        ];
        $data['primaryKey'] = $data['primaryKey'] ?? ['id'];
        $data['db'] = $data['db'] ?? $this->getDbConfig();

        if (!$inputMapping) {
            $inputMapping = $this->createInputMapping($data['dbName']);
        }

        return ExportConfig::fromArray($data, $inputMapping);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function createInputMapping(string $dbName): array
    {
        return [
            [
                'source' => $dbName,
                'destination' => $dbName . '.csv',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getDbConfig(): array
    {
        return [
            'host' => (string) getenv('DB_HOST'),
            'port' => (string) getenv('DB_PORT'),
            'user' => (string) getenv('DB_USER'),
            '#password' => (string) getenv('DB_PASSWORD'),
            'database' => (string) getenv('DB_DATABASE'),
        ];
    }

    /**
     * @param array<int<0, max>, array<string, mixed>> $fromData
     * @return array<int<0, max>, array<string, mixed>>
     * @throws \Keboola\Csv\Exception|Exception
     */
    protected function generateDataFile(
        ExportConfig $exportConfig,
        int $rowsCount = 10,
        array $fromData = [],
    ): array {
        $fs = new Filesystem();
        if (!$fs->exists($exportConfig->getDataDir() . '/in/tables')) {
            $fs->mkdir($exportConfig->getDataDir() . '/in/tables');
        }
        $file = new CsvWriter($exportConfig->getDataDir() . '/in/tables/' . $exportConfig->getDbName() . '.csv');

        // header
        $dataRow = [];
        foreach ($exportConfig->getItems() as $item) {
            $dataRow[] = $item->getDbName();
        }
        $file->writeRow($dataRow);

        // data
        $data = [];
        for ($i = 0; $i < $rowsCount; $i++) {
            $dataRow = [];
            foreach ($exportConfig->getItems() as $item) {
                $dataRow[$item->getDbName()] = $this->generateItemValue($item);
            }
            $data[] = $dataRow;
            $file->writeRow($dataRow);
        }

        foreach ($fromData as $fromDataRow) {
            $file->writeRow($fromDataRow);
            $data[] = $fromDataRow;
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    private function generateItemValue(ItemConfig $item): string|int|bool|float
    {
        return match ($item->getType()) {
            'int' => rand(0, 10000),
            'varchar' => uniqid('varchar_', true),
            'decimal' => (float) rand(0, 100) / 100,
            'date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'bool' => rand(0, 1) === 1,
            default => throw new Exception(sprintf('Unknown type "%s".', $item->getType())),
        };
    }
}
