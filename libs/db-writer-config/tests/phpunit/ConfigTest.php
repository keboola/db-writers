<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests;

use Keboola\DbWriterConfig\Config;
use Keboola\DbWriterConfig\Configuration\ActionConfigDefinition;
use Keboola\DbWriterConfig\Configuration\ConfigDefinition;
use Keboola\DbWriterConfig\Configuration\ConfigRowDefinition;
use Keboola\DbWriterConfig\Exception\UserException as ConfigUserException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public const DRIVER = 'config';

    public function testConfig(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => $this->getDbConfigurationArray(),
                'tables' => [
                    [
                        'tableId' => 'tableColumns',
                        'export' => true,
                        'dbName' => 'tableColumns',
                        'incremental' => false,
                        'primaryKey' => [],
                    ],
                ],
            ],
        ];
        $config = new Config($configurationArray, new ConfigDefinition());

        $expected = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => $this->getExpectedDbConfigArray(),
                'tables' => [
                    [
                        'tableId' => 'tableColumns',
                        'export' => true,
                        'dbName' => 'tableColumns',
                        'incremental' => false,
                        'primaryKey' => [],
                        'items' => [],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expected, $config->getData());
    }

    public function testConfigRow(): void
    {
        $configurationArray = [
            'parameters' => [
                'incremental' => true,
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => $this->getDbConfigurationArray(),
                'tableId' => 'tableColumns',
                'dbName' => 'tableColumns',
                'items' => [
                    [
                        'name' => 'name',
                        'dbName' => 'dbName',
                        'type' => 'type',
                        'size' => 123,
                        'nullable' => true,
                        'default' => 'default',
                    ],
                    [
                        'name' => 'name1',
                        'dbName' => 'dbName2',
                        'type' => 'type',
                        'size' => '456',
                        'nullable' => true,
                        'default' => 'default',
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ConfigRowDefinition());

        $expected = [
            'parameters' => [
                'incremental' => true,
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => $this->getExpectedDbConfigArray(),
                // Default values:
                'tableId' => 'tableColumns',
                'dbName' => 'tableColumns',
                'export' => true,
                'primaryKey' => [],
                'items' => [
                    [
                        'name' => 'name',
                        'dbName' => 'dbName',
                        'type' => 'type',
                        'size' => '123',
                        'nullable' => true,
                        'default' => 'default',
                    ],
                    [
                        'name' => 'name1',
                        'dbName' => 'dbName2',
                        'type' => 'type',
                        'size' => '456',
                        'nullable' => true,
                        'default' => 'default',
                    ],
                ],

            ],
        ];
        $this->assertEquals($expected, $config->getData());
    }

    public function testConfigActionRow(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testConfigWithSshTunnel(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                    'ssh' => [
                        'user' => 'root',
                        'sshHost' => 'sshproxy',
                        'sshPort' => '22',
                        'localPort' => '33306',
                        'keys' => ['public' => 'anyKey'],
                        'maxRetries' => 10,
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }



    public function testIntValues(): void
    {
        $config = [
            'parameters' => [
                'data_dir' => 'data_dir',
                'writer_class' => 'writer_class',
                'db' => [
                    'host' => 'host',
                    'port' => 12345, // <<< INT
                    'database' => 'database',
                    'user' => 'user',
                    '#password' => 'password',
                    'ssh' => [
                        'enabled' => true,
                        'keys' => [
                            '#private' => 'private',
                            'public' => 'public',
                        ],
                        'sshHost' => 'sshHost',
                        'sshPort' => 22, // <<< INT
                    ],
                ],
                'tableId' => 'tableId',
                'dbName' => 'dbName',
                'incremental' => true,
                'export' => false,
                'primaryKey' => ['pk1', 'pk2'],
                'items' => [
                    [
                        'name' => 'nameValue',
                        'dbName' => 'dbNameValues',
                        'type' => 'typeValue',
                        'size' => 12345, // <<< INT
                        'nullable' => true,
                        'default' => 'defaultValue',
                    ],
                ],
            ],
        ];

        $config = new Config($config, new ConfigRowDefinition());

        self::assertTrue(is_string($config->getParameters()['db']['port']));
        self::assertTrue(is_string($config->getParameters()['db']['ssh']['sshPort']));
        self::assertTrue(is_string($config->getParameters()['items'][0]['size']));
    }

    public function testStringValues(): void
    {
        $config = [
            'parameters' => [
                'data_dir' => 'data_dir',
                'writer_class' => 'writer_class',
                'db' => [
                    'host' => 'host',
                    'port' => '12345', // <<< STRING
                    'database' => 'database',
                    'user' => 'user',
                    '#password' => 'password',
                    'ssh' => [
                        'enabled' => true,
                        'keys' => [
                            '#private' => 'private',
                            'public' => 'public',
                        ],
                        'sshHost' => 'sshHost',
                        'sshPort' => '22', // <<< STRING
                    ],
                ],
                'tableId' => 'tableId',
                'dbName' => 'dbName',
                'incremental' => true,
                'export' => false,
                'primaryKey' => ['pk1', 'pk2'],
                'items' => [
                    [
                        'name' => 'nameValue',
                        'dbName' => 'dbNameValues',
                        'type' => 'typeValue',
                        'size' => '12345', // <<< STRING
                        'nullable' => true,
                        'default' => 'defaultValue',
                    ],
                ],
            ],
        ];

        $config = new Config($config, new ConfigRowDefinition());

        self::assertTrue(is_string($config->getParameters()['db']['port']));
        self::assertTrue(is_string($config->getParameters()['db']['ssh']['sshPort']));
        self::assertTrue(is_string($config->getParameters()['items'][0]['size']));
    }

    public function testConfigWithSshTunnelDisabled(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                    'ssh' => [
                        'enabled' => false,
                        'sshHost' => 'sshproxy',
                        'sshPort' => '22',
                        'localPort' => '33306',
                        'keys' => ['public' => 'anyKey'],
                        'maxRetries' => 10,
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testConfigWithSshTunnelEnabled(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                    'ssh' => [
                        'enabled' => true,
                        'user' => 'root',
                        'sshHost' => 'sshproxy',
                        'sshPort' => '22',
                        'localPort' => '33306',
                        'keys' => ['public' => 'anyKey', '#private' => 'anyKey'],
                        'maxRetries' => 10,
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testConfigWithSshTunnelEnabledMissingPrivateKey(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                    'ssh' => [
                        'enabled' => true,
                        'user' => 'root',
                        'sshHost' => 'sshproxy',
                        'sshPort' => '22',
                        'localPort' => '33306',
                        'keys' => ['public' => 'anyKey'],
                        'maxRetries' => 10,
                    ],
                ],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage('The child config "#private" under "root.parameters.db.ssh.keys" ' .
            'must be configured.');

        new Config($configurationArray, new ActionConfigDefinition());
    }

    public function testUnencryptedSslKey(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'ssl' => [
                        'key' => 'testKey',
                        'ca' => 'testCa',
                        'cert' => 'testCert',
                        'cipher' => 'testCipher',
                        'verifyServerCert' => false,
                    ],
                ],
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        /** @var array{'parameters': array{'db': array{'ssl': array{"#key": string}}}} $data */
        $data = $config->getData();
        $this->assertEquals('testKey', $data['parameters']['db']['ssl']['#key']);
    }

    public function testSslOnlyCa(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'ssl' => [
                        'enabled' => true,
                        'ca' => 'abs',
                    ],
                ],
            ],
        ];

        $this->expectNotToPerformAssertions();
        new Config($configurationArray, new ActionConfigDefinition());
    }

    public function testMissingSslKey(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'ssl' => [
                        'enabled' => true,
                        'cert' => 'abs',
                    ],
                ],
            ],
        ];

        $exceptionMessage =
            'Invalid configuration for path "root.parameters.db.ssl": ' .
            'Both "#key" and "cert" must be specified.';
        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testMissingSslCert(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'ssl' => [
                        'enabled' => true,
                        '#key' => 'abs',
                    ],
                ],
            ],
        ];

        $exceptionMessage =
            'Invalid configuration for path "root.parameters.db.ssl": ' .
            'Both "#key" and "cert" must be specified.';
        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testTestConfigWithExtraKeysConfigDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                ],
                'tables' => [],
                'advancedMode' => true,
            ],
        ];

        $config = new Config($configurationArray, new ConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testTestConfigWithExtraKeysConfigRowDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                ],
                'incremental' => false,
                'enabled' => true,
                'primaryKey' => [],
                'tableId' => 'tableColumns',
                'dbName' => 'tableColumns',
                'export' => true,
                'items' => [],
            ],
        ];

        $config = new Config($configurationArray, new ConfigRowDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testTestConfigWithExtraKeysActionConfigRowDefinition(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => [
                    'host' => 'mysql',
                    'user' => 'root',
                    '#password' => 'rootpassword',
                    'database' => 'test',
                    'port' => 3306,
                    'initQueries' => [],
                ],
                'advancedMode' => true,
            ],
        ];

        $config = new Config($configurationArray, new ActionConfigDefinition());
        $this->assertEquals($configurationArray, $config->getData());
    }

    public function testEmptyNameInPK(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
                'db' => $this->getDbConfigurationArray(),
                'tableId' => 'name',
                'dbName' => 'name',
                'primaryKey' => ['abc', ''],
            ],
        ];

        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage(
            'The path "root.parameters.primaryKey.1" cannot contain an empty value, but got "".',
        );
        new Config($configurationArray, new ConfigRowDefinition());
    }

    public function testMissingDbNode(): void
    {
        $configurationArray = [
            'parameters' => [
                'data_dir' => '/code/tests/Keboola/DbWriter/../../data',
                'writer_class' => 'MySQL',
            ],
        ];

        $exceptionMessage = 'The child config "db" under "root.parameters" must be configured.';
        $this->expectException(ConfigUserException::class);
        $this->expectExceptionMessage($exceptionMessage);

        new Config($configurationArray, new ConfigRowDefinition());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDbConfigurationArray(): array
    {
        return [
            'host' => 'mysql',
            'user' => 'root',
            '#password' => 'rootpassword',
            'database' => 'test',
            'port' => 3306,
            'ssl' => [
                '#key' => 'testKey',
                'ca' => 'testCa',
                'cert' => 'testCert',
                'cipher' => 'testCipher',
                'verifyServerCert' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getExpectedDbConfigArray(): array
    {
        return [
            'host' => 'mysql',
            'user' => 'root',
            '#password' => 'rootpassword',
            'database' => 'test',
            'port' => 3306,
            'ssl' => [
                '#key' => 'testKey',
                'ca' => 'testCa',
                'cert' => 'testCert',
                'cipher' => 'testCipher',
                'verifyServerCert' => false,
                'enabled' => false,
                'ignoreCertificateCn' => false,
            ],
            'initQueries' => [],
        ];
    }
}
