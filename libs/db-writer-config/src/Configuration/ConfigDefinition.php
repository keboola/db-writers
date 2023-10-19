<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration;

use Keboola\Component\Config\BaseConfigDefinition;
use Keboola\DbWriterConfig\Configuration\NodeDefinition\DbNode;
use Keboola\DbWriterConfig\Configuration\NodeDefinition\SshNode;
use Keboola\DbWriterConfig\Configuration\NodeDefinition\SslNode;
use Keboola\DbWriterConfig\Configuration\NodeDefinition\TableNodesDecorator;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class ConfigDefinition extends BaseConfigDefinition
{
    protected TableNodesDecorator $tableNodesDecorator;

    protected DbNode $dbNode;

    public function __construct(
        ?DbNode $dbNode = null,
        readonly protected ?SshNode $sshNode = null,
        readonly protected ?SslNode $sslNode = null,
        ?TableNodesDecorator $tableNodesDecorator = null,
    ) {
        $this->dbNode = $dbNode ?? new DbNode($sshNode, $sslNode);
        $this->tableNodesDecorator = $tableNodesDecorator ?? new TableNodesDecorator();
    }

    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $treeBuilder = new TreeBuilder('parameters');

        /** @var ArrayNodeDefinition $parametersNode */
        $parametersNode = $treeBuilder->getRootNode();

        // @formatter:off
        $parametersNode
            ->ignoreExtraKeys(false)
            ->children()
                ->scalarNode('data_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('extractor_class')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->append($this->dbNode->isRequired())
            ->end();
        // @formatter:on

        // Add common nodes for tables/rows config
        $tablesItemNode = $parametersNode->children()->arrayNode('tables')->arrayPrototype();
        $this->tableNodesDecorator->addNodes($tablesItemNode->children());
//        $tablesItemNode->validate()->always(fn($v) => $this->tableNodesDecorator->validate($v));

        return $parametersNode;
    }
}
