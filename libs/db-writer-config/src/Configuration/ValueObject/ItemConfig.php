<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;

readonly class ItemConfig
{
    /**
     * @param $config array{
     *     name: string,
     *     dbName: string,
     *     type: string,
     *     size?: string,
     *     nullable?: bool,
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
        private ?bool $nullable,
        private ?string $default,
    ) {
    }

    public function hasSize(): bool
    {
        return !empty($this->size);
    }

    public function hasDefault(): bool
    {
        return !empty($this->default);
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

    /**
     * @throws PropertyNotSetException
     */
    public function getSize(): string
    {
        if ($this->size === null) {
            throw new PropertyNotSetException('Property "size" is not set.');
        }
        return $this->size;
    }

    public function getNullable(): bool
    {
        if (is_null($this->nullable)) {
            return false;
        }
        return $this->nullable;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getDefault(): string
    {
        if ($this->default === null) {
            throw new PropertyNotSetException('Property "default" is not set.');
        }
        return $this->default;
    }
}
