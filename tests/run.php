<?php

define('MAGPIE_TESTING', true);

$tmpDir = __DIR__ . '/tmp';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
if (!is_dir($tmpDir . '/avatars')) mkdir($tmpDir . '/avatars', 0755, true);
if (!is_dir($tmpDir . '/posts')) mkdir($tmpDir . '/posts', 0755, true);

define('UPLOADS_DIR', $tmpDir . '/avatars/');
define('POSTS_UPLOADS_DIR', $tmpDir . '/posts/');

// Mock some globals if needed
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/../api.php';
require_once __DIR__ . '/TestCase.php';

use Tests\AssertionFailedException;

$testDir = __DIR__ . '/Unit';
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testDir));
$testFiles = new RegexIterator($files, '/Test\.php$/');

$passed = 0;
$failed = 0;
$assertions = 0;
$failures = [];

echo "Running tests...\n\n";

foreach ($testFiles as $file) {
    require_once $file->getPathname();
    
    // Get classes defined in this file
    $classes = get_declared_classes();
    $testClass = end($classes);
    
    $reflection = new ReflectionClass($testClass);
    if (!$reflection->isAbstract() && $reflection->isSubclassOf('Tests\TestCase')) {
        $instance = $reflection->newInstance();
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        
        foreach ($methods as $method) {
            if (strpos($method->name, 'test') === 0) {
                try {
                    $instance->setUp();
                    $instance->{$method->name}();
                    echo ".";
                    $passed++;
                } catch (AssertionFailedException $e) {
                    echo "F";
                    $failed++;
                    $failures[] = [
                        'class' => $testClass,
                        'method' => $method->name,
                        'message' => $e->getMessage(),
                        'file' => $file->getPathname(),
                        'line' => $e->getLine()
                    ];
                } catch (Throwable $e) {
                    echo "E";
                    $failed++;
                    $failures[] = [
                        'class' => $testClass,
                        'method' => $method->name,
                        'message' => "Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString(),
                        'file' => $file->getPathname(),
                        'line' => $e->getLine()
                    ];
                }
            }
        }
        $assertions += $instance->getAssertionCount();
    }
}

echo "\n\n";

if ($failed > 0) {
    echo "Failures:\n";
    foreach ($failures as $i => $failure) {
        echo ($i + 1) . ") {$failure['class']}::{$failure['method']}\n";
        echo "{$failure['message']}\n";
        echo "{$failure['file']}:{$failure['line']}\n\n";
    }
}

echo "Tests: " . ($passed + $failed) . ", Assertions: $assertions, Failures: $failed\n";

exit($failed > 0 ? 1 : 0);
