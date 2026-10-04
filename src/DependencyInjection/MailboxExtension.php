<?php

namespace Base\Mailbox\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MailboxExtension extends AbstractBaseExtension implements PrependExtensionInterface
{
    /**
     * The extension's own tables (mailbox_desk_conversation, mailbox_attachment)
     * are mapped only where desks or attachments are configured: a site that
     * uses neither keeps the three tables it had, and no migration to write.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $wanted = false;
        foreach ($container->getExtensionConfig('mailbox') as $config) {
            if (!empty($config['desks']) || !empty($config['attachments'])) {
                $wanted = true;
            }
        }
        if ($wanted && $container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', ['orm' => ['mappings' => ['MailboxExtension' => [
                'is_bundle' => false,
                'type' => 'attribute',
                'dir' => dirname(__DIR__).'/Extension/Entity',
                'prefix' => 'Base\\Mailbox\\Extension\\Entity',
                'alias' => 'MailboxExtension',
            ]]]]);
        }
    }

    public function getConfiguration(array $config, ContainerBuilder $container): MailboxConfiguration
    {
        return new MailboxConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');

        $processor = new Processor();
        $configuration = new MailboxConfiguration();
        $config = $processor->processConfiguration($configuration, $configs);

        // mailbox.per_page, mailbox.inbox_limit, ...
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());
    }
}
