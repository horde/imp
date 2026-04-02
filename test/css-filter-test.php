#!/usr/bin/env php
<?php

/**
 * Test script for IMP CSS filtering changes
 *
 * Verifies that:
 * 1. Normal mode removes URLs, imports, and cursor rules
 * 2. Blocked mode keeps only dangerous CSS
 */

// Find Horde autoloader
$autoload_paths = [
    __DIR__ . '/../../../Core/lib/Horde/Core/Autoloader/Default.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoload_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

// Include IMP classes
require_once __DIR__ . '/../lib/Mime/Viewer/Html.php';

// Test CSS samples
$maliciousCss = <<<'CSS'
    .safe { color: red; font-size: 12px; }
    .tracking { background: url("http://tracker.com/pixel.gif"); }
    .cursor-override { cursor: none; }
    @import url("http://evil.com/bad.css");
    .font-face { src: url("font.woff"); }
    .safe2 { margin: 10px; }
    CSS;

echo "Testing IMP CSS Filtering\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Normal mode (remove dangerous CSS)
echo "Test 1: Normal Mode (Remove Dangerous CSS)\n";
echo str_repeat("-", 60) . "\n";

try {
    $parser = new Horde_Css_Parser($maliciousCss);

    // Create a minimal IMP viewer stub
    $viewer = new class extends IMP_Mime_Viewer_Html {
        public function __construct()
        {
            // Skip parent constructor
        }
        public function testParseCss($css, $blocked)
        {
            return $this->_parseCss($css, $blocked);
        }
    };

    $safeCss = $viewer->testParseCss($parser, false);

    echo "Input CSS:\n$maliciousCss\n\n";
    echo "Output (safe) CSS:\n$safeCss\n\n";

    // Verify dangerous patterns are removed
    $checks = [
        'url(' => 'URLs should be removed',
        '@import' => 'Imports should be removed',
        'cursor' => 'Cursor rules should be removed',
    ];

    $pass = 0;
    $fail = 0;

    foreach ($checks as $pattern => $description) {
        if (stripos($safeCss, $pattern) === false) {
            echo "✓ PASS: $description\n";
            $pass++;
        } else {
            echo "✗ FAIL: $description (found: $pattern)\n";
            $fail++;
        }
    }

    // Verify safe CSS is kept
    if (strpos($safeCss, 'color') !== false || strpos($safeCss, 'margin') !== false) {
        echo "✓ PASS: Safe CSS properties preserved\n";
        $pass++;
    } else {
        echo "✗ FAIL: Safe CSS properties not preserved\n";
        $fail++;
    }

    echo "\nTest 1 Results: $pass passed, $fail failed\n\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

// Test 2: Blocked mode (keep only dangerous CSS)
echo "Test 2: Blocked Mode (Keep Only Dangerous CSS)\n";
echo str_repeat("-", 60) . "\n";

try {
    $parser2 = new Horde_Css_Parser($maliciousCss);
    $blockedCss = $viewer->testParseCss($parser2, true);

    echo "Output (blocked) CSS:\n$blockedCss\n\n";

    // Verify dangerous patterns are kept
    $checks = [
        'url(' => 'URLs should be kept',
        '@import' => 'Imports should be kept',
        'cursor' => 'Cursor rules should be kept',
    ];

    $pass = 0;
    $fail = 0;

    foreach ($checks as $pattern => $description) {
        if (stripos($blockedCss, $pattern) !== false) {
            echo "✓ PASS: $description\n";
            $pass++;
        } else {
            echo "✗ FAIL: $description (not found: $pattern)\n";
            $fail++;
        }
    }

    // Verify most safe CSS is removed
    $safePatterns = ['color:', 'margin:'];
    $safeRemoved = true;
    foreach ($safePatterns as $pattern) {
        if (stripos($blockedCss, $pattern) !== false) {
            $safeRemoved = false;
            break;
        }
    }

    if ($safeRemoved) {
        echo "✓ PASS: Safe CSS properties removed\n";
        $pass++;
    } else {
        echo "✗ FAIL: Safe CSS properties not removed\n";
        $fail++;
    }

    echo "\nTest 2 Results: $pass passed, $fail failed\n\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

echo str_repeat("=", 60) . "\n";
echo "All tests complete!\n";
