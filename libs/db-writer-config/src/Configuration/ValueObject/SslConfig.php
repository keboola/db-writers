<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\ValueObject;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;

readonly class SslConfig
{
    /**
     * @param $config array{
     *     'ca'?: string,
     *     'cert'?: string,
     *     "#key"?: string,
     *     'cipher'?: string,
     *     'verifyServerCert'?: string,
     *     'ignoreCertificateCn'?: string,
     * }
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['ca'] ?? null,
            $config['cert'] ?? null,
            $config['#key'] ?? null,
            $config['cipher'] ?? null,
            $config['verifyServerCert'] ?? true,
            $config['ignoreCertificateCn'] ?? null,
        );
    }

    public function __construct(
        private ?string $ca,
        private ?string $cert,
        private ?string $key,
        private ?string $cipher,
        private bool $verifyServerCert,
        private ?bool $ignoreCertificateCn,
    ) {
    }

    public function hasCa(): bool
    {
        return $this->ca !== null;
    }

    public function hasCert(): bool
    {
        return $this->cert !== null;
    }

    public function hasKey(): bool
    {
        return $this->key !== null;
    }

    public function hasCipher(): bool
    {
        return $this->cipher !== null;
    }

    public function hasVerifyServerCert(): bool
    {
        return $this->verifyServerCert !== null;
    }

    public function hasIgnoreCertificateCn(): bool
    {
        return $this->ignoreCertificateCn !== null;
    }

    public function getCa(): string
    {
        if ($this->ca === null) {
            throw new PropertyNotSetException('SSL ca is not set.');
        }
        return $this->ca;
    }

    public function getCert(): string
    {
        if ($this->cert === null) {
            throw new PropertyNotSetException('SSL cert is not set.');
        }
        return $this->cert;
    }

    public function getKey(): string
    {
        if ($this->key === null) {
            throw new PropertyNotSetException('SSL key is not set.');
        }
        return $this->key;
    }

    public function getCipher(): string
    {
        if ($this->cipher === null) {
            throw new PropertyNotSetException('SSL cipher is not set.');
        }
        return $this->cipher;
    }

    public function getVerifyServerCert(): bool
    {
        return $this->verifyServerCert;
    }

    public function getIgnoreCertificateCn(): bool
    {
        if ($this->ignoreCertificateCn === null) {
            throw new PropertyNotSetException('SSL ignoreCertificateCn is not set.');
        }
        return $this->ignoreCertificateCn;
    }
}
