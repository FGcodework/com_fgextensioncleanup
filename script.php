<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Registers the component in Joomla's User Actions Log (#__action_logs_extensions), so it also appears in the
 * "Extension" filter there. Runs on install and on every update, which makes upgrades from older versions work too.
 * Failures are ignored: the log entries themselves are written and shown without this registration.
 */
return new class () implements InstallerScriptInterface {
    private const EXTENSION = 'com_fgextensioncleanup';

    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__action_logs_extensions'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote(self::EXTENSION))
            )->execute();
        } catch (\Throwable $exception) {
            // Nothing to clean up (Action Logs tables missing).
        }

        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__action_logs_extensions'))
                    ->where($db->quoteName('extension') . ' = ' . $db->quote(self::EXTENSION))
            );

            if (!$db->loadResult()) {
                $row = (object) ['extension' => self::EXTENSION];
                $db->insertObject('#__action_logs_extensions', $row);
            }
        } catch (\Throwable $exception) {
            // Action Logs tables missing or not writable: ignore.
        }

        return true;
    }
};
