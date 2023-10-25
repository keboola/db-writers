<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Tests\ValueObject;

use Keboola\DbWriterConfig\Configuration\ValueObject\ItemConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class ItemConfigTest extends TestCase
{
    public function testMinimalConfig(): void
    {
        $config = [
            'name' => 'nameValue',
            'dbName' => 'dbNameValues',
            'type' => 'typeValue',
        ];

        $itemConfig = ItemConfig::fromArray($config);

        self::assertSame($config['name'], $itemConfig->getName());
        self::assertSame($config['dbName'], $itemConfig->getDbName());
        self::assertSame($config['type'], $itemConfig->getType());

        self::assertFalse($itemConfig->hasSize());
        self::assertFalse($itemConfig->getNullable());
        self::assertFalse($itemConfig->hasDefault());

        try {
            $itemConfig->getSize();
            self::fail('Exception expected');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "size" is not set.', $e->getMessage());
        }

        try {
            $itemConfig->getDefault();
            self::fail('Exception expected');
        } catch (PropertyNotSetException $e) {
            Assert::assertEquals('Property "default" is not set.', $e->getMessage());
        }
    }

    /**
     * @throws PropertyNotSetException
     */
    public function testFullConfig(): void
    {
        $config = [
            'name' => 'nameValue',
            'dbName' => 'dbNameValues',
            'type' => 'typeValue',
            'size' => 'sizeValue',
            'nullable' => true,
            'default' => 'defaultValue',
        ];

        $itemConfig = ItemConfig::fromArray($config);

        self::assertSame($config['name'], $itemConfig->getName());
        self::assertSame($config['dbName'], $itemConfig->getDbName());
        self::assertSame($config['type'], $itemConfig->getType());
        self::assertSame($config['size'], $itemConfig->getSize());
        self::assertSame($config['nullable'], $itemConfig->getNullable());
        self::assertSame($config['default'], $itemConfig->getDefault());

        self::assertTrue($itemConfig->hasSize());
        self::assertTrue($itemConfig->getNullable());
        self::assertTrue($itemConfig->hasDefault());
    }
}
