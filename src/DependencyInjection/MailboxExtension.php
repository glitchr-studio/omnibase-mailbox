<?php

namespace Base\Mailbox\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MailboxExtension extends AbstractBaseExtension
{
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
