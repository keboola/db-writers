<?php
/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 12/02/16
 * Time: 16:38
 */

namespace Keboola\DbWriter\Writer;

use Keboola\Csv\CsvFile;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Writer;
use Keboola\DbWriter\WriterInterface;
use Keboola\Temp\Temp;

class MySQL extends Writer implements WriterInterface
{
	private static $allowedTypes = [
		'int', 'smallint', 'bigint',
		'decimal', 'float', 'double',
		'date', 'datetime', 'timestamp',
		'char', 'varchar', 'text', 'blob'
	];


	private static $typesWithSize = [
		'decimal', 'float',
		'datetime', 'time',
		'char', 'varchar',
	];

	private static $numericTypes = [
		'int', 'smallint', 'bigint',
		'decimal', 'float'
	];

	/** @var \PDO */
	protected $db;

	private $batched = true;
	private $charset = 'utf8mb4';

	public function generateTmpName($tableName)
	{
		$tmpId = '_temp_' . uniqid();
		return mb_substr($tableName, 0, 30 - mb_strlen($tmpId)) . $tmpId;
	}

	public function createConnection($dbParams)
	{
		$isSsl = false;

		// convert errors to PDOExceptions
		$options = [
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
			\PDO::MYSQL_ATTR_LOCAL_INFILE => true
		];

		if (!empty($dbParams['batched'])) {
			if ($dbParams['batched'] == false) {
				$this->batched = false;
			}
		}

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

			$temp = new Temp(defined('APP_NAME') ? APP_NAME : 'wr-db-mysql');

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

	function write(CsvFile $csv, array $table)
	{
        $this->logger->info('Writing table "' . $table['dbName'] . '".');
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
				'query' => $query
			]);
		}
        $this->logger->info('Table "' . $table['dbName'] . '" written.');
	}

	protected function emptyToDefault($table)
    {
        $defaultCols = array_filter($table['items'], function($column) {
            return !empty($column['default']) && strtolower($column['type']) !== 'ignore';
        });

        if (empty($defaultCols)) {
            return '';
        }

        $defaultExpression = array_map(function($column) {
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

	protected function columnNamesForLoad($table, $header)
    {
        return array_map(function($column) use ($table) {
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

	function isTableValid(array $table, $ignoreExport = false)
	{
		// TODO: Implement isTableValid() method.

		return true;
	}

	function drop($tableName)
	{
		$this->db->exec(sprintf("DROP TABLE IF EXISTS %s;", $this->escape($tableName)));
	}

	private function escape($obj)
	{
		return "`{$obj}`";
	}

	function create(array $table)
	{
        $this->logger->info('Crating table "' . $table['dbName'] . '".');
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
					function($primaryColumn) use ($writer) {
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
        $this->logger->info('Table "' . $table['dbName'] . '" created.');
	}

	static function getAllowedTypes()
	{
		return self::$allowedTypes;
	}

	public function upsert(array $table, $targetTable)
	{
        $this->logger->info('Upserting table "' . $table['dbName'] . '".');

        $columns = array_filter($table['items'], function($item) {
			return $item['type'] !== 'IGNORE';
		});

		$dbColumns = array_map(function($item) {
			return $this->escape($item['dbName']);
		}, $columns);

		if (!empty($table['primaryKey'])) {
		    $this->upsertWithPK($table, $targetTable, $dbColumns);
		    return;
		}

		$this->upsertWithoutPK($table, $targetTable, $dbColumns);
	}

	private function upsertWithPK($table, $targetTable, $dbColumns)
    {
        // check primary keys
        $this->checkKeys($table['primaryKey'], $targetTable);

        // update data
        $tempTableName = $this->escape($table['dbName']);
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
        $this->drop($table['dbName']);
        $this->logger->info('Table "' . $table['dbName'] . '" upserted.');
    }

    private function upsertWithoutPK($table, $targetTable, $dbColumns)
    {
        $columnsClause = implode(',', $dbColumns);

        // insert new data
        $this->db->exec("
          INSERT INTO {$this->escape($targetTable)} ({$columnsClause})
          SELECT * FROM {$this->escape($table['dbName'])}
        ");

        // drop temp table
        $this->drop($table['dbName']);
        $this->logger->info('Table "' . $table['dbName'] . '" upserted.');
    }

    private function getKeysFromDbTable($tableName, $keyName = 'PRIMARY')
    {
        $stmt = $this->db->query("SHOW KEYS FROM {$tableName} WHERE Key_name = '{$keyName}'");
        $result = $stmt->fetchAll();

        return array_map(function ($item) {
            return $item['Column_name'];
        }, $result);
    }

	private function createSSLFile($sslCa, Temp $temp)
	{
		$filename = $temp->createTmpFile('ssl');
		file_put_contents($filename, $sslCa);
		return realpath($filename);
	}

	public function testConnection()
	{
		$this->db->query('SELECT NOW();')->execute();
	}

	public function tableExists($tableName)
	{
		$stmt = $this->db->prepare('SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?;');
		$stmt->execute([$tableName]);

		$res = $stmt->fetchAll();
		return !empty($res);
	}

	public function showTables($dbName)
	{
		$stmt = $this->db->query("SHOW TABLES;");
		$res = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return array_map(function ($item) {
			return array_shift($item);
		}, $res);
	}

	public function getTableInfo($tableName)
	{
		$stmt = $this->db->query(sprintf("DESCRIBE %s;", $this->escape($tableName)));
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function checkKeys($configKeys, $targetTable)
    {
        $primaryKeysInDb = $this->getKeysFromDbTable($targetTable);
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
}
