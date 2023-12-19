<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Exception;

use Exception;
use Keboola\CommonExceptions\ApplicationExceptionInterface;

class PropertyNotSetException extends Exception implements ApplicationExceptionInterface
{

}
