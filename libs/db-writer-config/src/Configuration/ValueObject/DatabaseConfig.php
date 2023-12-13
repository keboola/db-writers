<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;

readonly class DatabaseConfig
{
    public function __construct(
        private ?string $host,
        private ?string $port,
        private string $database,
        private string $user,
        private ?string $password,
        private ?string $schema,
        private ?SshConfig $sshConfig,
        private ?SslConfig $sslConfig,
    ) {
    }

    /**
     * @param $config array{
     *     host?: string,
     *     port?: string,
     *     database: string,
     *     user: string,
     *     "#password"?: string,
     *     schema?: string,
     *     ssh?: array
     *     ssl?: array
     * }
     */
    public static function fromArray(array $config): self
    {
        $sshEnabled = $config['ssh']['enabled'] ?? false;
        $sslEnabled = $config['ssl']['enabled'] ?? false;

        return new self(
            $config['host'] ?? null,
            $config['port'] ?? null,
            $config['database'],
            $config['user'],
            $config['#password'] ?? null,
            $config['schema'] ?? null,
            $sshEnabled ? SshConfig::fromArray($config['ssh']) : null,
            $sslEnabled ? SslConfig::fromArray($config['ssl']) : null,
        );
    }

    public function hasHost(): bool
    {
        return $this->host !== null;
    }

    public function hasPort(): bool
    {
        return $this->port !== null;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }

    public function hasSchema(): bool
    {
        return $this->schema !== null;
    }

    public function hasSshConfig(): bool
    {
        return $this->sshConfig !== null;
    }

    public function hasSslConfig(): bool
    {
        return $this->sslConfig !== null;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getHost(): string
    {
        if ($this->host === null) {
            throw new PropertyNotSetException('Property "host" is not set.');
        }
        return $this->host;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getPort(): string
    {
        if ($this->port === null) {
            throw new PropertyNotSetException('Property "port" is not set.');
        }
        return $this->port;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getPassword(): string
    {
        if ($this->password === null) {
            throw new PropertyNotSetException('Property "password" is not set.');
        }
        return $this->password;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getSchema(): string
    {
        if ($this->schema === null) {
            throw new PropertyNotSetException('Property "schema" is not set.');
        }
        return $this->schema;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getSshConfig(): SshConfig
    {
        if ($this->sshConfig === null) {
            throw new PropertyNotSetException('Property "sshConfig" is not set.');
        }
        return $this->sshConfig;
    }

    /**
     * @throws PropertyNotSetException
     */
    public function getSslConfig(): SslConfig
    {
        if ($this->sslConfig === null) {
            throw new PropertyNotSetException('Property "sslConfig" is not set.');
        }
        return $this->sslConfig;
    }

    /**
     * @return array{
     *     host: string|null,
     *     port: string|null,
     *     database: string,
     *     user: string,
     *     "#password": ?string,
     *     schema: string|null,
     *     ssh: array|null
     * }
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'user' => $this->user,
            '#password' => $this->password,
            'schema' => $this->schema,
            'ssh' => $this->hasSshConfig() ? $this->getSshConfig()->toArray() : null,
        ];
    }
}
