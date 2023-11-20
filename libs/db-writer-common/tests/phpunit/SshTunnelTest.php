<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use Keboola\DbWriter\TestsTraits\CloseSshTunnelsTrait;
use Keboola\DbWriter\Writer\SshTunnel;
use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;

class SshTunnelTest extends TestCase
{
    use CloseSshTunnelsTrait;

    public function tearDown(): void
    {
        parent::tearDown();
        $this->closeSshTunnels();
    }

    public function testOpenTunnel(): void
    {
        $logger = new TestLogger();

        $sshTunnel = new SshTunnel($logger);

        $databaseConfig = DatabaseConfig::fromArray(self::getDatabaseConfig());

        $newConfig = $sshTunnel->createSshTunnel($databaseConfig);

        self::assertEquals('127.0.0.1', $newConfig->getHost());
        self::assertEquals('33006', $newConfig->getPort());
    }

    private static function getDatabaseConfig(): array
    {
        return [
            'host' => (string) getenv('COMMON_DB_HOST'),
            'port' => (string) getenv('COMMON_DB_PORT'),
            'database' => (string) getenv('COMMON_DB_DATABASE'),
            'user' => (string) getenv('COMMON_DB_USER'),
            '#password' => (string) getenv('COMMON_DB_PASSWORD'),
            'schema' => (string) getenv('COMMON_DB_SCHEMA'),
            'ssh' => [
                'enabled' => true,
                'keys' => [
                    '#private' => (string) file_get_contents('/root/.ssh/id_rsa'),
                    'public' => (string) file_get_contents('/root/.ssh/id_rsa.pub'),
                ],
                'sshHost' => 'sshproxy',
            ],
        ];
    }
}
