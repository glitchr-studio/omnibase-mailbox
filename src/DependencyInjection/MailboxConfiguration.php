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
                // What follows is off unless asked for: a site that sets none of it runs as before, on the same three tables.
                ->arrayNode('desks')
                    ->info('Desks a member writes to without knowing anyone\'s username ("the secretariat"): a name => the role whose holders read and answer it. Adds the table mailbox_desk_conversation.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->beforeNormalization()->ifString()->then(static fn (string $role) => ['role' => $role])->end()
                        ->children()
                            ->scalarNode('role')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('label')->defaultNull()->info('How it is shown; null: its name.')->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->booleanNode('directory')->defaultFalse()
                    ->info('Compose to a choice (the desks, and whoever the RecipientProviderInterface services offer) instead of typed usernames.')->end()
                ->booleanNode('notify')->defaultFalse()
                    ->info('E-mail the other participants that a message awaits - never its content, nor its subject.')->end()
                ->scalarNode('sender')->defaultValue('%env(default::MAILER_TECHNICAL)%')
                    ->info('The From of those e-mails.')->end()
                ->integerNode('poll')->min(0)->defaultValue(0)
                    ->info('Seconds between two checks for new messages in the open conversation (omnibase\'s Stimulus poll controller); 0: none.')->end()
                ->booleanNode('attachments')->defaultFalse()
                    ->info('A file with a reply, kept in omnibase/office\'s encrypted vault (needs omnibase/office). Adds the table mailbox_attachment.')->end()
                ->booleanNode('encrypt')->defaultFalse()
                    ->info('Subjects and messages stored encrypted (libsodium). Fail closed: with no usable key, nothing is sent.')->end()
                ->scalarNode('key')->defaultNull()
                    ->info('Base64 of 32 bytes, from the secrets vault (MAILBOX_KEY).')->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
