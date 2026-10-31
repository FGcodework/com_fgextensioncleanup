<?php
test('library: free orphan (no manifest, no files, not referenced) is removable', function () {
    fresh();
    $row = analyse(rec(['type' => 'library', 'element' => 'fof']));
    same('orphan', $row['analysis']['status']);
    same(true, $row['analysis']['removable']);
});

test('library: protected, packaged, still on disk or mentioned elsewhere is NOT removable', function () {
    $cases = [
        'protected' => function () { fresh(); return rec(['type' => 'library', 'element' => 'fof', 'protected' => 1]); },
        'in package' => function () { fresh(); return rec(['type' => 'library', 'element' => 'fof', 'package_id' => 7]); },
        'files on disk' => function () { fresh(); put('libraries/fof/index.html'); return rec(['type' => 'library', 'element' => 'fof']); },
        'referenced' => function () { $db = fresh(); $db->data['#__extensions'] = ['Akeeba Foo']; return rec(['type' => 'library', 'element' => 'fof']); },
    ];
    foreach ($cases as $label => $make) {
        $record = $make();
        same(false, analyse($record)['analysis']['removable'], $label);
    }
});

test('library: Joomla core libraries are never reported as orphans', function () {
    fresh();
    $row = analyse(rec(['type' => 'library', 'element' => 'joomla', 'protected' => 1]));
    same(true, $row['core']);
    same(false, $row['analysis']['removable']);
});

test('component: removable only when site, administrator and API directory are all missing', function () {
    fresh();
    same(true, analyse(rec(['type' => 'component', 'element' => 'com_gone', 'client_id' => 1]))['analysis']['removable']);
    fresh(); put('components/com_gone/index.html');
    same(false, analyse(rec(['type' => 'component', 'element' => 'com_gone', 'client_id' => 1]))['analysis']['removable']);
    fresh(); put('api/components/com_gone/index.html');
    same(false, analyse(rec(['type' => 'component', 'element' => 'com_gone', 'client_id' => 1]))['analysis']['removable']);
});

test('component: protected, packaged and core are never removable', function () {
    fresh();
    same(false, analyse(rec(['type' => 'component', 'element' => 'com_gone', 'client_id' => 1, 'protected' => 1]))['analysis']['removable']);
    same(false, analyse(rec(['type' => 'component', 'element' => 'com_gone', 'client_id' => 1, 'package_id' => 3]))['analysis']['removable']);
    $core = analyse(rec(['type' => 'component', 'element' => 'com_content', 'client_id' => 1]));
    same(true, $core['core']);
    same(false, $core['analysis']['removable']);
});

test('manifests: any file name is fine for components and modules when exactly one valid manifest exists', function () {
    fresh();
    put('administrator/components/com_adminer/manifest.xml', manifest('component'));
    put('administrator/components/com_adminer/access.xml', '<access/>');
    put('modules/mod_foo/custom.xml', manifest('module', 'client="site"'));
    $component = analyse(rec(['type' => 'component', 'element' => 'com_adminer', 'client_id' => 1]))['analysis'];
    same('ok', $component['status']);
    ok(str_contains($component['detail'], 'manifest.xml'), 'note about the non-standard name expected');
    same('ok', analyse(rec(['type' => 'module', 'element' => 'mod_foo']))['analysis']['status']);
});

test('manifests: two valid manifests in one directory stay ambiguous', function () {
    fresh();
    put('administrator/components/com_two/a.xml', manifest('component'));
    put('administrator/components/com_two/b.xml', manifest('component'));
    same('manifest_missing', analyse(rec(['type' => 'component', 'element' => 'com_two', 'client_id' => 1]))['analysis']['status']);
});

test('manifests: templates still need their fixed file name', function () {
    fresh();
    put('templates/tpl/wrongname.xml', manifest('template', 'client="site"'));
    same('manifest_missing', analyse(rec(['type' => 'template', 'element' => 'tpl']))['analysis']['status']);
    fresh();
    put('templates/tpl/templateDetails.xml', manifest('template', 'client="site"'));
    same('ok', analyse(rec(['type' => 'template', 'element' => 'tpl']))['analysis']['status']);
});

test('plugin: empty folder with exactly one valid directory is repairable, with none it is a removable orphan', function () {
    fresh();
    put('plugins/system/foo/foo.xml', manifest('plugin', 'group="system"'));
    $repair = analyse(rec(['element' => 'foo']))['analysis'];
    same('repairable', $repair['status']);
    same('system', $repair['repair']);
    fresh(); put('plugins/system/other/other.xml', manifest('plugin', 'group="system"'));
    $orphan = analyse(rec(['element' => 'ghost']))['analysis'];
    same('orphan', $orphan['status']);
    same(true, $orphan['removable']);
});

test('plugin: unsafe element names are rejected', function () {
    fresh();
    same('unsafe', analyse(rec(['element' => '../evil']))['analysis']['status']);
});

test('remove: orphaned component deletes its rows, admin menu entries and writes a backup first', function () {
    $db = fresh();
    $db->record = rec(['extension_id' => 10468, 'type' => 'component', 'element' => 'com_gone', 'client_id' => 1, 'name' => 'COM_X']);
    $db->data['#__menu'] = [41, 40];
    $result = model('CleanupModel')->removeOrphanRecord(10468);
    ok(is_file(JPATH_ROOT . '/' . $result['backup']), 'backup file exists');
    same(['tx start', 'DELETE #__extensions', 'DELETE #__schemas', 'DELETE #__update_sites_extensions', 'tx commit'], $db->log);
    same(['delete 41', 'delete 40', 'rebuild'], \Joomla\CMS\Table\Menu::$log);
});

test('remove: refuses when files still exist, and deletes nothing', function () {
    $db = fresh();
    put('components/com_gone/index.html');
    $db->record = rec(['extension_id' => 1, 'type' => 'component', 'element' => 'com_gone', 'client_id' => 1]);
    throws(fn () => model('CleanupModel')->removeOrphanRecord(1));
    same([], $db->log);
    same([], glob(JPATH_ROOT . '/administrator/logs/*.json'));
});

test('remove: a failed delete rolls back and removes the misleading backup', function () {
    $db = fresh(); put('plugins/system/other/other.xml', manifest('plugin', 'group="system"'));
    $db->record = rec(['extension_id' => 9, 'element' => 'ghost']);
    $db->affected = 0;
    throws(fn () => model('CleanupModel')->removeOrphanRecord(9));
    ok(in_array('tx rollback', $db->log, true));
    same([], glob(JPATH_ROOT . '/administrator/logs/*.json'));
});

test('remove: invalid ids and missing records are refused', function () {
    $db = fresh();
    throws(fn () => model('CleanupModel')->removeOrphanRecord(0));
    $db->record = null;
    throws(fn () => model('CleanupModel')->removeOrphanRecord(5));
});

test('scan: core records never count as orphaned', function () {
    fresh(); put('plugins/system/other/other.xml', manifest('plugin', 'group="system"'));
    $scan = model('CleanupModel')->buildScan([
        rec(['extension_id' => 1, 'type' => 'library', 'element' => 'joomla']),
        rec(['extension_id' => 2, 'type' => 'plugin', 'element' => 'cache', 'folder' => 'system']),
        rec(['extension_id' => 3, 'type' => 'plugin', 'element' => 'ghost']),
    ]);
    same(2, $scan['counts']['core']);
    same(1, $scan['counts']['orphan']);
});
