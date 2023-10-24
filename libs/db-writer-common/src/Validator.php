<?php

namespace Keboola\DbWriter;

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\Component\Config\BaseConfig;
use Keboola\DbWriter\Exception\ApplicationException;
use Keboola\DbWriter\Exception\InvalidDatabaseHostException;
use Keboola\DbWriter\Exception\UserException;
use Psr\Log\LoggerInterface;

readonly class Validator
{
    public function __construct(public LoggerInterface $logger, public BaseConfig $config)
    {
    }

    /**
     * @throws InvalidDatabaseHostException
     */
    public function validateDatabaseHost(): void
    {
        if (!isset($this->config->getImageParameters()['approvedHostnames'])) {
            return;
        }
        $approvedHostnames = $this->config->getImageParameters()['approvedHostnames'];
        $db = $this->config->getParameters()['db'];
        $validHostname = array_filter($approvedHostnames, function ($v) use ($db) {
            return $v['host'] === $db['host'] && $v['port'] === $db['port'];
        });

        if (count($validHostname) === 0) {
            throw new InvalidDatabaseHostException(
                sprintf(
                    'Hostname "%s" with port "%s" is not approved.',
                    $db['host'],
                    $db['port']
                )
            );
        }
    }

    public function validateTableItems(string $tablePath, array $items): array
    {
        $manifestPath = $tablePath . '.manifest';
        if (!file_exists($manifestPath)) {
            throw new ApplicationException(sprintf('Manifest "%s" not found.', $manifestPath));
        }

        $manifest = @json_decode((string) file_get_contents($manifestPath), true);
        if (!$manifestPath) {
            throw new ApplicationException(sprintf('Manifest "%s" is not valid JSON.', $manifestPath));
        }

        $csvHeader = $manifest['columns'];
        $reordered = [];
        foreach ($csvHeader as $csvCol) {
            foreach ($items as $item) {
                if ($csvCol === $item['name']) {
                    $reordered[] = $item;
                }
            }
        }

        return $reordered;
    }
}