<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

use Keboola\Component\BaseComponent;
use Keboola\DbWriter\Exception\ApplicationException;
use Keboola\DbWriter\Exception\InvalidDatabaseHostException;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbWriterConfig\Configuration\ValueObject\ExportConfig;
use Psr\Log\LoggerInterface;
use Throwable;

class Application extends BaseComponent
{
    /**
     * @throws InvalidDatabaseHostException
     */
    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->checkDatabaseHost();
    }

    /**
     * @throws UserException|ApplicationException
     */
    public function run(): void
    {
        $parameters = $this->getConfig()->getParameters();
        $writerFactory = new WriterFactory($this->getConfig());
        $writer = $writerFactory->create($this->getLogger());

        if (!$this->isRowConfiguration($parameters)) {
            $filteredTables = array_filter($parameters['tables'], fn($table) => $table['export']);
            foreach ($filteredTables as $filteredTable) {
                $filteredTable = $this->validateTableItems($filteredTable);
                $writer->write($this->createExportConfig($filteredTable));
            }
        } else {
            $parameters = $this->validateTableItems($parameters);
            $writer->write($this->createExportConfig($parameters));
        }
    }

    /**
     * @throws UserException
     */
    public function testConnectionAction(): string
    {
        $writerFactory = new WriterFactory($this->getConfig());
        $writer = $writerFactory->create($this->getLogger());
        try {
            $writer->testConnection();
        } catch (Throwable $e) {
            throw new UserException(sprintf("Connection failed: '%s'", $e->getMessage()), 0, $e);
        }

        return json_encode([
            'status' => 'success',
        ]);
    }

    /**
     * @throws UserException
     */
    public function getTablesInfoAction(): string
    {
        $writerFactory = new WriterFactory($this->getConfig());
        $writer = $writerFactory->create($this->getLogger());

        $tables = $writer->showTables(DatabaseConfig::fromArray($this->getConfig()->getParameters()['db']));

        $tablesInfo = [];
        foreach ($tables as $tableName) {
            $tablesInfo[$tableName] = $writer->getTableInfo($tableName);
        }

        return json_encode([
            'status' => 'success',
            'tables' => $tablesInfo,
        ]);
    }

    protected function createExportConfig(array $table): ExportConfig
    {
        return ExportConfig::fromArray($table);
    }

    protected function getSyncActions(): array
    {
        return [
            'testConnection' => 'testConnectionAction',
            'getTablesInfo' => 'getTablesInfoAction',
        ];
    }

    protected function getValidator(): Validator
    {
        return new Validator($this->getLogger(), $this->getConfig());
    }

    private function getInputTablePath(string $tableId): string
    {
        $inputMapping = $this->getConfig()->getInputTables();
        if (!$inputMapping) {
            throw new ApplicationException('Missing storage input mapping.');
        }

        $filteredStorageInputMapping = array_filter($inputMapping, function ($v) use ($tableId) {
            return $v['source'] === $tableId;
        });

        if (count($filteredStorageInputMapping) === 0) {
            throw new UserException(
                sprintf('Table "%s" in storage input mapping cannot be found.', $tableId)
            );
        }

        $tableFromInputMapping = current($filteredStorageInputMapping);

        return sprintf(
            '%s/in/tables/%s',
            $this->getDataDir(),
            $tableFromInputMapping['destination']
        );
    }

    /**
     * @throws InvalidDatabaseHostException
     */
    private function checkDatabaseHost(): void
    {
        $checker = $this->getValidator();
        $checker->validateDatabaseHost();
    }

    private function isRowConfiguration(array $parameters): bool
    {
        return !isset($parameters['tables']);
    }

    /**
     * @throws UserException|ApplicationException
     */
    private function validateTableItems(array $table): array
    {
        $validator = $this->getValidator();
        return $validator->validateTableItems($this->getInputTablePath($table['tableId']), $table['items']);
    }
}
