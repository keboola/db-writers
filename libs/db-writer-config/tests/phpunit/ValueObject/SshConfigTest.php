<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests\ValueObject;

use Keboola\DbWriterConfig\Configuration\ValueObject\SshConfig;
use PHPUnit\Framework\TestCase;

class SshConfigTest extends TestCase
{
    public function testMinimalConfig(): void
    {
        $config = [
            'enabled' => true,
            'keys' => [
                '#private' => 'private',
                'public' => 'public',
            ],
            'sshHost' => 'sshHost',
            'sshPort' => 22,
            'remoteHost' => 'remoteHost',
            'remotePort' => 3306,
            'localPort' => 3306,
            'user' => 'user',
        ];

        $sshConfig = SshConfig::fromArray($config);

        self::assertTrue($sshConfig->isEnabled());
        self::assertSame('private', $sshConfig->getPrivateKey());
        self::assertSame('public', $sshConfig->getPublicKey());
        self::assertSame('sshHost', $sshConfig->getSshHost());
        self::assertSame(22, $sshConfig->getSshPort());
        self::assertSame('remoteHost', $sshConfig->getRemoteHost());
        self::assertSame(3306, $sshConfig->getRemotePort());
        self::assertSame(3306, $sshConfig->getLocalPort());
        self::assertSame('user', $sshConfig->getUser());
    }
}
