<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests\PDO;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\PDO\PdoConnection;
use Keboola\DbWriterAdapter\PDO\PdoWriteAdapter;
use Keboola\DbWriterAdapter\Query\DefaultQueryBuilder;
use Keboola\DbWriterAdapter\Tests\AbstractWriteAdapterTest;
use Keboola\DbWriterAdapter\Tests\Traits\PdoCreateConnectionTrait;
use PHPUnit\Framework\Assert;

class PdoWriteAdapterTest extends AbstractWriteAdapterTest
{
    use PdoCreateConnectionTrait;

    /** @var PdoConnection $connection */
    protected Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createPdoConnection();
    }

    protected function createWriteAdapter(
        ?string $host = null,
        ?int $port = null,
    ): PdoWriteAdapter {
        return new PdoWriteAdapter(
            $this->connection,
            new DefaultQueryBuilder(),
            $this->logger,
        );
    }

    public function testGetName(): void
    {
        Assert::assertSame('PDO', $this->createWriteAdapter()->getName());
    }
}
