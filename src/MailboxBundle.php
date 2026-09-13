<?php

namespace Base\Mailbox;

use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Private messages between members: conversations, each with its
 * participants and their own read/archive state.
 *
 * Deliberately NOT built on Thread: a private message is not content that
 * is published, tagged, liked or listed - it is mail. What it shares with
 * the rest of base-bundle is the User, the Timestamp attributes and the
 * paginator.
 */
class MailboxBundle extends AbstractBaseBundle
{
    // Own singleton slot - see AbstractBaseBundle's constructor.
    use SingletonTrait;

    // Public, delegating to parent: the trait's protected no-op would
    // otherwise shadow the real constructor (same note as ForumBundle).
    public function __construct()
    {
        parent::__construct();
    }

    /** Modern layout: package root, templates/ as @Mailbox, entities in src/Entity. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->setMapping($this->getPath() . '/src/Entity', 'Base\Mailbox\Entity', 'App\Entity\Mailbox');
        $this->setMapping($this->getPath() . '/src/Repository', 'Base\Mailbox\Repository', 'App\Repository\Mailbox');
    }
}
