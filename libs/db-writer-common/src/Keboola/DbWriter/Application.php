<?php

/**
 * Created by PhpStorm.
 * User: miroslavcillik
 * Date: 10/12/15
 * Time: 12:17
 */

namespace Keboola\DbWriter;

use Keboola\Csv\CsvFile;
use Keboola\DbWriter\Configuration\ConfigDefinition;
use Keboola\DbWriter\Configuration\Validator;
use Keboola\DbWriter\Exception\ApplicationException;
use Keboola\DbWriter\Exception\UserException;
use Pimple\Container;


/***
 * Class Application
 * @package Keboola\DbWriter
 *
 */
class Application extends Container
{
    public function __construct($config, Logger $logger, $configDefinition = null)
    {
        parent::__construct();

        $app = $this;

        if ($configDefinition == null) {
            $configDefinition = new ConfigDefinition();
        }
        $validate = Validator::getValidator($configDefinition);

        $this['action'] = isset($config['action'])?$config['action']:'run';
        $this['parameters'] = $validate($config['parameters']);
        $this['logger'] = $logger;
        $this['writer_factory'] = function() use ($app) {
            return new WriterFactory($app['parameters']);
        };
        $this['writer'] = function() use ($app) {
            return $app['writer_factory']->create($app['logger']);
        };
    }

    public function run()
    {
        $actionMethod = $this['action'] . 'Action';
        if (!method_exists($this, $actionMethod)) {
            throw new UserException(sprintf("Action '%s' does not exist.", $this['action']));
        }

        return $this->$actionMethod();
    }

    public function runAction()
    {
        $uploaded = [];
        $tables = array_filter($this['parameters']['tables'], function ($table) {
            return ($table['export']);
        });

        $writer = $this['writer'];
        foreach ($tables as $table) {
            if (!$writer->isTableValid($table)) {
                continue;
            }

            $csv = $this->getInputCsv($table['tableId']);

            $targetTableName = $table['dbName'];

            if ($table['incremental']) {
                $table['dbName'] = $writer->generateTmpName($table['dbName']);
            }

            $table['items'] = $this->reorderColumns($csv, $table['items']);

            if (empty($table['items'])) {
                continue;
            }

            try {
                $writer->drop($table['dbName']);
                $writer->create($table);
                $writer->write($csv, $table);

                if ($table['incremental']) {
                    // create target table if not exists
                    if (!$writer->tableExists($targetTableName)) {
                        $destinationTable = $table;
                        $destinationTable['dbName'] = $targetTableName;
                        $destinationTable['incremental'] = false;
                        $writer->create($destinationTable);
                    }
                    $writer->upsert($table, $targetTableName);
                }
            } catch (UserException $e) {
                throw $e;
            } catch (\PDOException $e) {
                throw new UserException($e->getMessage(), 400, $e);
            } catch (\Exception $e) {
                throw new ApplicationException($e->getMessage(), 500, $e);
            }

            $uploaded[] = $table['tableId'];
        }

        return [
            'status' => 'success',
            'uploaded' => $uploaded
        ];
    }

    private function reorderColumns(CsvFile $csv, $items)
    {
        $csv->next();
        $csvHeader = $csv->current();
        $csv->rewind();

        $reordered = [];
        foreach ($csvHeader as $csvCol) {
            foreach ($items as $item) {
                if ($csvCol == $item['name']) {
                    $reordered[] = $item;
                }
            }
        }

        return $reordered;
    }

    private function getInputCsv($tableId)
    {
        return new CsvFile($this['parameters']['data_dir'] . "/in/tables/" . $tableId . ".csv");
    }

    public function testConnectionAction()
    {
        try {
            $this['writer']->testConnection();
        } catch (\Exception $e) {
            throw new UserException(sprintf("Connection failed: '%s'", $e->getMessage()), 0, $e);
        }

        return [
            'status' => 'success',
        ];
    }

    public function getTablesInfoAction()
    {
        $tables = $this['writer']->showTables($this['parameters']['db']['database']);

        $tablesInfo = [];
        foreach ($tables as $tableName) {
            $tablesInfo[$tableName] = $this['writer']->getTableInfo($tableName);
        }

        return [
            'status' => 'success',
            'tables' => $tablesInfo
        ];
    }
}
