<?php

// Standalone (composer install in this checkout) or inside a host application
// (vendor/omnibase/mailbox): whichever autoloader exists is used,
// and the test namespace is registered by hand because a host's autoloader
// never reads a dependency's autoload-dev.
// (a path repository's symlink resolves outside the application: its autoloader is then the working directory's)
$candidates = [__DIR__.'/../vendor/autoload.php', __DIR__.'/../../../autoload.php', getcwd().'/vendor/autoload.php'];
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $loader = require $candidate;
        $loader->addPsr4('Tests\\Base\\Mailbox\\', __DIR__);
        // Prepended: the classes under test are this checkout's, even when a
        // host application has an installed copy of the bundle too.
        $loader->addPsr4('Base\\Mailbox\\', __DIR__.'/../src', true);

        return;
    }
}

throw new RuntimeException('No autoloader found: run composer install in this checkout or install the bundle in an application.');
