<?php
function usedLanguageKeys(): array
{
    $keys = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FG_PROJECT . '/admin/src'));
    $files = array_merge(iterator_to_array($files), [new SplFileInfo(FG_PROJECT . '/admin/tmpl/cleanup/default.php'), new SplFileInfo(FG_PROJECT . '/script.php')]);
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') { continue; }
        $source = file_get_contents($file->getPathname());
        preg_match_all('/COM_FGEXTENSIONCLEANUP_[A-Z0-9_]+/', $source, $m);
        $keys = array_merge($keys, $m[0]);
        preg_match_all('/\$t\(([^)]*)\)/', $source, $t);    // $t('KEY') and $t($x ? 'A' : 'B')
        foreach ($t[1] as $args) {
            preg_match_all("/'([A-Z0-9_]+)'/", $args, $names);
            foreach ($names[1] as $name) { $keys[] = 'COM_FGEXTENSIONCLEANUP_' . $name; }
        }
    }
    return array_values(array_unique($keys));
}

test('language: en-GB and sk-SK define exactly the same keys, and all files parse', function () {
    $en = parse_ini_file(FG_PROJECT . '/admin/language/en-GB/com_fgextensioncleanup.ini');
    $sk = parse_ini_file(FG_PROJECT . '/admin/language/sk-SK/com_fgextensioncleanup.ini');
    ok($en !== false && $sk !== false, 'ini parse error');
    same([], array_values(array_diff(array_keys($en), array_keys($sk))), 'missing in sk-SK:');
    same([], array_values(array_diff(array_keys($sk), array_keys($en))), 'missing in en-GB:');
    foreach (['en-GB', 'sk-SK'] as $tag) {
        ok(parse_ini_file(FG_PROJECT . "/admin/language/$tag/com_fgextensioncleanup.sys.ini") !== false);
    }
});

test('language: every key used in the code exists', function () {
    $en = parse_ini_file(FG_PROJECT . '/admin/language/en-GB/com_fgextensioncleanup.ini') + parse_ini_file(FG_PROJECT . '/admin/language/en-GB/com_fgextensioncleanup.sys.ini');
    $missing = array_values(array_filter(usedLanguageKeys(), fn ($k) => !array_key_exists($k, $en) && !str_ends_with($k, '_')));
    same([], $missing, 'undefined keys:');
});

test('manifest: version is semver and matches updates.xml', function () {
    $manifest = simplexml_load_file(FG_PROJECT . '/com_fgextensioncleanup.xml');
    $updates = simplexml_load_file(FG_PROJECT . '/updates.xml');
    ok(preg_match('/^\d+\.\d+\.\d+$/', (string) $manifest->version) === 1, 'version format');
    same((string) $manifest->version, (string) $updates->update->version, 'updates.xml version');
    ok(str_contains((string) $updates->update->downloads->downloadurl, 'v' . $manifest->version . '/'), 'download URL tag');
});
