<?php

declare(strict_types=1);

namespace Keboola\DbWriterAdapter\PDO;

use Keboola\DbWriterAdapter\BaseWriteAdapter;
use Keboola\DbWriterAdapter\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

class PdoWriteAdapter extends BaseWriteAdapter
{
    public function __construct(
        PdoConnection $connection,
        QueryBuilder $queryBuilder,
        LoggerInterface $logger,
    ) {
        parent::__construct($connection, $queryBuilder, $logger);
    }

    public function getName(): string
    {
        return 'PDO';
    }
}
