<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Turns the raw `name` column of #__extensions (often a language key such as COM_FOO) into the text Joomla's
 * own extension manager would show. Display only: nothing here is ever used to decide, delete or repair.
 */
final class NameTranslator
{
    /** @var array<string, string> */
    private static array $cache = [];

    /** A language key: upper case letters, digits and underscores with at least one underscore. */
    public static function isLanguageKey(string $name): bool
    {
        return preg_match('/^[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+$/', $name) === 1;
    }

    public static function translate(object $record): string
    {
        $name = (string) ($record->name ?? '');

        if (!self::isLanguageKey($name)) {
            return $name;
        }

        $cacheKey = $name;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $translated = $name;

        try {
            $element = (string) ($record->element ?? '');
            $folder = (string) ($record->folder ?? '');
            $type = (string) ($record->type ?? '');
            $client = (int) ($record->client_id ?? 0) === 1 ? JPATH_ADMINISTRATOR : JPATH_SITE;

            if (preg_match('/^[A-Za-z0-9_.\-]+$/', $element) === 1 && ($folder === '' || preg_match('/^[A-Za-z0-9_.\-]+$/', $folder) === 1)) {
                $language = Factory::getApplication()->getLanguage();

                foreach (self::sources($type, $element, $folder, $client) as [$extension, $paths]) {
                    foreach ($paths as $path) {
                        $language->load($extension, $path);
                        $language->load($extension . '.sys', $path);
                    }
                }

                $text = Text::_($name);
                if ($text !== '' && $text !== $name) {
                    $translated = $text;
                }
            }
        } catch (\Throwable $e) {
            // Display only - fall back to the raw name.
        }

        return self::$cache[$cacheKey] = $translated;
    }

    /** @return array<int, array{0: string, 1: array<int, string>}> language file base names and the folders to look in */
    private static function sources(string $type, string $element, string $folder, string $client): array
    {
        $prefixed = static fn (string $prefix): string => str_starts_with($element, $prefix) ? $element : $prefix . $element;

        switch ($type) {
            case 'component':
                $name = $prefixed('com_');
                return [[$name, [JPATH_ADMINISTRATOR, JPATH_ADMINISTRATOR . '/components/' . $name, JPATH_SITE]]];
            case 'plugin':
                $name = 'plg_' . $folder . '_' . $element;
                return [[$name, [JPATH_ADMINISTRATOR, JPATH_PLUGINS . '/' . $folder . '/' . $element]]];
            case 'module':
                $name = $prefixed('mod_');
                return [[$name, [$client, $client . '/modules/' . $name]]];
            case 'template':
                $name = 'tpl_' . $element;
                return [[$name, [$client, $client . '/templates/' . $element]]];
            case 'library':
                return [['lib_' . $element, [JPATH_SITE]]];
            case 'package':
                return [[$prefixed('pkg_'), [JPATH_SITE]]];
            case 'file':
                return [[$prefixed('files_'), [JPATH_SITE]]];
            default:
                return [];
        }
    }
}
