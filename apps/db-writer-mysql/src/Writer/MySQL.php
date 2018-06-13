<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Writer;

use Keboola\Csv\CsvFile;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Writer;
use Keboola\DbWriter\WriterInterface;
use Keboola\Temp\Temp;

class MySQL extends Writer implements WriterInterface
{
    /** @var array */
    private static $allowedTypes = [
        'int', 'smallint', 'bigint',
        'decimal', 'float', 'double',
        'date', 'datetime', 'timestamp',
        'char', 'varchar', 'text', 'blob',
    ];


    /** @var \PDO */
    protected $db;

    /** @var string */
    private $charset = 'utf8mb4';

    public function generateTmpName(string $tableName): string
    {
        $tmpId = '_temp_' . uniqid();
        return mb_substr($tableName, 0, 30 - mb_strlen($tmpId)) . $tmpId;
    }

    public function createConnection(array $dbParams): \PDO
    {
        $isSsl = false;

        // convert errors to PDOExceptions
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
        ];

        if (!isset($dbParams['password']) && isset($dbParams['#password'])) {
            $dbParams['password'] = $dbParams['#password'];
        }

        // check params
        foreach (['host', 'database', 'user', 'password'] as $r) {
            if (!isset($dbParams[$r])) {
                throw new UserException(sprintf("Parameter %s is missing.", $r));
            }
        }

        // ssl encryption
        if (!empty($dbParams['ssl']) && !empty($dbParams['ssl']['enabled'])) {
            $ssl = $dbParams['ssl'];

            $temp = new Temp('wr-db-mysql');

            if (!empty($ssl['key'])) {
                $options[\PDO::MYSQL_ATTR_SSL_KEY] = $this->createSSLFile($ssl['key'], $temp);
                $isSsl = true;
            }
            if (!empty($ssl['cert'])) {
                $options[\PDO::MYSQL_ATTR_SSL_CERT] = $this->createSSLFile($ssl['cert'], $temp);
                $isSsl = true;
            }
            if (!empty($ssl['ca'])) {
                $options[\PDO::MYSQL_ATTR_SSL_CA] = $this->createSSLFile($ssl['ca'], $temp);
                $isSsl = true;
            }
            if (!empty($ssl['cipher'])) {
                $options[\PDO::MYSQL_ATTR_SSL_CIPHER] = $ssl['cipher'];
            }
        }

        $port = isset($dbParams['port']) ? $dbParams['port'] : '3306';

        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s",
            $dbParams['host'],
            $port,
            $dbParams['database']
        );

        $this->logger->info("Connecting to DSN '" . $dsn . "' " . ($isSsl ? 'Using SSL' : ''));

        $pdo = new \PDO($dsn, $dbParams['user'], $dbParams['password'], $options);
        $pdo->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        try {
            $pdo->exec("SET NAMES $this->charset;");
        } catch (\PDOException $e) {
            $this->charset = 'utf8';
            $this->logger->info('Falling back to ' . $this->charset . ' charset');
            $pdo->exec("SET NAMES $this->charset;");
        }

        if ($isSsl) {
            $status = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher';")->fetch(\PDO::FETCH_ASSOC);

            if (empty($status['Value'])) {
                throw new UserException(sprintf("Connection is not encrypted"));
            } else {
                $this->logger->info("Using SSL cipher: " . $status['Value']);
            }
        }

        return $pdo;
    }

    public function write(CsvFile $csv, array $table): void
    {
        $header = $csv->getHeader();
        $columnNames = $this->columnNamesForLoad($table, $header);
        $csv->rewind();

        $query = "
            LOAD DATA LOCAL INFILE '{$csv}'
            INTO TABLE {$this->escape($table['dbName'])}
            CHARACTER SET $this->charset
            FIELDS TERMINATED BY ','            
            OPTIONALLY ENCLOSED BY '\"'
            ESCAPED BY ''
            IGNORE 1 LINES
            (". implode(', ', $columnNames) . ")
            " . $this->emptyToDefault($table)
        ;

        try {
            $this->db->exec($query);
        } catch (\PDOException $e) {
            throw new UserException("Query failed: " . $e->getMessage(), 400, $e, [
                'query' => $query,
            ]);
        }
    }

    protected function emptyToDefault(array $table): string
    {
        $defaultCols = array_filter($table['items'], function ($column) {
            return !empty($column['default']) && strtolower($column['type']) !== 'ignore';
        });

        if (empty($defaultCols)) {
            return '';
        }

        $defaultExpression = array_map(function ($column) {
            return sprintf(
                "%s = IF(%s = '', '%s', %s)",
                $this->escape($column['dbName']),
                $this->escape($column['dbName']),
                $column['default'],
                $this->escape($column['dbName'])
            );
        }, $defaultCols);
        return 'SET ' . implode(',', $defaultExpression);
    }

    protected function columnNamesForLoad(array $table, array $header): array
    {
        return array_map(function ($column) use ($table) {
            // skip ignored
            foreach ($table['items'] as $tableColumn) {
                if ($tableColumn['name'] === $column && $tableColumn['type'] === 'IGNORE') {
                    return '@dummy';
                }
            }

            // name by mapping
            foreach ($table['items'] as $tableColumn) {
                if ($tableColumn['name'] === $column) {
                    return $this->escape($tableColumn['dbName']);
                }
            }

            // origin sapi name
            return $this->escape($column);
        }, $header);
    }

    public function drop(string $tableName): void
    {
        $this->db->exec(sprintf("DROP TABLE IF EXISTS %s;", $this->escape($tableName)));
    }

    private function escape(string $obj): string
    {
        return "`{$obj}`";
    }

    public function create(array $table): void
    {
        $sql = "CREATE TABLE " . $this->escape($table['dbName']) . " (";

        $columns = $table['items'];
        foreach ($columns as $k => $col) {
            $type = strtoupper($col['type']);
            if ($type == 'IGNORE') {
                continue;
            }

            if (!empty($col['size'])) {
                $type .= "({$col['size']})";
            }

            $null = $col['nullable'] ? 'NULL' : 'NOT NULL';

            $default = empty($col['default']) ? '' : "DEFAULT '" . $col['default'] . "'";
            if ($type == 'TEXT') {
                $default = '';
            }

            $sql .= $this->escape($col['dbName']) . " $type $null $default";
            $sql .= ',';
        }

        if (!empty($table['primaryKey'])) {
            $writer = $this;
            $sql .= "PRIMARY KEY (" . implode(
                ', ',
                array_map(
                    function ($primaryColumn) use ($writer) {
                        return $writer->escape($primaryColumn);
                    },
                    $table['primaryKey']
                )
            ) . ")";

            $sql .= ',';
        }


        $sql = substr($sql, 0, -1);
        $sql .= ") DEFAULT CHARSET=$this->charset COLLATE {$this->charset}_unicode_ci";

        $this->db->exec($sql);
    }

    public static function getAllowedTypes(): array
    {
        return self::$allowedTypes;
    }

    public function upsert(array $table, string $targetTable): void
    {
        $this->logger->info('Upserting table "' . $table['dbName'] . '".');

        $columns = array_filter($table['items'], function ($item) {
            return $item['type'] !== 'IGNORE';
        });

        $dbColumns = array_map(function ($item) {
            return $this->escape($item['dbName']);
        }, $columns);

        if (!empty($table['primaryKey'])) {
            $this->upsertWithPK($table, $targetTable, $dbColumns);
            return;
        }

        $this->upsertWithoutPK($table, $targetTable, $dbColumns);
    }

    private function upsertWithPK(array $tableConfig, string $targetTable, array $dbColumns): void
    {
        // check primary keys
        $this->checkKeys($tableConfig['primaryKey'], $targetTable);

        // update data
        $tempTableName = $this->escape($tableConfig['dbName']);
        $targetTableName = $this->escape($targetTable);

        $valuesClauseArr = [];
        foreach ($dbColumns as $index => $column) {
            $valuesClauseArr[] = "{$targetTableName}.{$column}={$tempTableName}.{$column}";
        }
        $valuesClause = implode(',', $valuesClauseArr);
        $columnsClause = implode(',', $dbColumns);

        $query = "
          INSERT INTO {$targetTableName} ({$columnsClause})
          SELECT * FROM {$tempTableName}
          ON DUPLICATE KEY UPDATE
          {$valuesClause}
        ";

        $this->db->exec($query);

        // drop temp table
        $this->drop($tableConfig['dbName']);
        $this->logger->info('Table "' . $tableConfig['dbName'] . '" upserted.');
    }

    private function upsertWithoutPK(array $tableConfig, string $targetTable, array $dbColumns): void
    {
        $columnsClause = implode(',', $dbColumns);

        // insert new data
        $this->db->exec("
          INSERT INTO {$this->escape($targetTable)} ({$columnsClause})
          SELECT * FROM {$this->escape($tableConfig['dbName'])}
        ");

        // drop temp table
        $this->drop($tableConfig['dbName']);
        $this->logger->info('Table "' . $tableConfig['dbName'] . '" upserted.');
    }

    private function getKeysFromDbTable(string $tableName, string $keyName = 'PRIMARY'): array
    {
        $stmt = $this->db->query("SHOW KEYS FROM {$this->escape($tableName)} WHERE Key_name = '{$keyName}'");
        $result = $stmt->fetchAll();

        return array_map(function ($item) {
            return $item['Column_name'];
        }, $result);
    }

    public function checkKeys(array $configKeys, string $targetTable): void
    {
        $primaryKeysInDb = $this->getKeysFromDbTable($targetTable);
        sort($primaryKeysInDb);
        sort($configKeys);

        if ($primaryKeysInDb != $configKeys) {
            throw new UserException(sprintf(
                'Primary key(s) in configuration does NOT match with keys in DB table.' . PHP_EOL
                . 'Keys in configuration: %s' . PHP_EOL
                . 'Keys in DB table: %s',
                implode(',', $configKeys),
                implode(',', $primaryKeysInDb)
            ));
        }
    }

    private function createSSLFile(string $sslCa, Temp $temp): string
    {
        $fileInfo = $temp->createTmpFile('ssl');
        file_put_contents($fileInfo->getPathname(), $sslCa);
        return $fileInfo->getPathname();
    }

    public function testConnection(): void
    {
        $this->db->query('SELECT NOW();')->execute();
    }

    public function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare('SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?;');
        $stmt->execute([$tableName]);

        $res = $stmt->fetchAll();
        return !empty($res);
    }

    public function showTables(string $dbName): array
    {
        $stmt = $this->db->query("SHOW TABLES;");
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($item) {
            return array_shift($item);
        }, $res);
    }

    public function getTableInfo(string $tableName): array
    {
        $stmt = $this->db->query(sprintf("DESCRIBE %s;", $this->escape($tableName)));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function validateTable(array $tableConfig): void
    {
        // TODO: Implement validateTable() method.
    }
}
