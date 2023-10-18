<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\NodeDefinition;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class SshNode extends ArrayNodeDefinition implements NodeInterface
{
    public const NODE_NAME = 'ssh';

    private const SSH_REQUIRED_PARAMS = [
        'user',
        'sshHost',
        'keys',
    ];

    private const SSH_REQUIRED_KEYS_PARAMS = [
        'public',
        '#private',
    ];

    public function __construct(?NodeParentInterface $parent = null)
    {
        parent::__construct(self::NODE_NAME, $parent);
        $this->init($this->children());
    }

    public function init(NodeBuilder $nodeBuilder): void
    {
        $this->addEnabledNode($nodeBuilder);
        $this->addKeysNode($nodeBuilder);
        $this->addSshHostNode($nodeBuilder);
        $this->addSshPortNode($nodeBuilder);
        $this->addRemoteHostNode($nodeBuilder);
        $this->addRemotePortNode($nodeBuilder);
        $this->addLocalPortNode($nodeBuilder);
        $this->addUserNode($nodeBuilder);
        $this->addMaxRetriesNode($nodeBuilder);
        $this->addValidation();
    }

    protected function addEnabledNode(NodeBuilder $builder): void
    {
        $builder->booleanNode('enabled');
    }

    protected function addKeysNode(NodeBuilder $builder): void
    {
        // @formatter:off
        $builder->arrayNode('keys')
            ->children()
            ->scalarNode('#private')->end()
            ->scalarNode('public')->end();
        // @formatter:on
    }

    protected function addSshHostNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('sshHost');
    }

    protected function addSshPortNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('sshPort');
    }

    protected function addRemoteHostNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('remoteHost');
    }

    protected function addRemotePortNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('remotePort');
    }

    protected function addLocalPortNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('localPort');
    }

    protected function addUserNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('user');
    }

    protected function addMaxRetriesNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('maxRetries');
    }

    protected function addValidation(): void
    {
        $this->validate()->always(function ($val) {
            if (!isset($val['enabled']) || $val['enabled'] === false) {
                return $val;
            }

            foreach (self::SSH_REQUIRED_PARAMS as $param) {
                if (!array_key_exists($param, $val)) {
                    throw new InvalidConfigurationException(sprintf(
                        'The child config "%s" under "root.parameters.db.ssh" must be configured.',
                        $param,
                    ));
                }
            }

            foreach (self::SSH_REQUIRED_KEYS_PARAMS as $param) {
                if (!array_key_exists($param, $val['keys'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'The child config "%s" under "root.parameters.db.ssh.keys" must be configured.',
                        $param,
                    ));
                }
            }

            return $val;
        });
    }
}
