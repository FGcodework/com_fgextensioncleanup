<?php
namespace FGcodework\Component\FgExtensionCleanup\Administrator\Model;
defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use FGcodework\Component\FgExtensionCleanup\Administrator\Service\BackupStore;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Menu as MenuTable;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;
use RuntimeException;
use SimpleXMLElement;

final class CleanupModel extends BaseDatabaseModel
{
    private const SUPPORTED_TYPES = ['plugin', 'component', 'module', 'library', 'package', 'template', 'language', 'file'];

    public function getScan(): array
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('extension_id'),
                $db->quoteName('name'),
                $db->quoteName('element'),
                $db->quoteName('folder'),
                $db->quoteName('enabled'),
                $db->quoteName('protected'),
                $db->quoteName('type'),
                $db->quoteName('client_id'),
                $db->quoteName('package_id'),
            ])
            ->from($db->quoteName('#__extensions'))
            ->order([$db->quoteName('type') . ' ASC', $db->quoteName('name') . ' ASC', $db->quoteName('element') . ' ASC']);
        $db->setQuery($query);

        return $this->buildScan($db->loadObjectList());
    }

    /**
     * Analyse already loaded #__extensions records. Joomla core extensions (as defined by
     * ExtensionHelper) are counted separately and never contribute to the orphan/repair counters.
     *
     * @param   object[]  $records
     */
    public function buildScan(array $records): array
    {
        $counts = ['ok' => 0, 'repairable' => 0, 'ambiguous' => 0, 'orphan' => 0, 'other' => 0, 'core' => 0, 'core_findings' => 0];
        $typeCounts = [];
        $packageChildren = [];

        foreach ($records as $record) {
            $type = (string) ($record->type ?? 'unknown');
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

            $parentId = (int) ($record->package_id ?? 0);
            if ($parentId > 0) {
                $packageChildren[$parentId][] = $record;
            }
        }

        $rows = [];
        foreach ($records as $record) {
            $type = (string) ($record->type ?? '');

            if ($type === 'plugin') {
                $analysis = $this->analysePluginRecord($record);
            } elseif (in_array($type, self::SUPPORTED_TYPES, true)) {
                $analysis = $this->analyseStandardRecord($record, $records, $packageChildren[(int) $record->extension_id] ?? []);
            } else {
                $analysis = $this->result(
                    'unsupported',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_UNSUPPORTED_L'),
                    Text::_('COM_FGEXTENSIONCLEANUP_R_UNSUPPORTED_D1')
                );
            }

            $core = $this->isCoreExtension($record);
            $rows[] = ['record' => $record, 'analysis' => $analysis, 'core' => $core];

            if ($core) {
                $counts['core']++;
                if ($analysis['status'] !== 'ok') {
                    $counts['core_findings']++;
                }
                continue;
            }

            $bucket = in_array($analysis['status'], ['ok', 'repairable', 'ambiguous', 'orphan'], true)
                ? $analysis['status']
                : 'other';
            $counts[$bucket]++;
        }

        ksort($typeCounts);
        return ['rows' => $rows, 'counts' => $counts, 'type_counts' => $typeCounts];
    }

    /** @return array{id: int, name: string, element: string, type: string, folder: string} the repaired record (for the action log) */
    public function repairFolder(int $id): array
    {
        $record = $this->getPluginRecord($id);
        $analysis = $this->analysePluginRecord($record);

        if ($analysis['status'] !== 'repairable' || empty($analysis['repair'])) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_REPAIR_NOT_CONFIRMED'));
        }

        $db = $this->db();
        $emptyFolderCondition = '(' . $db->quoteName('folder')
            . ' IS NULL OR ' . $db->quoteName('folder') . ' = ' . $db->quote('') . ')';

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('folder') . ' = ' . $db->quote($analysis['repair']))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $record->extension_id)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($emptyFolderCondition)
            ->where($db->quoteName('element') . ' = ' . $db->quote((string) $record->element));
        $db->setQuery($query)->execute();

        if ($db->getAffectedRows() !== 1) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_REPAIR_NOT_CHANGED'));
        }

        return $this->recordSummary($record) + ['folder' => (string) $analysis['repair']];
    }

    /**
     * Remove only the #__extensions registration record (plus its #__schemas / #__update_sites_extensions rows)
     * of a confirmed orphaned plugin or library. The analysis is recomputed here, so the decision never
     * depends on what the browser submitted. A JSON backup of every row that is deleted is written first;
     * if the backup cannot be written nothing is removed.
     *
     * @return array{id: int, name: string, element: string, type: string, backup: string}
     */
    public function removeOrphanRecord(int $id): array
    {
        $record = $this->getRecord($id, ['plugin', 'library', 'component']);
        $type = (string) $record->type;

        $analysis = $type === 'plugin'
            ? $this->analysePluginRecord($record)
            : $this->analyseStandardRecord($record, [], []);

        if ($analysis['status'] !== 'orphan' || !$analysis['removable'] || !empty($record->protected) || $this->isCoreExtension($record)) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_REMOVE_NOT_CONFIRMED'));
        }

        $db = $this->db();
        $extensionId = (int) $record->extension_id;
        $backup = $this->writeBackup($record);

        $db->transactionStart();

        try {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__extensions'))
                ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
                ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                ->where('(' . $db->quoteName('protected') . ' = 0 OR ' . $db->quoteName('protected') . ' IS NULL)');
            $db->setQuery($query)->execute();

            if ($db->getAffectedRows() !== 1) {
                throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_REMOVE_NOT_DELETED'));
            }

            // Leftovers keyed by extension_id which the standard uninstaller would normally remove.
            foreach (['#__schemas', '#__update_sites_extensions'] as $table) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName('extension_id') . ' = ' . $extensionId);
                $db->setQuery($query)->execute();
            }

            if ($type === 'component') {
                $this->removeAdminMenuEntries($extensionId);
            }

            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();
            // Nothing was deleted, so the backup would only be misleading.
            @unlink(JPATH_ROOT . '/' . $backup);

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), 0, $exception);
        }

        return $this->recordSummary($record) + ['backup' => $backup];
    }

    private function recordSummary(object $record): array
    {
        return [
            'id' => (int) $record->extension_id,
            'name' => (string) $record->name,
            'element' => (string) $record->element,
            'type' => (string) $record->type,
        ];
    }

    /**
     * A component record may be removed when the component is in no way present on disk (site, administrator and API
     * directory all confirmed missing), is not Joomla core, not protected and not part of a package.
     *
     * @return array{removable: bool, note: string}
     */
    private function componentRemovalCheck(object $record): array
    {
        if (!empty($record->protected) || $this->isCoreExtension($record)) {
            return ['removable' => false, 'note' => ''];
        }

        $element = (string) ($record->element ?? '');
        if (!$this->isSafeSegment($element)) {
            return ['removable' => false, 'note' => ''];
        }

        if ((int) ($record->package_id ?? 0) > 0) {
            return ['removable' => false, 'note' => Text::_('COM_FGEXTENSIONCLEANUP_R_COMP_NOTE_PACKAGE')];
        }

        foreach ([['components'], ['administrator', 'components'], ['api', 'components']] as $base) {
            $relative = implode('/', array_merge($base, [$element]));
            $path = $this->inspectDirectoryChain(array_merge($base, [$element]), $relative);

            if ($path['status'] !== 'missing') {
                return ['removable' => false, 'note' => Text::sprintf('COM_FGEXTENSIONCLEANUP_R_LIB_NOTE_FILES', $relative)];
            }
        }

        return ['removable' => true, 'note' => ''];
    }

    /** Administrator menu entries of a component, removed the way Joomla's own uninstaller does it. */
    private function removeAdminMenuEntries(int $extensionId): void
    {
        $db = $this->db();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('component_id') . ' = ' . $extensionId)
                ->order($db->quoteName('level') . ' DESC')
        );
        $ids = $db->loadColumn() ?: [];

        if (!$ids) {
            return;
        }

        $table = new MenuTable($db);
        foreach ($ids as $menuId) {
            $table->delete((int) $menuId);   // an already removed child simply returns false
        }
        $table->rebuild();
    }

    /** Store the complete rows that are about to be deleted (see BackupStore). @return string path relative to the site root */
    private function writeBackup(object $record): string
    {
        $db = $this->db();
        $id = (int) $record->extension_id;

        $fetch = function (string $table, string $column) use ($db, $id): array {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName($table))
                ->where($db->quoteName($column) . ' = ' . $id);
            $db->setQuery($query);

            return $db->loadAssocList() ?: [];
        };

        $fetchMenu = function () use ($db, $id): array {
            $db->setQuery(
                $db->getQuery(true)->select('*')->from($db->quoteName('#__menu'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('component_id') . ' = ' . $id)
            );

            return $db->loadAssocList() ?: [];
        };

        return (new BackupStore())->write((string) $record->type, $id, [
            'kind' => 'extension',
            'note' => 'Rows deleted from the database. Restore them from FG Extension Cleanup or re-insert them with their original column names.',
            'extensions' => $fetch('#__extensions', 'extension_id'),
            'schemas' => $fetch('#__schemas', 'extension_id'),
            'update_sites_extensions' => $fetch('#__update_sites_extensions', 'extension_id'),
            'admin_menu' => (string) $record->type === 'component' ? $fetchMenu() : [],
        ]);
    }

    /**
     * Decide whether the record of an orphaned library may be removed. Conservative: any doubt means "no".
     *
     * @return array{removable: bool, note: string}  note is an explanation when removal is not allowed
     */
    private function libraryRemovalCheck(object $record): array
    {
        if (!empty($record->protected) || $this->isCoreExtension($record)) {
            return ['removable' => false, 'note' => ''];
        }

        if ((int) ($record->package_id ?? 0) > 0) {
            return ['removable' => false, 'note' => Text::_('COM_FGEXTENSIONCLEANUP_R_LIB_NOTE_PACKAGE')];
        }

        $element = (string) ($record->element ?? '');
        $segments = $this->safeRelativeSegments($element);
        if ($segments === null) {
            return ['removable' => false, 'note' => ''];
        }

        // The library files must be confirmed as absent, not merely unreadable.
        $directory = $this->inspectDirectoryChain(array_merge(['libraries'], $segments), 'libraries/' . implode('/', $segments));
        if ($directory['status'] !== 'missing') {
            return ['removable' => false, 'note' => Text::sprintf('COM_FGEXTENSIONCLEANUP_R_LIB_NOTE_FILES', 'libraries/' . implode('/', $segments))];
        }

        // No other registered extension may mention the library (manifest cache or parameters).
        $db = $this->db();
        $like = static fn (string $pattern): string => $db->quote('%' . $db->escape($pattern, true) . '%', false);
        $patterns = ['"' . $element . '"', 'lib_' . $element, 'libraries/' . $element];
        $conditions = [];
        foreach ($patterns as $pattern) {
            $conditions[] = $db->quoteName('manifest_cache') . ' LIKE ' . $like($pattern);
            $conditions[] = $db->quoteName('params') . ' LIKE ' . $like($pattern);
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('name'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' <> ' . (int) $record->extension_id)
            ->where('(' . implode(' OR ', $conditions) . ')');
        $db->setQuery($query, 0, 5);
        $names = $db->loadColumn();

        if (!empty($names)) {
            return ['removable' => false, 'note' => Text::sprintf('COM_FGEXTENSIONCLEANUP_R_LIB_NOTE_REF', implode(', ', $names))];
        }

        return ['removable' => true, 'note' => ''];
    }

    /** True for extensions that ship with Joomla itself (the list is maintained by Joomla, so it follows the running version). */
    private function isCoreExtension(object $record): bool
    {
        $type = (string) ($record->type ?? '');

        return ExtensionHelper::checkIfCoreExtension(
            $type,
            (string) ($record->element ?? ''),
            (int) ($record->client_id ?? 0),
            $type === 'plugin' ? (string) ($record->folder ?? '') : ''
        );
    }

    private function db(): DatabaseInterface
    {
        return $this->getDatabase();
    }

    private function getPluginRecord(int $id): object
    {
        return $this->getRecord($id, ['plugin']);
    }

    /** @param string[] $types */
    private function getRecord(int $id, array $types): object
    {
        if ($id < 1) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_INVALID_ID'));
        }

        $db = $this->db();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('extension_id'),
                $db->quoteName('name'),
                $db->quoteName('element'),
                $db->quoteName('folder'),
                $db->quoteName('enabled'),
                $db->quoteName('protected'),
                $db->quoteName('type'),
                $db->quoteName('client_id'),
                $db->quoteName('package_id'),
            ])
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . $id)
            ->where($db->quoteName('type') . ' IN (' . implode(',', array_map([$db, 'quote'], $types)) . ')');
        $db->setQuery($query);
        $record = $db->loadObject();

        if (!$record) {
            throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_PLUGIN_NOT_FOUND'));
        }

        return $record;
    }

    private function analysePluginRecord(object $record): array
    {
        $element = (string) ($record->element ?? '');
        $folder = trim((string) ($record->folder ?? ''));

        if (!$this->isSafeSegment($element)) {
            return $this->result('unsafe', Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_ELEMENT_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_ELEMENT_D'));
        }

        if ($folder === '') {
            $scan = $this->findPluginCandidates($element);

            if (!empty($scan['blocked'])) {
                return $this->result(
                    'unsafe',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_SYMLINK_L'),
                    Text::_('COM_FGEXTENSIONCLEANUP_R_SYMLINK_D'),
                    null,
                    false,
                    $scan['candidates'],
                    $scan['blocked_paths']
                );
            }

            if (!empty($scan['incomplete'])) {
                return $this->result(
                    'scan_error',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_SCAN_INCOMPLETE_L'),
                    Text::_('COM_FGEXTENSIONCLEANUP_R_SCAN_INCOMPLETE_D'),
                    null,
                    false,
                    $scan['candidates'],
                    $scan['blocked_paths']
                );
            }

            $directoryCount = count($scan['candidates']);
            $validCandidates = array_values(array_filter($scan['candidates'], static fn (array $candidate): bool => $candidate['valid']));

            if ($directoryCount === 1 && count($validCandidates) === 1) {
                $group = $validCandidates[0]['group'];
                return $this->result(
                    'repairable',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_REPAIRABLE_L'),
                    Text::sprintf('COM_FGEXTENSIONCLEANUP_R_REPAIRABLE_D', $group),
                    $group,
                    false,
                    $scan['candidates']
                );
            }

            if ($directoryCount > 1) {
                return $this->result(
                    'ambiguous',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_AMBIGUOUS_L'),
                    Text::sprintf('COM_FGEXTENSIONCLEANUP_R_AMBIGUOUS_D', $directoryCount),
                    null,
                    false,
                    $scan['candidates']
                );
            }

            if ($directoryCount === 1) {
                return $this->result(
                    'manifest_missing',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_DIR_NO_MANIFEST_L'),
                    Text::_('COM_FGEXTENSIONCLEANUP_R_DIR_NO_MANIFEST_D'),
                    null,
                    false,
                    $scan['candidates']
                );
            }

            return $this->result(
                'orphan',
                Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_NODIR_L'),
                Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_NODIR_D', $element),
                null,
                empty($record->protected) && !$this->isCoreExtension($record)
            );
        }

        if (!$this->isSafeSegment($folder)) {
            return $this->result('unsafe', Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_FOLDER_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_FOLDER_D'));
        }

        $root = $this->inspectDirectoryChain(['plugins'], 'plugins');
        if ($root['status'] !== 'ok') {
            return $this->pathFailureResult($root, Text::_('COM_FGEXTENSIONCLEANUP_P_ROOT'));
        }

        $groupPath = $this->inspectDirectoryChain(['plugins', $folder], 'plugins/' . $folder);
        if ($groupPath['status'] !== 'ok') {
            if ($groupPath['status'] === 'missing') {
                return $this->result('orphan', Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_GROUP_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_GROUP_D', $folder));
            }
            return $this->pathFailureResult($groupPath, Text::_('COM_FGEXTENSIONCLEANUP_P_GROUP'));
        }

        $pluginPath = $this->inspectDirectoryChain(['plugins', $folder, $element], 'plugins/' . $folder . '/' . $element);
        if ($pluginPath['status'] !== 'ok') {
            if ($pluginPath['status'] === 'missing') {
                return $this->result('orphan', Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_DIR_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_DIR_D', $folder, $element));
            }
            return $this->pathFailureResult($pluginPath, Text::_('COM_FGEXTENSIONCLEANUP_P_PLUGIN'));
        }

        $manifest = $this->inspectPluginManifest($pluginPath['absolute'], $folder, $element);
        $candidate = $this->pluginCandidateInfo($folder, $element, $manifest);
        if (!$manifest['valid']) {
            return $this->result(
                'manifest_missing',
                Text::_('COM_FGEXTENSIONCLEANUP_R_MANIFEST_AMBIG_L'),
                Text::_('COM_FGEXTENSIONCLEANUP_R_MANIFEST_AMBIG_D'),
                null,
                false,
                [$candidate]
            );
        }

        return $this->result('ok', Text::_('COM_FGEXTENSIONCLEANUP_R_OK_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_PLUGIN_D', $folder, $element), null, false, [$candidate]);
    }

    /** Diagnose component, module, library, package, template, language and file extensions. */
    private function analyseStandardRecord(object $record, array $allRecords, array $packageChildren): array
    {
        $type = (string) ($record->type ?? '');
        $element = (string) ($record->element ?? '');

        $validElement = $type === 'library'
            ? $this->safeRelativeSegments($element) !== null
            : $this->isSafeSegment($element);
        if (!$validElement) {
            return $this->result('unsafe', Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_ID_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_ID_D'));
        }

        if (in_array($type, ['component', 'module', 'template', 'language'], true)) {
            $clientId = (int) ($record->client_id ?? 0);
            // Components and language packs can also target Joomla's API client.
            $allowedClients = in_array($type, ['component', 'language'], true) ? [0, 1, 3] : [0, 1];
            if (!in_array($clientId, $allowedClients, true)) {
                return $this->result('unsafe', Text::_('COM_FGEXTENSIONCLEANUP_R_BAD_CLIENT_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_BAD_CLIENT_D', $clientId));
            }

            $spec = $this->directoryExtensionSpec($record);
            $path = $this->inspectDirectoryChain($spec['segments'], $spec['directory']);

            if ($path['status'] !== 'ok') {
                if ($path['status'] === 'missing') {
                    $detail = Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_EXTDIR_D', $spec['directory']);
                    if ($type !== 'component') {
                        return $this->result('orphan', Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_DIR_L'), $detail);
                    }

                    $check = $this->componentRemovalCheck($record);

                    return $this->result(
                        'orphan',
                        Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_DIR_L'),
                        $check['note'] !== '' ? $detail . ' ' . $check['note'] : $detail,
                        null,
                        $check['removable']
                    );
                }
                return $this->pathFailureResult($path, Text::_('COM_FGEXTENSIONCLEANUP_P_EXT'));
            }

            $manifestResult = $this->inspectDirectoryManifests(
                $path['absolute'],
                $spec['directory'],
                $type,
                $spec['manifest_name'],
                $spec['accepted_names'],
                $spec['client_name'],
                in_array($type, ['template', 'language'], true)
            );
            $candidate = [
                'directory' => $spec['directory'] . '/',
                'valid' => $manifestResult['valid'],
                'valid_count' => $manifestResult['valid_count'],
                'manifests' => $manifestResult['files'],
            ];

            if ($manifestResult['valid_count'] === 0) {
                return $this->result(
                    'manifest_missing',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_MANIFEST_MISSING_L'),
                    Text::sprintf('COM_FGEXTENSIONCLEANUP_R_MANIFEST_MISSING_D', $type),
                    null,
                    false,
                    [$candidate]
                );
            }

            if ($manifestResult['valid_count'] > 1) {
                return $this->result(
                    'manifest_missing',
                    Text::_('COM_FGEXTENSIONCLEANUP_R_MANIFEST_MULTI_L'),
                    Text::sprintf('COM_FGEXTENSIONCLEANUP_R_MANIFEST_MULTI_D', $type),
                    null,
                    false,
                    [$candidate]
                );
            }

            $detail = Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_STD_D', $spec['directory']);
            foreach ($manifestResult['files'] as $manifestFile) {
                if (!empty($manifestFile['valid']) && isset($manifestFile['standard']) && $manifestFile['standard'] === false) {
                    $detail .= ' ' . Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_NONSTD', basename((string) $manifestFile['path']));
                }
            }

            return $this->result('ok', Text::_('COM_FGEXTENSIONCLEANUP_R_OK_L'), $detail, null, false, [$candidate]);
        }

        if ($type === 'library' || $type === 'package' || $type === 'file') {
            $manifestSpec = $this->manifestExtensionSpec($type, $element);
            $manifestDir = $this->inspectDirectoryChain($manifestSpec['segments'], $manifestSpec['directory']);
            if ($manifestDir['status'] !== 'ok') {
                if ($manifestDir['status'] === 'missing') {
                    return $this->libraryOrphanResult($record, 'COM_FGEXTENSIONCLEANUP_R_ORPHAN_MANDIR_L', Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_MANDIR_D', $manifestSpec['directory']));
                }
                return $this->pathFailureResult($manifestDir, Text::_('COM_FGEXTENSIONCLEANUP_P_MANDIR'));
            }

            $manifestPath = $manifestDir['absolute'] . '/' . $manifestSpec['filename'];
            $manifestRelative = $manifestSpec['directory'] . '/' . $manifestSpec['filename'];
            $manifestStatus = $this->inspectRegularFile($manifestPath);

            if ($manifestStatus['status'] === 'missing') {
                return $this->libraryOrphanResult($record, 'COM_FGEXTENSIONCLEANUP_R_ORPHAN_MAN_L', Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_MAN_D', $manifestRelative));
            }
            if ($manifestStatus['status'] !== 'ok') {
                return $this->pathFailureResult($manifestStatus, Text::_('COM_FGEXTENSIONCLEANUP_P_MAN'));
            }

            $parsed = $this->parseManifest($manifestPath, $manifestRelative, $type, null);
            if (!$parsed['valid']) {
                return $this->result('manifest_missing', Text::_('COM_FGEXTENSIONCLEANUP_R_MANIFEST_INVALID_L'), $parsed['reason'], null, false, [[
                    'directory' => $manifestSpec['directory'] . '/',
                    'valid' => false,
                    'valid_count' => 0,
                    'manifests' => [$parsed['file']],
                ]]);
            }

            if ($type === 'package') {
                $packageName = trim((string) ($parsed['xml']->packagename ?? ''));
                if (!$this->isSafeSegment($packageName)) {
                    return $this->result('manifest_missing', Text::_('COM_FGEXTENSIONCLEANUP_R_PKG_NAME_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_PKG_NAME_D'), null, false, [[
                        'directory' => $manifestSpec['directory'] . '/',
                        'valid' => false,
                        'valid_count' => 0,
                        'manifests' => [['path' => $manifestRelative, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_PKG_NAME')]],
                    ]]);
                }
                if (strcasecmp($element, 'pkg_' . $packageName) !== 0) {
                    return $this->result('manifest_missing', Text::_('COM_FGEXTENSIONCLEANUP_R_PKG_MISMATCH_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_PKG_MISMATCH_D', $element, $packageName), null, false, [[
                        'directory' => $manifestSpec['directory'] . '/',
                        'valid' => false,
                        'valid_count' => 0,
                        'manifests' => [['path' => $manifestRelative, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_PKG_MISMATCH')]],
                    ]]);
                }
            }

            if ($type === 'library') {
                $libraryName = trim((string) ($parsed['xml']->libraryname ?? ''));
                $librarySegments = $this->safeRelativeSegments($libraryName);
                if ($librarySegments === null) {
                    return $this->result('unsafe', Text::_('COM_FGEXTENSIONCLEANUP_R_LIB_PATH_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_LIB_PATH_D'));
                }
                if (strcasecmp($libraryName, $element) !== 0) {
                    return $this->result('manifest_missing', Text::_('COM_FGEXTENSIONCLEANUP_R_LIB_MISMATCH_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_LIB_MISMATCH_D', $libraryName, $element), null, false, [[
                        'directory' => $manifestSpec['directory'] . '/',
                        'valid' => false,
                        'valid_count' => 0,
                        'manifests' => [['path' => $manifestRelative, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_LIB_MISMATCH')]],
                    ]]);
                }
                $libraryRelative = 'libraries/' . implode('/', $librarySegments);
                $checkLibraryDirectory = !$this->isCoreExtension($record);
                if ($checkLibraryDirectory) {
                    $libraryPath = $this->inspectDirectoryChain(array_merge(['libraries'], $librarySegments), $libraryRelative);
                    if ($libraryPath['status'] === 'missing') {
                        return $this->result('orphan', Text::_('COM_FGEXTENSIONCLEANUP_R_ORPHAN_LIB_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_ORPHAN_LIB_D', $libraryRelative));
                    }
                    if ($libraryPath['status'] !== 'ok') {
                        return $this->pathFailureResult($libraryPath, Text::_('COM_FGEXTENSIONCLEANUP_P_LIB'));
                    }
                }
            }

            $candidate = [
                'directory' => $manifestSpec['directory'] . '/',
                'valid' => true,
                'valid_count' => 1,
                'manifests' => [$parsed['file']],
            ];
            $analysis = $this->result('ok', Text::_('COM_FGEXTENSIONCLEANUP_R_OK_L'), Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_MANIFEST_D', $type, $manifestRelative), null, false, [$candidate]);

            if ($type === 'library') {
                if (!empty($checkLibraryDirectory)) {
                    $analysis['detail'] .= ' ' . Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_LIB_APPEND', $libraryRelative);
                }
            } elseif ($type === 'package') {
                $analysis['package'] = $this->packageDiagnostics($parsed['xml'], (int) $record->extension_id, $allRecords, $packageChildren);
                $declared = count($analysis['package']['declared']);
                $registered = count($analysis['package']['registered']);
                $analysis['detail'] .= ' ' . Text::sprintf('COM_FGEXTENSIONCLEANUP_R_OK_PKG_APPEND', $declared, $registered);
            }

            return $analysis;
        }

        return $this->result('unsupported', Text::_('COM_FGEXTENSIONCLEANUP_R_UNSUPPORTED_L'), Text::_('COM_FGEXTENSIONCLEANUP_R_UNSUPPORTED_D2'));
    }

    /** Orphan result for library/package/file records; only libraries can be offered for removal. */
    private function libraryOrphanResult(object $record, string $labelKey, string $detail): array
    {
        if ((string) ($record->type ?? '') !== 'library') {
            return $this->result('orphan', Text::_($labelKey), $detail);
        }

        $check = $this->libraryRemovalCheck($record);
        if ($check['note'] !== '') {
            $detail .= ' ' . $check['note'];
        }

        return $this->result('orphan', Text::_($labelKey), $detail, null, $check['removable']);
    }

    private function directoryExtensionSpec(object $record): array
    {
        $type = (string) $record->type;
        $element = (string) $record->element;
        $clientId = (int) ($record->client_id ?? 0);
        $clientName = match ($clientId) {
            1 => 'administrator',
            3 => 'api',
            default => 'site',
        };

        switch ($type) {
            case 'component':
                $componentRoots = [
                    0 => ['components'],
                    1 => ['administrator', 'components'],
                    3 => ['api', 'components'],
                ];
                $base = $componentRoots[$clientId] ?? ['administrator', 'components'];
                return [
                    'segments' => array_merge($base, [$element]),
                    'directory' => implode('/', array_merge($base, [$element])),
                    'manifest_name' => null,
                    'accepted_names' => array_values(array_unique([$element, str_starts_with($element, 'com_') ? substr($element, 4) : $element])),
                    'client_name' => null,
                ];
            case 'module':
                $base = $clientId === 1 ? ['administrator', 'modules'] : ['modules'];
                $prefix = $clientId === 1 ? 'administrator/modules/' : 'modules/';
                return [
                    'segments' => array_merge($base, [$element]),
                    'directory' => $prefix . $element,
                    'manifest_name' => $element . '.xml',
                    'accepted_names' => [$element],
                    'client_name' => $clientName,
                ];
            case 'template':
                $base = $clientId === 1 ? ['administrator', 'templates'] : ['templates'];
                $prefix = $clientId === 1 ? 'administrator/templates/' : 'templates/';
                return [
                    'segments' => array_merge($base, [$element]),
                    'directory' => $prefix . $element,
                    'manifest_name' => 'templateDetails.xml',
                    'accepted_names' => ['templateDetails'],
                    'client_name' => $clientName,
                ];
            case 'language':
                $languageRoots = [
                    0 => ['language'],
                    1 => ['administrator', 'language'],
                    3 => ['api', 'language'],
                ];
                $base = $languageRoots[$clientId] ?? ['language'];
                $prefix = implode('/', $base) . '/';
                return [
                    'segments' => array_merge($base, [$element]),
                    'directory' => $prefix . $element,
                    'manifest_name' => 'install.xml',
                    'accepted_names' => ['install'],
                    'client_name' => $clientName,
                ];
        }

        throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_DIR_TYPE'));
    }

    private function manifestExtensionSpec(string $type, string $element): array
    {
        if ($type === 'library') {
            // Joomla library elements are libraryname values and may be nested,
            // e.g. vendor/library. The manifest is stored at the matching path
            // below administrator/manifests/libraries.
            $elementSegments = $this->safeRelativeSegments($element);
            if ($elementSegments === null) {
                throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_LIB_PATH'));
            }

            $filename = array_pop($elementSegments) . '.xml';
            $segments = array_merge(['administrator', 'manifests', 'libraries'], $elementSegments);
        } else {
            $map = [
                'package' => ['administrator', 'manifests', 'packages'],
                'file' => ['administrator', 'manifests', 'files'],
            ];
            $segments = $map[$type] ?? null;
            if ($segments === null) {
                throw new RuntimeException(Text::_('COM_FGEXTENSIONCLEANUP_ERR_MANIFEST_TYPE'));
            }
            $filename = $element . '.xml';
        }

        $relative = implode('/', $segments);
        return [
            'segments' => $segments,
            'directory' => $relative,
            'filename' => $filename,
        ];
    }

    private function inspectDirectoryChain(array $segments, string $relative): array
    {
        $path = JPATH_ROOT;
        $current = '';

        foreach ($segments as $segment) {
            if (!$this->isSafeSegment((string) $segment)) {
                return ['status' => 'unsafe', 'absolute' => $path, 'relative' => $relative, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_UNSAFE_SEGMENT')];
            }

            // Probe only one level after its parent was already confirmed as a readable directory.
            $next = $path . '/' . $segment;
            $current = $current === '' ? (string) $segment : $current . '/' . $segment;

            if (@is_link($next)) {
                return ['status' => 'unsafe', 'absolute' => $next, 'relative' => $current, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_SYMLINK_PATH')];
            }
            if (!@is_dir($next)) {
                return ['status' => 'missing', 'absolute' => $next, 'relative' => $current, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_DIR_MISSING')];
            }
            if (!@is_readable($next)) {
                return ['status' => 'scan_error', 'absolute' => $next, 'relative' => $current, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_DIR_UNREADABLE')];
            }

            $path = $next;
        }

        return ['status' => 'ok', 'absolute' => $path, 'relative' => $relative, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_DIR_OK')];
    }

    private function inspectRegularFile(string $path): array
    {
        if (@is_link($path)) {
            return ['status' => 'unsafe', 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_FILE_SYMLINK')];
        }
        if (!@is_file($path)) {
            return ['status' => 'missing', 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_FILE_MISSING')];
        }
        if (!@is_readable($path)) {
            return ['status' => 'scan_error', 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_FILE_UNREADABLE')];
        }

        return ['status' => 'ok', 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_F_FILE_OK')];
    }

    private function inspectDirectoryManifests(
        string $absoluteDirectory,
        string $relativeDirectory,
        string $expectedType,
        ?string $manifestName,
        array $acceptedNames,
        ?string $expectedClient,
        bool $strictName = true
    ): array {
        $entries = @scandir($absoluteDirectory);
        if ($entries === false) {
            return ['valid' => false, 'valid_count' => 0, 'files' => []];
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $absoluteFile = $absoluteDirectory . '/' . $entry;
            $relativeFile = $relativeDirectory . '/' . $entry;
            $expectedName = $manifestName !== null
                ? strcasecmp($entry, $manifestName) === 0
                : in_array(strtolower(pathinfo($entry, PATHINFO_FILENAME)), array_map('strtolower', $acceptedNames), true);

            if (@is_link($absoluteFile)) {
                $files[] = ['path' => $relativeFile, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_XML_SYMLINK')];
                continue;
            }
            if (!@is_file($absoluteFile) || !@is_readable($absoluteFile)) {
                $files[] = ['path' => $relativeFile, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_XML_NOTREG')];
                continue;
            }

            $parsed = $this->parseManifest($absoluteFile, $relativeFile, $expectedType, $expectedClient);
            if ($parsed['valid'] && !$expectedName) {
                if ($strictName) {
                    $parsed['valid'] = false;
                    $parsed['file']['valid'] = false;
                    $parsed['file']['reason'] = Text::_('COM_FGEXTENSIONCLEANUP_M_NAME_CONVENTION');
                } else {
                    // Joomla keeps the file name of the manifest from the install package, so any name is possible.
                    $parsed['file']['standard'] = false;
                    $parsed['file']['reason'] = Text::_('COM_FGEXTENSIONCLEANUP_M_NONSTANDARD_NAME');
                }
            }
            $files[] = $parsed['file'];
        }

        $validCount = count(array_filter($files, static fn (array $file): bool => !empty($file['valid'])));
        return ['valid' => $validCount === 1, 'valid_count' => $validCount, 'files' => $files];
    }

    private function parseManifest(string $absoluteFile, string $relativeFile, string $expectedType, ?string $expectedClient): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = @simplexml_load_file($absoluteFile, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        $reason = '';
        $valid = false;

        if ($xml === false) {
            $reason = Text::_('COM_FGEXTENSIONCLEANUP_M_XML_SYNTAX');
            libxml_clear_errors();
        } elseif (strtolower($xml->getName()) !== 'extension') {
            $reason = Text::_('COM_FGEXTENSIONCLEANUP_M_ROOT');
        } else {
            $attributes = $xml->attributes();
            $type = strtolower(trim((string) ($attributes['type'] ?? '')));
            $client = strtolower(trim((string) ($attributes['client'] ?? '')));

            if ($type !== $expectedType) {
                $reason = Text::sprintf('COM_FGEXTENSIONCLEANUP_M_TYPE_MISMATCH', $expectedType, ($type !== '' ? $type : Text::_('COM_FGEXTENSIONCLEANUP_M_UNSPECIFIED')));
            } elseif ($expectedClient !== null && $client !== '' && $client !== $expectedClient) {
                $reason = Text::sprintf('COM_FGEXTENSIONCLEANUP_M_CLIENT_MISMATCH', $client, $expectedClient);
            } else {
                $valid = true;
                $reason = $client !== '' ? Text::sprintf('COM_FGEXTENSIONCLEANUP_M_VALID_CLIENT', $expectedType, $client) : Text::sprintf('COM_FGEXTENSIONCLEANUP_M_VALID', $expectedType);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return [
            'valid' => $valid,
            'xml' => $xml instanceof SimpleXMLElement ? $xml : null,
            'reason' => $reason,
            'file' => ['path' => $relativeFile, 'valid' => $valid, 'reason' => $reason],
        ];
    }

    private function packageDiagnostics(SimpleXMLElement $xml, int $packageId, array $allRecords, array $registeredChildren): array
    {
        $declared = [];
        foreach ($xml->files as $filesNode) {
            $constituents = [];

            // Package manifests normally declare files with <file>. Some Joomla
            // language packages use <folder type="language" ...> entries instead.
            foreach ($filesNode->file as $fileNode) {
                $constituents[] = ['node' => $fileNode, 'tag' => 'file'];
            }
            foreach ($filesNode->folder as $folderNode) {
                $folderAttributes = $folderNode->attributes();
                if (trim((string) ($folderAttributes['type'] ?? '')) !== '') {
                    $constituents[] = ['node' => $folderNode, 'tag' => 'folder'];
                }
            }

            foreach ($constituents as $constituent) {
                /** @var SimpleXMLElement $fileNode */
                $fileNode = $constituent['node'];
                $attributes = $fileNode->attributes();
                $type = strtolower(trim((string) ($attributes['type'] ?? '')));
                $id = trim((string) ($attributes['id'] ?? ''));
                $client = strtolower(trim((string) ($attributes['client'] ?? '')));
                $group = trim((string) ($attributes['group'] ?? ''));
                $filename = trim((string) $fileNode);
                $matches = [];
                $lookupAttributesComplete = $type !== '' && $id !== '';
                $clientMap = ['site' => 0, 'administrator' => 1, 'api' => 3];
                $clientRequired = in_array($type, ['module', 'template', 'language'], true);
                if ($clientRequired && !array_key_exists($client, $clientMap)) {
                    $lookupAttributesComplete = false;
                } elseif ($client !== '' && !array_key_exists($client, $clientMap)) {
                    $lookupAttributesComplete = false;
                }
                if ($type === 'plugin' && $group === '') {
                    $lookupAttributesComplete = false;
                }

                if ($lookupAttributesComplete) {
                    foreach ($allRecords as $candidate) {
                        if (strtolower((string) ($candidate->type ?? '')) !== $type) {
                            continue;
                        }
                        if (!$this->packageElementMatches($type, $id, (string) ($candidate->element ?? ''))) {
                            continue;
                        }
                        if ($client !== '' && (int) ($candidate->client_id ?? 0) !== $clientMap[$client]) {
                            continue;
                        }
                        if ($type === 'plugin' && strcasecmp($group, (string) ($candidate->folder ?? '')) !== 0) {
                            continue;
                        }
                        $matches[] = [
                            'extension_id' => (int) $candidate->extension_id,
                            'name' => (string) $candidate->name,
                            'element' => (string) $candidate->element,
                            'package_id' => (int) ($candidate->package_id ?? 0),
                            'belongs_to_package' => (int) ($candidate->package_id ?? 0) === $packageId,
                        ];
                    }
                }

                $declared[] = [
                    'tag' => $constituent['tag'],
                    'type' => $type !== '' ? $type : Text::_('COM_FGEXTENSIONCLEANUP_PKG_TYPE_NONE'),
                    'id' => $id !== '' ? $id : '—',
                    'client' => $client,
                    'group' => $group,
                    'filename' => $filename,
                    'matches' => $matches,
                    'match_reason' => !$lookupAttributesComplete
                        ? Text::_('COM_FGEXTENSIONCLEANUP_PKG_ATTR_INCOMPLETE')
                        : (empty($matches) ? Text::_('COM_FGEXTENSIONCLEANUP_PKG_NO_MATCH') : ''),
                ];
            }
        }

        $registered = [];
        foreach ($registeredChildren as $child) {
            $registered[] = [
                'extension_id' => (int) $child->extension_id,
                'type' => (string) $child->type,
                'name' => (string) $child->name,
                'element' => (string) $child->element,
                'folder' => (string) ($child->folder ?? ''),
                'client_id' => (int) ($child->client_id ?? 0),
            ];
        }

        return ['declared' => $declared, 'registered' => $registered];
    }

    private function packageElementMatches(string $type, string $manifestId, string $databaseElement): bool
    {
        // Joomla's package adapter looks up the child using the manifest's id
        // as the exact #__extensions.element value. Do not normalize prefixes:
        // doing so could incorrectly report a broken package reference as valid.
        return strcasecmp($manifestId, $databaseElement) === 0;
    }

    private function safeRelativeSegments(string $path): ?array
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, ':')) {
            return null;
        }

        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if (!$this->isSafeSegment($segment)) {
                return null;
            }
        }

        return $segments;
    }

    private function pathFailureResult(array $path, string $label): array
    {
        if (($path['status'] ?? '') === 'unsafe') {
            return $this->result(
                'unsafe',
                Text::_('COM_FGEXTENSIONCLEANUP_R_PATH_UNSAFE_L'),
                Text::sprintf('COM_FGEXTENSIONCLEANUP_R_PATH_UNSAFE_D', $label, $path['reason'] ?? Text::_('COM_FGEXTENSIONCLEANUP_PATH_NOT_SAFE'))
            );
        }

        return $this->result(
            'scan_error',
            Text::_('COM_FGEXTENSIONCLEANUP_R_PATH_INCOMPLETE_L'),
            Text::sprintf('COM_FGEXTENSIONCLEANUP_R_PATH_INCOMPLETE_D', $label, $path['reason'] ?? Text::_('COM_FGEXTENSIONCLEANUP_PATH_NOT_VERIFIED'))
        );
    }

    private function result(
        string $status,
        string $label,
        string $detail,
        ?string $repair = null,
        bool $removable = false,
        array $candidates = [],
        array $blockedPaths = []
    ): array {
        return [
            'status' => $status,
            'label' => $label,
            'detail' => $detail,
            'repair' => $repair,
            'removable' => $removable,
            'candidates' => $candidates,
            'blocked_paths' => $blockedPaths,
        ];
    }

    private function isSafeSegment(string $value): bool
    {
        return $value !== '' && $value !== '.' && $value !== '..' && preg_match('/^[A-Za-z0-9_.-]+$/D', $value) === 1;
    }

    /** Locate exact plugins/<group>/<element> directories and inspect their XML manifests. */
    private function findPluginCandidates(string $element): array
    {
        $result = ['candidates' => [], 'blocked' => false, 'incomplete' => false, 'blocked_paths' => []];
        $root = $this->inspectDirectoryChain(['plugins'], 'plugins');

        if ($root['status'] === 'unsafe') {
            $result['blocked'] = true;
            $result['blocked_paths'][] = 'plugins/';
            return $result;
        }
        if ($root['status'] !== 'ok') {
            $result['incomplete'] = true;
            return $result;
        }

        $groups = @scandir($root['absolute']);
        if ($groups === false) {
            $result['incomplete'] = true;
            return $result;
        }

        foreach ($groups as $group) {
            if (!$this->isSafeSegment($group)) {
                continue;
            }

            // First inspect the group entry itself. This avoids probing children of plugins/index.html.
            $groupPath = $this->inspectDirectoryChain(['plugins', $group], 'plugins/' . $group);
            if ($groupPath['status'] === 'unsafe') {
                $result['blocked'] = true;
                $result['blocked_paths'][] = 'plugins/' . $group;
                continue;
            }
            if ($groupPath['status'] === 'missing') {
                continue;
            }
            if ($groupPath['status'] !== 'ok') {
                $result['incomplete'] = true;
                continue;
            }

            $candidatePath = $this->inspectDirectoryChain(['plugins', $group, $element], 'plugins/' . $group . '/' . $element);
            if ($candidatePath['status'] === 'unsafe') {
                $result['blocked'] = true;
                $result['blocked_paths'][] = 'plugins/' . $group . '/' . $element;
                continue;
            }
            if ($candidatePath['status'] === 'missing') {
                continue;
            }
            if ($candidatePath['status'] !== 'ok') {
                $result['incomplete'] = true;
                continue;
            }

            $manifest = $this->inspectPluginManifest($candidatePath['absolute'], $group, $element);
            $result['candidates'][] = $this->pluginCandidateInfo($group, $element, $manifest);
        }

        return $result;
    }

    private function pluginCandidateInfo(string $group, string $element, array $manifest): array
    {
        return [
            'group' => $group,
            'directory' => 'plugins/' . $group . '/' . $element . '/',
            'valid' => (bool) $manifest['valid'],
            'valid_count' => (int) $manifest['valid_count'],
            'manifests' => $manifest['files'],
        ];
    }

    private function inspectPluginManifest(string $pluginDir, string $expectedGroup, string $element): array
    {
        $files = [];
        $entries = @scandir($pluginDir) ?: [];
        $previous = libxml_use_internal_errors(true);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'xml') {
                continue;
            }

            $xmlFile = $pluginDir . '/' . $entry;
            $relativePath = 'plugins/' . $expectedGroup . '/' . $element . '/' . $entry;
            if (@is_link($xmlFile)) {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_XML_SYMLINK')];
                continue;
            }
            if (!@is_file($xmlFile) || !@is_readable($xmlFile)) {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_XML_NOTREG')];
                continue;
            }

            $xml = @simplexml_load_file($xmlFile, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_XML_SYNTAX')];
                libxml_clear_errors();
                continue;
            }
            if (strtolower($xml->getName()) !== 'extension') {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_ROOT')];
                continue;
            }

            $attributes = $xml->attributes();
            $type = strtolower((string) ($attributes['type'] ?? ''));
            $group = trim((string) ($attributes['group'] ?? ''));
            if ($type !== 'plugin') {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::_('COM_FGEXTENSIONCLEANUP_M_TYPE_PLUGIN')];
                continue;
            }
            if ($group !== '' && strcasecmp($group, $expectedGroup) !== 0) {
                $files[] = ['path' => $relativePath, 'valid' => false, 'reason' => Text::sprintf('COM_FGEXTENSIONCLEANUP_M_GROUP_MISMATCH', $group, $expectedGroup)];
                continue;
            }

            $groupInfo = $group !== '' ? 'group=' . $group : Text::_('COM_FGEXTENSIONCLEANUP_M_GROUP_NONE');
            $files[] = ['path' => $relativePath, 'valid' => true, 'reason' => Text::sprintf('COM_FGEXTENSIONCLEANUP_M_VALID_PLUGIN', $groupInfo)];
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $validCount = count(array_filter($files, static fn (array $file): bool => $file['valid']));

        return ['valid' => $validCount === 1, 'valid_count' => $validCount, 'files' => $files];
    }
}
