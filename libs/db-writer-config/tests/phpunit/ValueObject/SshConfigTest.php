<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests\ValueObject;

use Keboola\DbWriterConfig\Configuration\ValueObject\SshConfig;
use PHPUnit\Framework\Assert;
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
            'remotePort' => '3306',
            'localPort' => '3306',
            'user' => 'user',
        ];

        $sshConfig = SshConfig::fromArray($config);

        Assert::assertTrue($sshConfig->isEnabled());
        Assert::assertEquals('private', $sshConfig->getPrivateKey());
        Assert::assertEquals('public', $sshConfig->getPublicKey());
        Assert::assertEquals('sshHost', $sshConfig->getSshHost());
        Assert::assertEquals(22, $sshConfig->getSshPort());
        Assert::assertEquals('remoteHost', $sshConfig->getRemoteHost());
        Assert::assertEquals(3306, $sshConfig->getRemotePort());
        Assert::assertEquals(3306, $sshConfig->getLocalPort());
        Assert::assertEquals('user', $sshConfig->getUser());
        Assert::assertEquals(
            $config,
            $sshConfig->toArray(),
        );
    }
}
