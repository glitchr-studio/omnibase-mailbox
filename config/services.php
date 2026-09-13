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
        ]);

    $services->load('Base\\Mailbox\\Controller\\', $src . '/Controller/')
        ->tag('controller.service_arguments');
};
