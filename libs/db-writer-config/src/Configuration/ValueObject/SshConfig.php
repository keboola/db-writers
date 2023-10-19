<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

readonly class SshConfig
{
    /**
     * @param $config array{
     *     'enabled': bool,
     *     'keys': array,
     *     'sshHost': string,
     *     'sshPort': int,
     *     'remoteHost': string,
     *     'remotePort': int,
     *     'localPort': int,
     *     'user': string
     * }
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['enabled'],
            $config['keys']['#private'],
            $config['keys']['public'],
            $config['sshHost'],
            $config['sshPort'],
            $config['remoteHost'],
            $config['remotePort'],
            $config['localPort'],
            $config['user'],
        );
    }

    public function __construct(
        private bool $enabled,
        private string $privateKey,
        private string $publicKey,
        private string $sshHost,
        private int $sshPort,
        private string $remoteHost,
        private int $remotePort,
        private int $localPort,
        private string $user,
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
        return $this->sshPort;
    }

    public function getRemoteHost(): string
    {
        return $this->remoteHost;
    }

    public function getRemotePort(): int
    {
        return $this->remotePort;
    }

    public function getLocalPort(): int
    {
        return $this->localPort;
    }

    public function getUser(): string
    {
        return $this->user;
    }
}
