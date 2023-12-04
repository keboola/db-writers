<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Keboola\Component\UserException;
use Keboola\DbWriterAdapter\Connection\Connection;
use Keboola\DbWriterAdapter\WriteAdapter;
use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use PDOException;

class MySQL extends BaseWriter
{

    /** @var MySQLConnection $connection */
    protected Connection $connection;

    private string $charset = 'utf8mb4';

    /**
     * @throws UserException|PropertyNotSetException
     */
    protected function createConnection(DatabaseConfig $databaseConfig): Connection
    {
        $connection = MySQLConnectionFactory::create($databaseConfig, $this->logger);

        try {
            $connection->exec("SET NAMES $this->charset;");
        } catch (PDOException) {
            $this->logger->info('Falling back to ' . $this->charset . ' charset');
            $this->charset = 'utf8';
            $connection->exec("SET NAMES $this->charset;");
        }

        return $connection;
    }

    protected function createWriteAdapter(): WriteAdapter
    {
        return new MySQLWriteAdapter(
            $this->connection,
            new MySQLQueryBuilder($this->charset),
            $this->logger,
        );
    }
}
