<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

readonly class DatabaseConfig
{
    public function __construct(
        private string $host,
        private int $port,
        private string $database,
        private string $user,
        private string $password,
        private string $schema,
        private SshConfig $sshConfig,
    ) {
    }

    /**
     * @param $config array{
     *     host: string,
     *     port: int,
     *     database: string,
     *     user: string,
     *     "#password": string,
     *     schema: string,
     *     ssh: array
     * }
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['host'],
            $config['port'],
            $config['database'],
            $config['user'],
            $config['#password'],
            $config['schema'],
            SshConfig::fromArray($config['ssh']),
        );
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): int
    {
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getSshConfig(): SshConfig
    {
        return $this->sshConfig;
    }
}
