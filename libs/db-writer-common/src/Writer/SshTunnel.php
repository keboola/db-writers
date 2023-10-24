<?php

namespace Keboola\DbWriter\Writer;

use Keboola\DbWriterConfig\Exception\PropertyNotSetException;
use Keboola\SSHTunnel\SSHException;
use Keboola\DbWriter\Exception\SshException as DbWriterSshException;
use Keboola\DbWriter\Exception\UserException;
use Keboola\DbWriterConfig\Configuration\ValueObject\DatabaseConfig;
use Keboola\SSHTunnel\SSH;
use Psr\Log\LoggerInterface;
use Retry\BackOff\ExponentialBackOffPolicy;
use Retry\Policy\SimpleRetryPolicy;
use Retry\RetryProxy;
use Throwable;

class SshTunnel
{
    public const DEFAULT_SSH_PORT = 22;
    public const DEFAULT_LOCAL_PORT = '33006';
    public const DEFAULT_MAX_TRIES = 5;

    public function __construct(readonly private LoggerInterface $logger)
    {
    }

    /**
     * @throws PropertyNotSetException|UserException|DbWriterSshException
     */
    public function createSshTunnel(DatabaseConfig $config): DatabaseConfig
    {
        // check main param
        if (!$config->hasSshConfig()) {
            return $config;
        }

        if (!$config->getSshConfig()->getPublicKey() && !$config->getSshConfig()->getPrivateKey()) {
            throw new DbWriterSshException('SSH public or private key is missing.');
        }

        if (!$config->getSshConfig()->getSshHost()) {
            throw new DbWriterSshException('SSH host is missing.');
        }

        $sshConfig = $config->getSshConfig()->toArray();

        $sshConfig['remoteHost'] = $config->getHost();
        $sshConfig['remotePort'] = $config->getPort();

        if (empty($sshConfig['user'])) {
            $sshConfig['user'] = $config->getUser();
        }
        if (empty($sshConfig['localPort'])) {
            $sshConfig['localPort'] = self::DEFAULT_LOCAL_PORT;
        }
        if (empty($sshConfig['sshPort'])) {
            $sshConfig['sshPort'] = self::DEFAULT_SSH_PORT;
        }

        if (isset($sshConfig['keys']['#private'])) {
            $sshConfig['privateKey'] = $sshConfig['keys']['#private'];
        } else {
            $sshConfig['privateKey'] = $sshConfig['keys']['private'];
            $this->logger->warning('Using unencrypted private key');
        }

        $tunnelParams = array_intersect_key(
            $sshConfig,
            array_flip(
                [
                    'user', 'sshHost', 'sshPort', 'localPort', 'remoteHost', 'remotePort', 'privateKey', 'compression',
                ]
            )
        );
        $this->logger->info(
            sprintf(
                "Creating SSH tunnel to '%s' on local port '%s'",
                $tunnelParams['sshHost'],
                $tunnelParams['localPort'])
        );

        $simplyRetryPolicy = new SimpleRetryPolicy(
            $sshConfig['maxRetries'] ?? self::DEFAULT_MAX_TRIES,
            [SSHException::class, Throwable::class]
        );

        $exponentialBackOffPolicy = new ExponentialBackOffPolicy();
        $proxy = new RetryProxy(
            $simplyRetryPolicy,
            $exponentialBackOffPolicy,
            $this->logger
        );

        try {
            $proxy->call(function () use ($tunnelParams): void {
                $ssh = new SSH();
                $ssh->openTunnel($tunnelParams);
            });
        } catch (SSHException $e) {
            throw new UserException($e->getMessage() . 'Retries count: ' . $proxy->getTryCount() , 0, $e);
        }

        $dbConfig = $config->toArray();
        $dbConfig['ssh'] = $sshConfig;
        $dbConfig['host'] = '127.0.0.1';
        $dbConfig['port'] = $sshConfig['localPort'];

        return DatabaseConfig::fromArray($dbConfig);
    }
}