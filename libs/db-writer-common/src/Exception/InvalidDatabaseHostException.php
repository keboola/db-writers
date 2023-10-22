<?php

namespace Keboola\DbWriter\Exception;

use Exception;
use Keboola\CommonExceptions\UserExceptionInterface;

class InvalidDatabaseHostException extends Exception implements UserExceptionInterface
{
}
