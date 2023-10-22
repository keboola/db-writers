<?php

declare(strict_types=1);

namespace Keboola\DbWriter;

use Keboola\Component\Config\BaseConfig;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriter\Writer\BaseWriter;
use Psr\Log\LoggerInterface;

readonly class WriterFactory
{
    public function __construct(public BaseConfig $config)
    {
    }

    /**
     * @throws UserException
     */
    public function create(LoggerInterface $logger): BaseWriter
    {
        $parameters = $this->config->getParameters();
        $writerClass = __NAMESPACE__ . '\\Writer\\' . $parameters['writer_class'];
        if (!class_exists($writerClass)) {
            throw new UserException(sprintf("Writer class '%s' doesn't exist", $writerClass));
        }

        return new $writerClass($parameters['db'], $logger);
    }
}
