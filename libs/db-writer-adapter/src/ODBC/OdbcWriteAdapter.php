<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\ODBC;

use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

class OdbcWriteAdapter extends BaseWriteAdapter
{
    public function __construct(
        OdbcConnection $connection,
        QueryBuilder $queryBuilder,
        LoggerInterface $logger,
    ) {
        parent::__construct($connection, $queryBuilder, $logger);
    }

    public function getName(): string
    {
        return 'ODBC';
    }
}
