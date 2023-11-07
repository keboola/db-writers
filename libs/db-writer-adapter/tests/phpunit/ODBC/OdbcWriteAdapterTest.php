<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\Tests\ODBC;

use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\ODBC\OdbcConnection;
use Keboola\DbWriterAdapter\ODBC\OdbcWriteAdapter;
use Keboola\DbWriterAdapter\Query\DefaultQueryBuilder;
use Keboola\DbWriterAdapter\Tests\AbstractWriteAdapterTest;
use Keboola\DbWriterAdapter\Tests\Traits\OdbcCreateConnectionTrait;
use PHPUnit\Framework\Assert;

class OdbcWriteAdapterTest extends AbstractWriteAdapterTest
{
    use OdbcCreateConnectionTrait;

    /** @var OdbcConnection $connection */
    protected Connection $connection;

    public function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createOdbcConnection();
    }

    protected function createWriteAdapter(
        ?string $host = null,
        ?int $port = null,
    ): OdbcWriteAdapter {
        return new OdbcWriteAdapter(
            $this->connection,
            new DefaultQueryBuilder(),
            $this->logger,
        );
    }

    public function testGetName(): void
    {
        Assert::assertSame('ODBC', $this->createWriteAdapter()->getName());
    }
}
