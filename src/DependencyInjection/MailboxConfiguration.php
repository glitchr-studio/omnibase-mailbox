<?php

namespace Base\Mailbox\DependencyInjection;

use Base\Bundle\AbstractBaseConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class MailboxConfiguration extends AbstractBaseConfiguration
{
    private bool $childrenDeclared = false;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getTreeBuilder();
        if ($this->childrenDeclared) {
            return $treeBuilder;
        }
        $this->childrenDeclared = true;

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('per_page')->min(1)->defaultValue(20)
                    ->info('Conversations listed per page.')->end()
                ->integerNode('inbox_limit')->min(0)->defaultValue(100)
                    ->info('Conversations a member may keep in the inbox; 0 for no limit. The old site refused mail to a full box.')->end()
                ->integerNode('flood_interval')->min(0)->defaultValue(120)
                    ->info('Seconds between two messages sent by the same member.')->end()
                ->integerNode('subject_max_length')->min(10)->defaultValue(55)->end()
                ->integerNode('content_max_length')->min(100)->defaultValue(10000)->end()
                ->integerNode('max_recipients')->min(1)->defaultValue(5)
                    ->info('How many members one conversation may be started with.')->end()
                ->scalarNode('required_role')->defaultValue('ROLE_USER')
                    ->info('Role needed to use the mailbox at all.')->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
