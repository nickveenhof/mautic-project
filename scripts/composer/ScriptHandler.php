<?php

/**
 * @file
 * Contains \MauticProject\composer\ScriptHandler.
 */

namespace MauticProject\composer;

use Composer\Script\Event;
use Composer\Semver\Comparator;
use Symfony\Component\Filesystem\Filesystem;
use Webmozart\PathUtil\Path;
use MauticFinder\MauticFinder;

class ScriptHandler
{

    public static function createRequiredFiles(Event $event)
    {
        $fs = new Filesystem();
        $mauticFinder = new mauticFinder();
        $mauticFinder->locateRoot(getcwd());
        $mauticRoot = $mauticFinder->getmauticRoot();

        $dirs = [
            'plugins',
            'themes',
        ];

        // Required for unit testing
        foreach ($dirs as $dir) {
            if (!$fs->exists($mauticRoot . '/' . $dir)) {
                $fs->mkdir($mauticRoot . '/' . $dir);
                $fs->touch($mauticRoot . '/' . $dir . '/.gitkeep');
            }
        }

        // Create the files directory with chmod 0777
        if (!$fs->exists($mauticRoot . '/media')) {
            $oldmask = umask(0);
            $fs->mkdir($mauticRoot . '/media', 0777);
            umask($oldmask);
            $event->getIO()->write("Created a media directory with chmod 0777");
        }
    }

    public static function moveFilesToProjectRoot(Event $event)
    {
        $fs = new Filesystem();
        $mauticFinder = new mauticFinder();
        $mauticFinder->locateRoot(getcwd());
        $mauticRoot = $mauticFinder->getmauticRoot();
        $composerRoot = $mauticFinder->getComposerRoot();

        $dirsToMoveToRoot = [
            '.github',
            'var',
        ];

        $dirsToDelete = [
            'vendor',
        ];

        // Required for unit testing
        foreach ($dirsToMoveToRoot as $dir) {
            if ($fs->exists($mauticRoot . '/' . $dir) && !$fs->exists($composerRoot . '/' . $dir)) {
                $fs->rename($mauticRoot . '/' . $dir, $composerRoot . '/' . $dir);
            }
        }

        // Required for unit testing
        foreach ($dirsToDelete as $dir) {
            if ($fs->exists($mauticRoot . '/' . $dir)) {
                $fs->remove($mauticRoot . '/' . $dir);
            }
        }

        // Create the files directory with chmod 0777
        if (!$fs->exists($mauticRoot . '/media/files')) {
            $oldmask = umask(0);
            $fs->mkdir($mauticRoot . '/media/files', 0777);
            umask($oldmask);
            $event->getIO()->write("Created a media/files directory with chmod 0777");
        }
    }

    /**
     * Checks if the installed version of Composer is compatible.
     *
     * Composer 1.0.0 and higher consider a `composer install` without having a
     * lock file present as equal to `composer update`. We do not ship with a lock
     * file to avoid merge conflicts downstream, meaning that if a project is
     * installed with an older version of Composer the scaffolding of Mautic will
     * not be triggered. We check this here instead of in mautic-scaffold to be
     * able to give immediate feedback to the end user, rather than failing the
     * installation after going through the lengthy process of compiling and
     * downloading the Composer dependencies.
     *
     * @see https://github.com/composer/composer/pull/5035
     */
    public static function checkComposerVersion(Event $event)
    {
        $composer = $event->getComposer();
        $io = $event->getIO();

        $version = $composer::VERSION;

        // The dev-channel of composer uses the git revision as version number,
        // try to the branch alias instead.
        if (preg_match('/^[0-9a-f]{40}$/i', $version)) {
            $version = $composer::BRANCH_ALIAS_VERSION;
        }

        // If Composer is installed through git we have no easy way to determine if
        // it is new enough, just display a warning.
        if ($version === '@package_version@' || $version === '@package_branch_alias_version@') {
            $io->writeError('<warning>You are running a development version of Composer. If you experience problems, please update Composer to the latest stable version.</warning>');
        } elseif (Comparator::lessThan($version, '1.0.0')) {
            $io->writeError('<error>Mautic-project requires Composer version 1.0.0 or higher. Please update your Composer before continuing</error>.');
            exit(1);
        }
    }

}
