<?php
use FGcodework\Component\FgExtensionCleanup\Administrator\Service\BackupStore;

function backupOf(array $extension, array $more = []): string
{
    return basename((new BackupStore())->write($extension['type'], (int) $extension['extension_id'], array_merge([
        'kind' => 'extension',
        'extensions' => [$extension],
        'schemas' => [['extension_id' => (string) $extension['extension_id'], 'version_id' => '1']],
        'update_sites_extensions' => [],
    ], $more)));
}

$fof = ['extension_id' => '105', 'name' => 'FOF', 'type' => 'library', 'element' => 'fof', 'folder' => '', 'client_id' => '0', 'params' => '{}'];

test('update sites: dangling, unlinked and Joomla-owned sites are found, healthy ones are not', function () {
    $db = fresh();
    $db->data['#__update_sites'] = [
        (object) ['update_site_id' => 1, 'name' => 'Healthy', 'type' => 'extension', 'location' => 'https://a', 'enabled' => 1],
        (object) ['update_site_id' => 2, 'name' => 'Dangling', 'type' => 'extension', 'location' => 'https://b', 'enabled' => 1],
        (object) ['update_site_id' => 3, 'name' => 'NoLink', 'type' => 'extension', 'location' => 'https://c', 'enabled' => 0],
        (object) ['update_site_id' => 4, 'name' => 'Joomla', 'type' => 'collection', 'location' => 'https://update.joomla.org/x', 'enabled' => 1],
    ];
    $db->data['#__update_sites_extensions'] = [
        (object) ['update_site_id' => 1, 'extension_id' => 10, 'existing_id' => 10],
        (object) ['update_site_id' => 2, 'extension_id' => 99, 'existing_id' => null],
    ];
    $orphans = model('MaintenanceModel')->getUpdateSites()['orphans'];
    same([2, 3, 4], array_column($orphans, 'id'));
    same([true, true, false], array_column($orphans, 'removable'));
});

test('update sites: a site is not orphaned while at least one linked extension exists', function () {
    $db = fresh();
    $db->data['#__update_sites'] = [(object) ['update_site_id' => 1, 'name' => 'Mixed', 'type' => 'extension', 'location' => 'https://a', 'enabled' => 1]];
    $db->data['#__update_sites_extensions'] = [
        (object) ['update_site_id' => 1, 'extension_id' => 10, 'existing_id' => 10],
        (object) ['update_site_id' => 1, 'extension_id' => 11, 'existing_id' => null],
    ];
    same([], model('MaintenanceModel')->getUpdateSites()['orphans']);
});

test('update sites: removing a non-orphan or Joomla site is refused', function () {
    $db = fresh();
    $db->data['#__update_sites'] = [(object) ['update_site_id' => 4, 'name' => 'Joomla', 'type' => 'collection', 'location' => 'https://update.joomla.org/x', 'enabled' => 1]];
    $db->data['#__update_sites_extensions'] = [];
    throws(fn () => model('MaintenanceModel')->removeUpdateSite(4));
    throws(fn () => model('MaintenanceModel')->removeUpdateSite(77));
    same([], $db->log);
});

test('restore: puts the rows back in one transaction and hides the backup', function () use ($fof) {
    $db = fresh();
    $file = backupOf($fof);
    $result = model('MaintenanceModel')->restoreBackup($file);
    same(105, $result['id']);
    same(false, $result['menu_skipped']);
    same(['INSERT #__extensions', 'INSERT #__schemas'], array_values(array_filter($db->log, fn ($l) => str_starts_with($l, 'INSERT'))));
    same(['tx start', 'INSERT #__extensions', 'INSERT #__schemas', 'tx commit'], $db->log);
    same([], (new BackupStore())->listBackups());
});

test('restore: refuses when the record already exists, rolls back, keeps the backup', function () use ($fof) {
    $db = fresh();
    $file = backupOf($fof);
    $db->existing = true;
    throws(fn () => model('MaintenanceModel')->restoreBackup($file), 'already exists');
    ok(in_array('tx rollback', $db->log, true));
    same([], $db->inserts);
    same(1, count((new BackupStore())->listBackups()));
});

test('restore: a tampered backup with an unknown column is rejected', function () {
    $db = fresh();
    $file = backupOf(['extension_id' => '5', 'name' => 'x', 'type' => 'plugin', 'element' => 'x', 'folder' => 'a', 'client_id' => '0', 'evil_column' => '1']);
    throws(fn () => model('MaintenanceModel')->restoreBackup($file), 'not valid');
    same([], $db->inserts);
});

test('restore: only plugin, library and component backups are accepted', function () {
    $db = fresh();
    $file = backupOf(['extension_id' => '6', 'name' => 't', 'type' => 'template', 'element' => 't', 'folder' => '', 'client_id' => '0']);
    throws(fn () => model('MaintenanceModel')->restoreBackup($file), 'not valid');
});

test('restore: component backups warn that admin menu entries are not restored', function () {
    $db = fresh();
    $file = backupOf(
        ['extension_id' => '7', 'name' => 'c', 'type' => 'component', 'element' => 'com_c', 'folder' => '', 'client_id' => '1'],
        ['admin_menu' => [['id' => '40', 'client_id' => '1']]]
    );
    same(true, model('MaintenanceModel')->restoreBackup($file)['menu_skipped']);
});

test('other orphans: report is limited and never deletes anything', function () {
    $db = fresh();
    $db->data['#__modules'] = [(object) ['id' => 5]];
    $db->data['#__menu'] = [(object) ['id' => 9]];
    $db->data['#__schemas'] = [(object) ['extension_id' => 77]];
    $report = model('MaintenanceModel')->getOtherOrphans();
    same([1, 1, 1], [count($report['modules']), count($report['menu']), count($report['schemas'])]);
    same([], array_filter($db->log, fn ($l) => str_starts_with($l, 'DELETE')));
});

test('restore: a backup of a blank record can be put back', function () {
    $db = fresh();
    $file = backupOf(['extension_id' => '305', 'name' => '', 'type' => '', 'element' => '', 'folder' => '', 'client_id' => '0']);
    same(305, model('MaintenanceModel')->restoreBackup($file)['id']);
});
