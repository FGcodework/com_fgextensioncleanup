<?php
use FGcodework\Component\FgExtensionCleanup\Administrator\Service\BackupStore;

$payload = ['kind' => 'extension', 'extensions' => [['extension_id' => '105', 'name' => 'FOF', 'type' => 'library', 'element' => 'fof']]];

test('BackupStore: write, list and read round trip', function () use ($payload) {
    fresh();
    $store = new BackupStore();
    $relative = $store->write('library', 105, $payload);
    ok(str_starts_with($relative, 'administrator/logs/fgextensioncleanup_backup_library_105_'), $relative);
    ok(is_file(JPATH_ROOT . '/' . $relative));
    $list = $store->listBackups();
    same(1, count($list));
    same('extension', $list[0]['kind']);
    same('FOF', $list[0]['name']);
    same(105, $list[0]['id']);
    same('FOF', $store->read(basename($relative))['extensions'][0]['name']);
});

test('BackupStore: file is private (0600)', function () use ($payload) {
    if (DIRECTORY_SEPARATOR === '\\') { return; }   // permissions are meaningless on Windows
    fresh();
    $relative = (new BackupStore())->write('library', 105, $payload);
    same(0600, fileperms(JPATH_ROOT . '/' . $relative) & 0777);
});

test('BackupStore: names from the browser are accepted only in the exact backup pattern', function () use ($payload) {
    fresh();
    $store = new BackupStore();
    $good = basename($store->write('library', 105, $payload));
    foreach (['../../etc/passwd', 'x/' . $good, $good . '.restored', 'fgextensioncleanup_backup_library_1_20260101_000000_zzzzzzzzzzzz.json', '', '..'] as $bad) {
        throws(fn () => $store->read($bad));
        throws(fn () => $store->delete($bad));
    }
});

test('BackupStore: unwritable or missing folder aborts instead of pretending', function () use ($payload) {
    fresh();
    \Joomla\CMS\Factory::$logPath = JPATH_ROOT . '/does-not-exist';
    throws(fn () => (new BackupStore())->write('library', 105, $payload), 'backup');
});

test('BackupStore: restored backups disappear from the list but stay on disk', function () use ($payload) {
    fresh();
    $store = new BackupStore();
    $file = basename($store->write('library', 105, $payload));
    $store->markRestored($file);
    same(0, count($store->listBackups()));
    ok(is_file(JPATH_ROOT . '/administrator/logs/' . $file . '.restored'));
});

test('BackupStore: a corrupt file is listed as invalid and cannot be read', function () {
    fresh();
    put('administrator/logs/fgextensioncleanup_backup_plugin_5_20260101_000000_abcdefabcdef.json', '{not json');
    $list = (new BackupStore())->listBackups();
    same(false, $list[0]['valid']);
    throws(fn () => (new BackupStore())->read('fgextensioncleanup_backup_plugin_5_20260101_000000_abcdefabcdef.json'));
});
