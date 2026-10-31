<?php
/** Dependency-free test runner:  php tests/run.php [filter] */
require __DIR__ . '/bootstrap.php';

$GLOBALS['fg_tests'] = [];
function test(string $name, callable $fn): void { $GLOBALS['fg_tests'][] = [$name, $fn]; }

function ok($condition, string $message = 'condition is false'): void { if (!$condition) { throw new AssertionError($message); } }
function same($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new AssertionError(trim($message . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)));
    }
}
function throws(callable $fn, string $contains = ''): void
{
    try { $fn(); } catch (RuntimeException $e) {
        if ($contains !== '' && stripos($e->getMessage(), $contains) === false) {
            throw new AssertionError('exception message "' . $e->getMessage() . '" does not contain "' . $contains . '"');
        }
        return;
    }
    throw new AssertionError('expected a RuntimeException');
}

foreach (glob(__DIR__ . '/*Test.php') as $file) { require $file; }

$filter = $argv[1] ?? '';
$failed = 0; $passed = 0;
foreach ($GLOBALS['fg_tests'] as [$name, $fn]) {
    if ($filter !== '' && stripos($name, $filter) === false) { continue; }
    try { $fn(); $passed++; echo "  ok    $name\n"; }
    catch (Throwable $e) { $failed++; echo "  FAIL  $name\n        " . get_class($e) . ': ' . $e->getMessage() . "\n"; }
}
removeTree(JPATH_ROOT);
echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
