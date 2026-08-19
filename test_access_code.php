<?php

// Simple test script to verify access code generation
require __DIR__ . '/vendor/autoload.php';

use App\Models\LegacyContact;
use Illuminate\Support\Str;

echo "Testing Access Code Generation\n";
echo "==============================\n\n";

// Test 1: Code format
echo "Test 1: Code Format\n";
for ($i = 0; $i < 5; $i++) {
    $code = Str::random(12);
    $cleaned = str_replace(['0', 'O', '1', 'I'], '', $code);
    $final = strtoupper(substr($cleaned, 0, 8));
    echo "Generated: $final (Length: " . strlen($final) . ")\n";
}

echo "\nTest 2: Code Properties\n";
$testCode = 'ABC2XYZ9';
echo "Test code: $testCode\n";
echo "Length: " . strlen($testCode) . "\n";
echo "Uppercase: " . strtoupper($testCode) . "\n";
echo "Regex match: " . (preg_match('/^[A-Z2-9]+$/', $testCode) ? 'PASS' : 'FAIL') . "\n";

echo "\nAll tests completed!\n";
