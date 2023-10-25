<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\NodeDefinition;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

class TableNodesDecorator implements DecoratorInterface
{
    public function addNodes(NodeBuilder $nodeBuilder): void
    {
        $this->addTableIdNode($nodeBuilder);
        $this->addDbNameNode($nodeBuilder);
        $this->addIncrementalNode($nodeBuilder);
        $this->addExportNode($nodeBuilder);
        $this->addPrimaryKeyNode($nodeBuilder);
        $this->addItemsNode($nodeBuilder);
    }

    protected function addTableIdNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->scalarNode('tableId')->isRequired()->cannotBeEmpty();
    }

    protected function addDbNameNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->scalarNode('dbName')->isRequired()->cannotBeEmpty();
    }

    protected function addIncrementalNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('incremental')->defaultFalse();
    }

    protected function addExportNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->booleanNode('export')->defaultTrue();
    }

    protected function addPrimaryKeyNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder->arrayNode('primaryKey')->prototype('scalar')->cannotBeEmpty()->end();
    }

    protected function addItemsNode(NodeBuilder $nodeBuilder): void
    {
        $nodeBuilder
            ->arrayNode('items')
                ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('dbName')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('type')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('size')->end()
                    ->scalarNode('nullable')->end()
                    ->scalarNode('default')->end()
                ->end()
            ->end();
    }
}
