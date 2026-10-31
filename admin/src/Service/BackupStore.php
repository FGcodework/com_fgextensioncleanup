<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\Service;
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use RuntimeException;

/**
 * JSON backups of rows deleted by this component. Files live in Joomla's log folder, have a random part
 * in the name and 0600 permissions (extension parameters can contain secrets). File names coming from the
 * browser are only ever accepted as a base name matching PATTERN, never as a path.
 */
final class BackupStore
{
    public const PATTERN = '/^fgextensioncleanup_backup_([a-z]+)_(\d+)_(\d{8})_(\d{6})_([0-9a-f]{12})\.json$/D';
    private const MAX_READ_BYTES = 4194304;

    public function directory(): string
    {
        return rtrim((string) (Factory::getApplication()->get('log_path') ?: JPATH_ADMINISTRATOR . '/logs'), '/\\');
    }

    /** @return string path relative to the site root */
    public function write(string $kind, int $id, array $payload): string
    {
        $directory = $this->directory();
        $payload = ['tool' => 'FG Extension Cleanup', 'created_utc' => gmdate('c')] + $payload;
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $file = sprintf(
            '%s/fgextensioncleanup_backup_%s_%d_%s_%s.json',
            $directory,
            preg_replace('/[^a-z]/', '', strtolower($kind)) ?: 'record',
            $id,
            gmdate('Ymd_His'),
            bin2hex(random_bytes(6))
        );

        if ($json === false || !is_dir($directory) || !is_writable($directory) || @file_put_contents($file, $json, LOCK_EX) === false) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_FAILED'));
        }

        @chmod($file, 0600);

        return $this->relative($file);
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function listBackups(int $limit = 100): array
    {
        $files = glob($this->directory() . '/fgextensioncleanup_backup_*.json') ?: [];
        $items = [];

        foreach ($files as $path) {
            $name = basename($path);
            if (!preg_match(self::PATTERN, $name) || @is_link($path) || !@is_file($path)) {
                continue;
            }
            $items[] = ['file' => $name, 'path' => $path, 'mtime' => (int) @filemtime($path), 'size' => (int) @filesize($path)];
        }

        usort($items, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        $result = [];
        foreach (array_slice($items, 0, $limit) as $item) {
            $payload = $item['size'] <= self::MAX_READ_BYTES ? json_decode((string) @file_get_contents($item['path']), true) : null;
            $summary = is_array($payload) ? self::summarise($payload) : null;
            $result[] = [
                'file' => $item['file'],
                'mtime' => $item['mtime'],
                'size' => $item['size'],
                'valid' => $summary !== null,
                'kind' => $summary['kind'] ?? '',
                'type' => $summary['type'] ?? '',
                'name' => $summary['name'] ?? '',
                'id' => $summary['id'] ?? 0,
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> the decoded payload (validated structure) */
    public function read(string $file): array
    {
        $path = $this->resolve($file);
        $size = (int) @filesize($path);
        $payload = $size > 0 && $size <= self::MAX_READ_BYTES ? json_decode((string) @file_get_contents($path), true) : null;

        if (!is_array($payload) || ($payload['tool'] ?? '') !== 'FG Extension Cleanup' || self::summarise($payload) === null) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
        }

        return $payload;
    }

    public function delete(string $file): void
    {
        if (!@unlink($this->resolve($file))) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_NOT_FOUND'));
        }
    }

    /** Keep a restored backup as evidence, but hide it from the list. */
    public function markRestored(string $file): void
    {
        $path = $this->resolve($file);
        @rename($path, $path . '.restored');
    }

    /** @return array{kind: string, type: string, name: string, id: int}|null */
    public static function summarise(array $payload): ?array
    {
        if (!empty($payload['update_sites']) && is_array($payload['update_sites']) && isset($payload['update_sites'][0]['update_site_id'])) {
            $row = $payload['update_sites'][0];

            return ['kind' => 'update_site', 'type' => 'update_site', 'name' => (string) ($row['name'] ?? ''), 'id' => (int) $row['update_site_id']];
        }

        if (!empty($payload['extensions']) && is_array($payload['extensions']) && isset($payload['extensions'][0]['extension_id'])) {
            $row = $payload['extensions'][0];

            return ['kind' => 'extension', 'type' => (string) ($row['type'] ?? ''), 'name' => (string) ($row['name'] ?? ''), 'id' => (int) $row['extension_id']];
        }

        return null;
    }

    private function resolve(string $file): string
    {
        if ($file !== basename($file) || !preg_match(self::PATTERN, $file)) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
        }

        $path = $this->directory() . '/' . $file;
        if (@is_link($path) || !@is_file($path)) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_NOT_FOUND'));
        }

        return $path;
    }

    private function relative(string $file): string
    {
        $root = rtrim(str_replace('\\', '/', JPATH_ROOT), '/') . '/';
        $normalised = str_replace('\\', '/', $file);

        return str_starts_with($normalised, $root) ? substr($normalised, strlen($root)) : $normalised;
    }
}
