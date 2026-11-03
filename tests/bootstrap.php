<?php
/**
 * Minimal stand-ins for the Joomla classes the component touches, so the model, service and installer script
 * can be tested without a Joomla installation:  php tests/run.php
 */
namespace Joomla\CMS\Language {
    class Text
    {
        public static array $strings = [];
        public static function _($key) { return self::$strings[$key] ?? '!!MISSING:' . $key; }
        public static function sprintf($key, ...$args) { return vsprintf(self::_($key), $args); }
        public static function script($key) {}
    }
}
namespace Joomla\CMS\Extension {
    class ExtensionHelper
    {
        public static function checkIfCoreExtension($type, $element, $clientId = 0, $folder = '')
        {
            return in_array([$type, $element, $folder], [['library', 'joomla', ''], ['library', 'phpass', ''], ['component', 'com_content', ''], ['plugin', 'cache', 'system']], true);
        }
    }
}
namespace Joomla\CMS {
    class Factory
    {
        public static ?string $logPath = null;
        public static $db;
        public static array $loaded = [];
        public static function getApplication() { return new class { public function get($key) { return \Joomla\CMS\Factory::$logPath; } public function getLanguage() { return new class { public function load($ext, $path) { \Joomla\CMS\Factory::$loaded[] = $ext . '@' . $path; return true; } }; } }; }
        public static function getContainer() { return new class { public function get($id) { return \Joomla\CMS\Factory::$db; } }; }
    }
}
namespace Joomla\CMS\Table {
    class Menu
    {
        public static array $log = [];
        public function __construct($db) {}
        public function delete($id) { self::$log[] = 'delete ' . $id; return true; }
        public function rebuild() { self::$log[] = 'rebuild'; return true; }
    }
}
namespace Joomla\CMS\Installer {
    interface InstallerAdapter {}
    interface InstallerScriptInterface {}
}
namespace Joomla\Database {
    interface DatabaseInterface {}
}
namespace Joomla\CMS\MVC\Model {
    class BaseDatabaseModel
    {
        public static $db;
        protected function getDatabase() { return self::$db; }
    }
}
namespace {
    /** Records what the code asks for and answers from $data[table]. */
    final class FakeQuery
    {
        public string $kind = 'select';
        public string $table = '';
        public array $where = [];
        public function __call($name, $args)
        {
            if ($name === 'from') { $this->table = (string) $args[0]; }
            if ($name === 'delete') { $this->kind = 'delete'; $this->table = (string) $args[0]; }
            if ($name === 'where') { $this->where[] = (string) $args[0]; }
            return $this;
        }
    }

    final class FakeDb implements \Joomla\Database\DatabaseInterface
    {
        public array $data = [];          // table => rows
        public array $log = [];
        public array $inserts = [];
        public bool $existing = false;    // answer of loadResult()
        public int $affected = 1;
        public ?object $record = null;    // answer of loadObject()
        public array $columns = [];
        private ?FakeQuery $query = null;

        public function getQuery($new = true) { return new FakeQuery(); }
        public function quoteName($name, $alias = null) { return $name; }
        public function quote($text, $escape = true) { return "'" . $text . "'"; }
        public function escape($text, $extra = false) { return $text; }
        public function setQuery($query, $offset = 0, $limit = 0) { $this->query = $query; return $this; }
        public function execute() { if ($this->query->kind === 'delete') { $this->log[] = 'DELETE ' . $this->query->table; } return true; }
        public function getAffectedRows() { return $this->affected; }
        public function loadObject() { return $this->record; }
        public function loadObjectList() { return $this->data[$this->query->table] ?? []; }
        public function loadAssocList() { return $this->data[$this->query->table] ?? []; }
        public function loadColumn() { return $this->data[$this->query->table] ?? []; }
        public function loadResult() { return $this->existing ? 1 : null; }
        public function getTableColumns($table) { return array_fill_keys($this->columns ?: ['extension_id', 'name', 'type', 'element', 'folder', 'client_id', 'params', 'update_site_id', 'version_id', 'extension', 'id'], 'x'); }
        public function insertObject($table, &$object) { $this->inserts[] = [$table, (array) $object]; $this->log[] = 'INSERT ' . $table; }
        public function transactionStart() { $this->log[] = 'tx start'; }
        public function transactionCommit() { $this->log[] = 'tx commit'; }
        public function transactionRollback() { $this->log[] = 'tx rollback'; }
    }

    define('_JEXEC', 1);
    define('FG_PROJECT', dirname(__DIR__));
    define('JPATH_ROOT', sys_get_temp_dir() . '/fgcleanup_tests_' . getmypid());
    define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
    define('JPATH_SITE', JPATH_ROOT);
    define('JPATH_PLUGINS', JPATH_ROOT . '/plugins');

    \Joomla\CMS\Language\Text::$strings = parse_ini_file(FG_PROJECT . '/admin/language/en-GB/com_fgextensioncleanup.ini');

    require FG_PROJECT . '/admin/src/Service/BackupStore.php';
    require FG_PROJECT . '/admin/src/Service/NameTranslator.php';
    require FG_PROJECT . '/admin/src/Model/CleanupModel.php';
    require FG_PROJECT . '/admin/src/Model/MaintenanceModel.php';

    /** Fresh, empty fake site (files + database) for every test. */
    function fresh(): FakeDb
    {
        removeTree(JPATH_ROOT);
        mkdir(JPATH_ROOT . '/administrator/logs', 0777, true);
        \Joomla\CMS\Factory::$logPath = JPATH_ADMINISTRATOR . '/logs';
        \Joomla\CMS\Table\Menu::$log = [];
        $db = new FakeDb();
        \Joomla\CMS\MVC\Model\BaseDatabaseModel::$db = $db;
        \Joomla\CMS\Factory::$db = $db;

        return $db;
    }

    function removeTree(string $path): void
    {
        if (!is_dir($path)) { return; }
        foreach (scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') { continue; }
            is_dir("$path/$entry") && !is_link("$path/$entry") ? removeTree("$path/$entry") : @unlink("$path/$entry");
        }
        @rmdir($path);
    }

    function put(string $relative, string $content = ''): void
    {
        @mkdir(dirname(JPATH_ROOT . '/' . $relative), 0777, true);
        file_put_contents(JPATH_ROOT . '/' . $relative, $content);
    }

    function manifest(string $type, string $extra = ''): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><extension type="' . $type . '" ' . $extra . '><name>x</name></extension>';
    }

    function rec(array $override = []): object
    {
        return (object) array_merge(['extension_id' => 1, 'name' => 'x', 'element' => '', 'folder' => '', 'enabled' => 1,
            'protected' => 0, 'type' => 'plugin', 'client_id' => 0, 'package_id' => 0], $override);
    }

    function model(string $class)
    {
        return (new ReflectionClass('FGcodework\\Component\\FgExtensionCleanup\\Administrator\\Model\\' . $class))->newInstanceWithoutConstructor();
    }

    function analyse(object $record): array
    {
        return model('CleanupModel')->buildScan([$record])['rows'][0];
    }
}
