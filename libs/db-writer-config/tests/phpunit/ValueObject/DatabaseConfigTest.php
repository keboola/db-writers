<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests\ValueObject;

use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class DatabaseConfigTest extends TestCase
{
    public function testExportConfig(): void
    {
        $config = [
            'host' => 'testHost.local',
            'port' => '12345',
            'user' => 'username',
            '#password' => 'secretPassword',
            'database' => 'database',
            'schema' => 'schema',
        ];

        $exportDatabaseConfig = DatabaseConfig::fromArray($config);

        Assert::assertTrue($exportDatabaseConfig->hasPort());
        Assert::assertTrue($exportDatabaseConfig->hasSchema());

        Assert::assertEquals('testHost.local', $exportDatabaseConfig->getHost());
        Assert::assertEquals('12345', $exportDatabaseConfig->getPort());
        Assert::assertEquals('secretPassword', $exportDatabaseConfig->getPassword());
        Assert::assertEquals('database', $exportDatabaseConfig->getDatabase());
        Assert::assertEquals('schema', $exportDatabaseConfig->getSchema());
    }

    public function testNotEnabledSshConnection(): void
    {
        $config = [
            'host' => 'testHost.local',
            'database' => 'database',
            'user' => 'username',
            'ssh' => [
                'enabled' => false,
                'keys' => [
                    '#private' => 'privateKey',
                    'public' => 'publicKey',
                ],
                'sshHost' => 'sshHost',
                'sshPort' => 12345,
                'remoteHost' => 'remoteHost',
                'remotePort' => 12345,
                'localPort' => 12345,
                'user' => 'sshUser',
            ],
        ];

        $exportDatabaseConfig = DatabaseConfig::fromArray($config);
        Assert::assertFalse($exportDatabaseConfig->hasSshConfig());
    }

    public function testOnlyRequiredProperties(): void
    {
        $config = [
            'database' => 'database',
            'user' => 'username',
        ];

        $exportDatabaseConfig = DatabaseConfig::fromArray($config);

        Assert::assertFalse($exportDatabaseConfig->hasPort());
        Assert::assertFalse($exportDatabaseConfig->hasHost());
        Assert::assertFalse($exportDatabaseConfig->hasSchema());
        Assert::assertFalse($exportDatabaseConfig->hasPassword());
        Assert::assertFalse($exportDatabaseConfig->hasSshConfig());

        try {
            $exportDatabaseConfig->getHost();
            Assert::fail('Property "host" is exists.');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "host" is not set.', $e->getMessage());
        }

        try {
            $exportDatabaseConfig->getPort();
            Assert::fail('Property "port" is exists.');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "port" is not set.', $e->getMessage());
        }

        try {
            $exportDatabaseConfig->getPassword();
            Assert::fail('Property "password" is exists.');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "password" is not set.', $e->getMessage());
        }

        try {
            $exportDatabaseConfig->getSchema();
            Assert::fail('Property "schema" is exists.');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "schema" is not set.', $e->getMessage());
        }

        try {
            $exportDatabaseConfig->getSshConfig();
            Assert::fail('Property "sshConfig" is exists.');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "sshConfig" is not set.', $e->getMessage());
        }

        Assert::assertEquals('database', $exportDatabaseConfig->getDatabase());
        Assert::assertEquals('username', $exportDatabaseConfig->getUser());
        Assert::assertEquals(
            [
                'database' => 'database',
                'user' => 'username',
                'host' => null,
                'port' => null,
                '#password' => null,
                'schema' => null,
                'ssh' => null,

            ],
            $exportDatabaseConfig->toArray(),
        );
    }
}
