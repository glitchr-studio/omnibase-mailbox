<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * This file is part of the Glitchr package.
 *
 * (c) Marco Meyer <marco.meyer@glitchr.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/* Plain autowiring over src/, the way an application's own src/ is wired. */
return function (ContainerConfigurator $configurator) {

    $src = dirname(__DIR__) . '/src';

    $services = $configurator->services();
    $services->defaults()
        ->autowire(true)
        ->autoconfigure(true)
        ->public(false);

    $services->load('Base\\Mailbox\\', $src . '/')
        ->exclude([
            $src . '/DependencyInjection/',
            $src . '/Entity/',
            $src . '/MailboxBundle.php',
            // Exceptions are values, not services.
            $src . '/Service/*Exception.php',
            $src . '/Event/',
            // Mapped only when desks or attachments are configured (MailboxExtension::prepend).
            $src . '/Extension/Entity/',
            // The bridge to omnibase/office's vault: loaded below, when it is installed.
            $src . '/Attachment/Office/',
        ]);

    if (interface_exists('Base\\Office\\Share\\AudienceResolverInterface')) {
        $services->load('Base\\Mailbox\\Attachment\\Office\\', $src . '/Attachment/Office/');
        $services->alias('mailbox.attachments', 'Base\\Mailbox\\Attachment\\Office\\VaultAttachments');
    } else {
        // No vault, no attachments: the controller is given nothing.
        $services->set('mailbox.attachments', \stdClass::class);
    }

    $services->load('Base\\Mailbox\\Controller\\', $src . '/Controller/')
        ->tag('controller.service_arguments');
};
