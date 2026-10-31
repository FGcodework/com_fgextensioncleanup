<?php
test('installer script: registers once in the Action Logs extension list, also on updates', function () {
    $db = fresh();
    $db->data['#__action_logs_extensions'] = [];
    $script = require FG_PROJECT . '/script.php';
    $adapter = new class implements \Joomla\CMS\Installer\InstallerAdapter {};
    $script->postflight('install', $adapter);
    same(1, count($db->inserts));
    same(['extension' => 'com_fgextensioncleanup'], $db->inserts[0][1]);
    $db->existing = true;                       // already registered
    $script->postflight('update', $adapter);
    same(1, count($db->inserts), 'no duplicate row');
});

test('installer script: uninstall removes the registration; database errors never break the installer', function () {
    $db = fresh();
    $script = require FG_PROJECT . '/script.php';
    $adapter = new class implements \Joomla\CMS\Installer\InstallerAdapter {};
    $script->uninstall($adapter);
    same(['DELETE #__action_logs_extensions'], $db->log);
    \Joomla\CMS\Factory::$db = new class { public function __call($n, $a) { throw new RuntimeException('no table'); } };
    same(true, $script->postflight('install', $adapter));
    same(true, $script->uninstall($adapter));
});
