<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use Keboola\DbWriterConfig\Exception\UserException;

readonly class ExportConfig
{

    /**
     * @param $config array{
     *     data_dir: string,
     *     writer_class: string,
     *     db: array,
     *     tableId: string,
     *     dbName: string,
     *     incremental?: bool,
     *     export?: bool,
     *     primary_key?: string[],
     *     items: array
     * }
     * @param $inputMapping array{
     *     source: string,
     *     destination: string,
     *     columns: array<string>
     * }
     */
    public static function fromArray(array $config, array $inputMapping): self
    {
        $filteredInputMapping = array_filter($inputMapping, fn($v) => $v['source'] === $config['tableId']);
        if (count($filteredInputMapping) === 0) {
            throw new UserException(
                sprintf('Table "%s" in storage input mapping cannot be found.', $config['tableId']),
            );
        }
        $tableFilePath = sprintf(
            '%s/in/tables/%s',
            $config['data_dir'],
            current($filteredInputMapping)['destination'],
        );

        return new self(
            $config['data_dir'],
            $config['writer_class'],
            DatabaseConfig::fromArray($config['db']),
            $config['tableId'],
            $config['dbName'],
            $config['incremental'] ?? false,
            $config['export'] ?? true,
            $config['primary_key'] ?? null,
            array_map(fn($v) => ItemConfig::fromArray($v), $config['items']),
            $tableFilePath,
        );
    }

    /**
     * @param string[] $primaryKey
     * @param ItemConfig[] $items
     */
    public function __construct(
        private string $dataDir,
        private string $writerClass,
        private DatabaseConfig $databaseConfig,
        private string $tableId,
        private string $dbName,
        private bool $incremental,
        private bool $export,
        private ?array $primaryKey,
        private array $items,
        private string $tableFilePath,
    ) {
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getWriterClass(): string
    {
        return $this->writerClass;
    }

    public function getDatabaseConfig(): DatabaseConfig
    {
        return $this->databaseConfig;
    }

    public function getTableId(): string
    {
        return $this->tableId;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function isIncremental(): bool
    {
        return $this->incremental;
    }

    public function isExport(): bool
    {
        return $this->export;
    }

    public function hasPrimaryKey(): bool
    {
        return $this->primaryKey !== null;
    }

    /**
     * @return string[]
     * @throws PropertyNotSetException
     */
    public function getPrimaryKey(): array
    {
        if ($this->primaryKey === null) {
            throw new PropertyNotSetException('Property "primaryKey" is not set.');
        }
        return $this->primaryKey;
    }

    /**
     * @return ItemConfig[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTableFilePath(): string
    {
        return $this->tableFilePath;
    }
}
