<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\Model;
defined('_JEXEC') or die;

use FGcodework\Component\FgExtensionCleanup\Administrator\Service\BackupStore;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use RuntimeException;

/** Orphaned update sites and the backup/restore of everything this component deletes. */
final class MaintenanceModel extends BaseDatabaseModel
{
    private const RESTORABLE_TYPES = ['plugin', 'library', 'component'];
    private const REPORT_LIMIT = 100;

    // ---------------------------------------------------------------- update sites

    /**
     * An update site is orphaned when no extension is linked to it, or when every linked #__extensions record
     * no longer exists. Sites that belong to Joomla itself are reported but never offered for removal.
     *
     * @return array{orphans: array<int, array<string, mixed>>, total: int}
     */
    public function getUpdateSites(): array
    {
        $db = $this->db();

        $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__update_sites'))->order($db->quoteName('update_site_id') . ' ASC'));
        $sites = $db->loadObjectList() ?: [];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('l.update_site_id'),
                $db->quoteName('l.extension_id'),
                $db->quoteName('e.extension_id', 'existing_id'),
                $db->quoteName('e.type'),
                $db->quoteName('e.element'),
                $db->quoteName('e.folder'),
                $db->quoteName('e.client_id'),
            ])
            ->from($db->quoteName('#__update_sites_extensions', 'l'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e') . ' ON ' . $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('l.extension_id'));
        $db->setQuery($query);

        $links = [];
        foreach ($db->loadObjectList() ?: [] as $link) {
            $links[(int) $link->update_site_id][] = $link;
        }

        $orphans = [];
        foreach ($sites as $site) {
            $siteLinks = $links[(int) $site->update_site_id] ?? [];
            $existing = array_filter($siteLinks, static fn (object $l): bool => $l->existing_id !== null);

            if ($existing) {
                continue;
            }

            $location = (string) ($site->location ?? '');
            $isJoomla = stripos($location, 'update.joomla.org') !== false;
            $missingIds = array_map(static fn (object $l): int => (int) $l->extension_id, $siteLinks);

            $orphans[] = [
                'id' => (int) $site->update_site_id,
                'name' => (string) ($site->name ?? ''),
                'type' => (string) ($site->type ?? ''),
                'location' => $location,
                'enabled' => (int) ($site->enabled ?? 0),
                'reason' => $missingIds
                    ? Text::sprintf('COM_FGEXTENSIONCLEANUP_US_REASON_MISSING', implode(', ', $missingIds))
                    : Text::_('COM_FGEXTENSIONCLEANUP_US_REASON_NOLINK'),
                'removable' => !$isJoomla,
            ];
        }

        return ['orphans' => $orphans, 'total' => count($sites)];
    }

    /** @return array{id: int, name: string, backup: string} */
    public function removeUpdateSite(int $id): array
    {
        $site = null;
        foreach ($this->getUpdateSites()['orphans'] as $candidate) {
            if ($candidate['id'] === $id && $candidate['removable']) {
                $site = $candidate;
                break;
            }
        }

        if ($site === null) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_US_NOT_REMOVABLE'));
        }

        $db = $this->db();
        $rows = static function (string $table) use ($db, $id): array {
            $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName($table))->where($db->quoteName('update_site_id') . ' = ' . $id));

            return $db->loadAssocList() ?: [];
        };

        $backup = (new BackupStore())->write('updatesite', $id, [
            'kind' => 'update_site',
            'note' => 'Rows deleted from the database. Cached #__updates rows are not part of the backup (Joomla rebuilds them).',
            'update_sites' => $rows('#__update_sites'),
            'update_sites_extensions' => $rows('#__update_sites_extensions'),
        ]);

        $db->transactionStart();
        try {
            foreach (['#__update_sites_extensions', '#__updates', '#__update_sites'] as $table) {
                $db->setQuery($db->getQuery(true)->delete($db->quoteName($table))->where($db->quoteName('update_site_id') . ' = ' . $id));
                $db->execute();
            }
            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();
            @unlink(JPATH_ROOT . '/' . $backup);

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        return ['id' => $id, 'name' => $site['name'], 'backup' => $backup];
    }

    // ---------------------------------------------------------------- other orphaned records (read-only report)

    /**
     * Records outside #__extensions that point to an extension which no longer exists. Report only: nothing here is
     * ever deleted by this component.
     *
     * @return array{modules: array, menu: array, schemas: array, links: array, limit: int}
     */
    public function getOtherOrphans(): array
    {
        $db = $this->db();
        $limit = self::REPORT_LIMIT + 1;
        $result = ['limit' => self::REPORT_LIMIT];

        // Module instances whose module extension is not registered (same client).
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('m.id'), $db->quoteName('m.title'), $db->quoteName('m.module'),
                $db->quoteName('m.client_id'), $db->quoteName('m.position'), $db->quoteName('m.published'),
            ])
            ->from($db->quoteName('#__modules', 'm'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e') . ' ON ' . $db->quoteName('e.type') . ' = ' . $db->quote('module')
                . ' AND ' . $db->quoteName('e.element') . ' = ' . $db->quoteName('m.module')
                . ' AND ' . $db->quoteName('e.client_id') . ' = ' . $db->quoteName('m.client_id'))
            ->where($db->quoteName('e.extension_id') . ' IS NULL')
            ->order($db->quoteName('m.id') . ' ASC');
        $result['modules'] = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];

        // Site menu items of type "component" whose component record no longer exists.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('mi.id'), $db->quoteName('mi.title'), $db->quoteName('mi.link'),
                $db->quoteName('mi.menutype'), $db->quoteName('mi.published'), $db->quoteName('mi.component_id'),
            ])
            ->from($db->quoteName('#__menu', 'mi'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e') . ' ON ' . $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('mi.component_id'))
            ->where($db->quoteName('mi.type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('mi.client_id') . ' = 0')
            ->where($db->quoteName('mi.component_id') . ' > 0')
            ->where($db->quoteName('e.extension_id') . ' IS NULL')
            ->order($db->quoteName('mi.id') . ' ASC');
        $result['menu'] = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];

        // Schema versions of extensions that are gone.
        $query = $db->getQuery(true)
            ->select([$db->quoteName('s.extension_id'), $db->quoteName('s.version_id')])
            ->from($db->quoteName('#__schemas', 's'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e') . ' ON ' . $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('s.extension_id'))
            ->where($db->quoteName('e.extension_id') . ' IS NULL')
            ->order($db->quoteName('s.extension_id') . ' ASC');
        $result['schemas'] = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];

        // Update-site links to missing extensions. Sites that are orphaned as a whole are handled by the update-site panel.
        $orphanSiteIds = array_column($this->getUpdateSites()['orphans'], 'id');
        $query = $db->getQuery(true)
            ->select([$db->quoteName('l.update_site_id'), $db->quoteName('l.extension_id'), $db->quoteName('us.name')])
            ->from($db->quoteName('#__update_sites_extensions', 'l'))
            ->join('LEFT', $db->quoteName('#__extensions', 'e') . ' ON ' . $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('l.extension_id'))
            ->join('LEFT', $db->quoteName('#__update_sites', 'us') . ' ON ' . $db->quoteName('us.update_site_id') . ' = ' . $db->quoteName('l.update_site_id'))
            ->where($db->quoteName('e.extension_id') . ' IS NULL')
            ->order($db->quoteName('l.update_site_id') . ' ASC');
        $links = $db->setQuery($query, 0, $limit)->loadObjectList() ?: [];
        $result['links'] = array_values(array_filter($links, static fn (object $l): bool => !in_array((int) $l->update_site_id, $orphanSiteIds, true)));

        return $result;
    }

    // ---------------------------------------------------------------- backups

    /** @return array<int, array<string, mixed>> */
    public function getBackups(): array
    {
        return (new BackupStore())->listBackups();
    }

    public function deleteBackup(string $file): void
    {
        (new BackupStore())->delete($file);
    }

    /**
     * Put the rows of a backup back. Refuses when the extension / update site (or an equivalent record) exists again.
     *
     * @return array{id: int, name: string, type: string, menu_skipped: bool}
     */
    public function restoreBackup(string $file): array
    {
        $store = new BackupStore();
        $payload = $store->read($file);
        $summary = BackupStore::summarise($payload);
        $db = $this->db();

        $db->transactionStart();
        try {
            if ($summary['kind'] === 'update_site') {
                $this->restoreUpdateSite($payload);
            } else {
                $this->restoreExtension($payload);
            }
            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();

            throw $exception instanceof RuntimeException ? $exception : new RuntimeException($exception->getMessage(), 0, $exception);
        }

        $store->markRestored($file);

        return [
            'id' => $summary['id'],
            'name' => $summary['name'],
            'type' => $summary['type'],
            'menu_skipped' => !empty($payload['admin_menu']),
        ];
    }

    private function restoreExtension(array $payload): void
    {
        $db = $this->db();
        $rows = $payload['extensions'] ?? [];

        if (count($rows) !== 1 || !in_array((string) ($rows[0]['type'] ?? ''), self::RESTORABLE_TYPES, true)) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
        }

        $row = $this->checkedRow('#__extensions', $rows[0]);
        $id = (int) $row['extension_id'];

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where('(' . $db->quoteName('extension_id') . ' = ' . $id . ' OR ('
                . $db->quoteName('type') . ' = ' . $db->quote((string) $row['type'])
                . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote((string) $row['element'])
                . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote((string) ($row['folder'] ?? ''))
                . ' AND ' . $db->quoteName('client_id') . ' = ' . (int) ($row['client_id'] ?? 0) . '))');
        $db->setQuery($query, 0, 1);
        if ($db->loadResult()) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_RESTORE_EXISTS'));
        }

        $object = (object) $row;
        $db->insertObject('#__extensions', $object);

        foreach ($payload['schemas'] ?? [] as $schema) {
            $schema = $this->checkedRow('#__schemas', $schema);
            if ((int) $schema['extension_id'] === $id) {
                $object = (object) $schema;
                $db->insertObject('#__schemas', $object);
            }
        }

        $this->restoreLinks($payload['update_sites_extensions'] ?? [], $id, null);
    }

    private function restoreUpdateSite(array $payload): void
    {
        $db = $this->db();
        $rows = $payload['update_sites'] ?? [];

        if (count($rows) !== 1) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
        }

        $row = $this->checkedRow('#__update_sites', $rows[0]);
        $siteId = (int) $row['update_site_id'];

        $db->setQuery($db->getQuery(true)->select($db->quoteName('update_site_id'))->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('update_site_id') . ' = ' . $siteId), 0, 1);
        if ($db->loadResult()) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_RESTORE_EXISTS'));
        }

        $object = (object) $row;
        $db->insertObject('#__update_sites', $object);
        $this->restoreLinks($payload['update_sites_extensions'] ?? [], null, $siteId);
    }

    /** Re-create #__update_sites_extensions rows, but only where both sides exist. */
    private function restoreLinks(array $links, ?int $extensionId, ?int $siteId): void
    {
        $db = $this->db();

        foreach ($links as $link) {
            $link = $this->checkedRow('#__update_sites_extensions', $link);
            $linkExtension = (int) $link['extension_id'];
            $linkSite = (int) $link['update_site_id'];

            if (($extensionId !== null && $linkExtension !== $extensionId) || ($siteId !== null && $linkSite !== $siteId)) {
                continue;
            }

            $exists = static function (string $table, string $column, int $value) use ($db): bool {
                $db->setQuery($db->getQuery(true)->select($db->quoteName($column))->from($db->quoteName($table))
                    ->where($db->quoteName($column) . ' = ' . $value), 0, 1);

                return (bool) $db->loadResult();
            };

            if ($exists('#__extensions', 'extension_id', $linkExtension) && $exists('#__update_sites', 'update_site_id', $linkSite)) {
                $object = (object) $link;
                $db->insertObject('#__update_sites_extensions', $object);
            }
        }
    }

    /**
     * Only columns that really exist in the table are accepted, and only scalar values.
     *
     * @return array<string, scalar|null>
     */
    private function checkedRow(string $table, mixed $row): array
    {
        if (!is_array($row) || !$row) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
        }

        $columns = $this->db()->getTableColumns($table);
        foreach ($row as $column => $value) {
            if (!array_key_exists((string) $column, $columns) || (!is_scalar($value) && $value !== null)) {
                throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_BACKUP_INVALID'));
            }
        }

        return $row;
    }

    private function db(): DatabaseInterface
    {
        return $this->getDatabase();
    }
}
