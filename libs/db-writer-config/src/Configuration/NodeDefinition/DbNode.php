<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\NodeDefinition;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeParentInterface;

class DbNode extends ArrayNodeDefinition implements NodeInterface
{
    public const NODE_NAME = 'db';

    protected NodeDefinition $sshNode;

    protected SslNode $sslNode;

    public function __construct(
        ?SshNode $sshNode = null,
        ?SslNode $sslNode = null,
        ?NodeParentInterface $parent = null,
    ) {
        parent::__construct(self::NODE_NAME, $parent);
        $this->sshNode = $sshNode ?? new SshNode();
        $this->sslNode = $sslNode ?? new SslNode();
        $this->init($this->children());
    }

    public function init(NodeBuilder $nodeBuilder): void
    {
        $this->addDriverNode($nodeBuilder);
        $this->addHostNode($nodeBuilder);
        $this->addPortNode($nodeBuilder);
        $this->addDatabaseNode($nodeBuilder);
        $this->addSchemaNode($nodeBuilder);
        $this->addUserNode($nodeBuilder);
        $this->addPasswordNode($nodeBuilder);

        $this->addSshNode($nodeBuilder);
        $this->addSslNode($nodeBuilder);
        $this->addInitQueriesNode($nodeBuilder);
    }

    protected function addDriverNode(NodeBuilder $builder): void
    {
        // For backward compatibility only
        $builder->scalarNode('driver');
    }

    protected function addHostNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('host');
    }

    protected function addPortNode(NodeBuilder $builder): void
    {
        $builder
            ->scalarNode('port')
            ->beforeNormalization()
            ->always(fn($v) => (string) $v);
    }

    protected function addDatabaseNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('database')->cannotBeEmpty();
    }

    protected function addSchemaNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('schema');
    }

    protected function addUserNode(NodeBuilder $builder): void
    {
        $builder->scalarNode('user')->isRequired();
    }

    protected function addPasswordNode(NodeBuilder $builder): void
    {
        $this->beforeNormalization()->always(function (array $v) {
            if (isset($v['password'])) {
                $v['#password'] = $v['password'];
                unset($v['password']);
            }
            return $v;
        });

        $builder->scalarNode('#password')->isRequired();
    }

    protected function addSshNode(NodeBuilder $builder): void
    {
        $builder->append($this->sshNode);
    }

    protected function addSslNode(NodeBuilder $builder): void
    {
        $builder->append($this->sslNode);
    }

    protected function addInitQueriesNode(NodeBuilder $builder): void
    {
        $builder->arrayNode('initQueries')->prototype('scalar');
    }
}
