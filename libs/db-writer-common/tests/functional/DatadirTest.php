<?php

declare(strict_types=1);

namespace Keboola\DbWriter\TestsFunctional;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DbWriter\Traits\CloseSshTunnelsTrait;
use Keboola\DbWriter\Traits\DropAllTablesTrait;
use Keboola\DbWriterAdapter\PDO\PdoConnection;
use RuntimeException;

class DatadirTest extends DatadirTestCase
{
    use CloseSshTunnelsTrait;
    use DropAllTablesTrait;

    public PdoConnection $connection;

    protected function getScript(): string
    {
        return $this->getTestFileDir() . '/../../tests/Fixtures/run.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        putenv('KBC_COMPONENT_RUN_MODE=run');

        // Test dir, eg. "/code/tests/functional/full-load-ok"
        $this->testProjectDir = $this->getTestFileDir() . '/' . $this->dataName();
        $this->testTempDir = $this->temp->getTmpFolder();

        $isSsl = str_starts_with((string) $this->dataName(), 'ssl-');
        $this->connection = PdoTestConnection::createConnection();
        $this->closeSshTunnels();
        $this->dropAllTables($this->connection);

        // Load setUp.php file - used to init database state
        $setUpPhpFile = $this->testProjectDir . '/setUp.php';
        if (file_exists($setUpPhpFile)) {
            // Get callback from file and check it
            $initCallback = require $setUpPhpFile;
            if (!is_callable($initCallback)) {
                throw new RuntimeException(sprintf('File "%s" must return callback!', $setUpPhpFile));
            }

            // Invoke callback
            $initCallback($this);
        }
    }
}
