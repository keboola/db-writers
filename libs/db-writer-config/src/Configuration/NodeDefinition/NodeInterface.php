<?php

declare(strict_types=1);

namespace Keboola\DbWriterConfig\Configuration\NodeDefinition;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;

interface NodeInterface
{
    public function init(NodeBuilder $nodeBuilder): void;
}
