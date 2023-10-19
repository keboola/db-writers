<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

readonly class ItemConfig
{
    /**
     * @param $config array{
     *     name: string,
     *     dbName: string,
     *     type: string,
     *     size?: string,
     *     nullable?: string,
     *     default?: string,
     * }
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['name'],
            $config['dbName'],
            $config['type'],
            $config['size'] ?? null,
            $config['nullable'] ?? null,
            $config['default'] ?? null,
        );
    }

    public function __construct(
        private string $name,
        private string $dbName,
        private string $type,
        private ?string $size,
        private ?string $nullable,
        private ?string $default,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function getNullable(): ?string
    {
        return $this->nullable;
    }

    public function getDefault(): ?string
    {
        return $this->default;
    }
}
