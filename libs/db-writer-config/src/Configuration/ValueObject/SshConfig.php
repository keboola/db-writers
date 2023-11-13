<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;

readonly class SshConfig
{
    /**
     * @param $config array{
     *     'enabled': bool,
     *     'keys': array,
     *     'sshHost': string,
     *     'sshPort'?: int,
     *     'remoteHost'?: string,
     *     'remotePort'?: int,
     *     'localPort'?: int,
     *     'user'?: string
     * }
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['enabled'],
            $config['keys']['#private'],
            $config['keys']['public'],
            $config['sshHost'],
            $config['sshPort'] ?? null,
            $config['remoteHost'] ?? null,
            $config['remotePort'] ?? null,
            $config['localPort'] ?? null,
            $config['user'] ?? null,
        );
    }

    public function __construct(
        private bool $enabled,
        private string $privateKey,
        private string $publicKey,
        private string $sshHost,
        private ?int $sshPort,
        private ?string $remoteHost,
        private ?string $remotePort,
        private ?string $localPort,
        private ?string $user,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function getSshHost(): string
    {
        return $this->sshHost;
    }

    public function getSshPort(): int
    {
        if ($this->sshPort === null) {
            throw new PropertyNotSetException('SSH port is not set.');
        }
        return $this->sshPort;
    }

    public function getRemoteHost(): string
    {
        if ($this->remoteHost === null) {
            throw new PropertyNotSetException('Remote host is not set.');
        }
        return $this->remoteHost;
    }

    public function getRemotePort(): string
    {
        if ($this->remotePort === null) {
            throw new PropertyNotSetException('Remote port is not set.');
        }
        return $this->remotePort;
    }

    public function getLocalPort(): string
    {
        if ($this->localPort === null) {
            throw new PropertyNotSetException('Local port is not set.');
        }
        return $this->localPort;
    }

    public function getUser(): string
    {
        if ($this->user === null) {
            throw new PropertyNotSetException('User is not set.');
        }
        return $this->user;
    }

    /**
     * @return array{
     *     'enabled': bool,
     *     'keys': array{
     *         "#private": string,
     *         'public': string
     *     },
     *     'sshHost': string,
     *     'sshPort': ?int,
     *     'remoteHost': ?string,
     *     'remotePort': ?string,
     *     'localPort': ?string,
     *     'user': ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'keys' => [
                '#private' => $this->privateKey,
                'public' => $this->publicKey,
            ],
            'sshHost' => $this->sshHost,
            'sshPort' => $this->sshPort,
            'remoteHost' => $this->remoteHost,
            'remotePort' => $this->remotePort,
            'localPort' => $this->localPort,
            'user' => $this->user,
        ];
    }
}
