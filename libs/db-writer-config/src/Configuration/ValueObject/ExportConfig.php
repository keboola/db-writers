<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

readonly class ExportConfig
{

    /**
     * @param $config array{
     *     data_dir: string,
     *     extractor_class: string,
     *     db: array,
     *     tableId: string,
     *     dbName: string,
     *     incremental: bool,
     *     export: bool,
     *     primary_key: string[],
     *     items: array
     * }
     *
     */
    public function fromArray(array $config): self
    {
        return new self(
            $config['data_dir'],
            $config['extractor_class'],
            DatabaseConfig::fromArray($config['db']),
            $config['tableId'],
            $config['dbName'],
            $config['incremental'],
            $config['export'],
            $config['primary_key'],
            array_map(fn($v) => ItemConfig::fromArray($v), $config['items']),
        );
    }

    /**
     * @param string[] $primaryKey
     * @param ItemConfig[] $items
     */
    public function __construct(
        private string $dataDir,
        private string $extractorClass,
        private DatabaseConfig $databaseConfig,
        private string $tableId,
        private string $dbName,
        private bool $incremental,
        private bool $export,
        private array $primaryKey,
        private array $items,
    ) {
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getExtractorClass(): string
    {
        return $this->extractorClass;
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

    /**
     * @return string[]
     */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /**
     * @return ItemConfig[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
