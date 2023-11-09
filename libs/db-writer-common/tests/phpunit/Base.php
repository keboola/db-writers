<?php

declare(strict_types=1);

namespace Keboola\DbWriter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class Base extends TestCase
{
    protected function setUp(): void
    {
        $this->closeSshTunnels();
        parent::setUp();
    }

    protected function closeSshTunnels(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }
}