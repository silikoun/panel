<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test environment variables first
$envVars = [
    'JWT_SECRET_KEY',
    'SUPABASE_URL',
    'SUPABASE_SERVICE_ROLE_KEY'
];

echo "Testing environment variables:\n";
foreach ($envVars as $var) {
    $value = getenv($var) ?: $_ENV[$var] ?? $_SERVER[$var] ?? null;
    echo "$var: " . ($value ? "✓ Set (" . strlen($value) . " chars)" : "✗ Not set") . "\n";
}

// Include the TokenManager class
require_once __DIR__ . '/classes/TokenManager.php';

try {
    echo "\nInitializing TokenManager...\n";
    $tokenManager = new TokenManager();
    
    echo "\nRunning token validation tests:\n";
    
    // Test 1: Invalid token structure
    echo "\n1. Testing invalid token structure:\n";
    $invalidToken = "not.a.token";
    $result = $tokenManager->validateToken($invalidToken);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    // Test 2: Invalid token signature
    echo "\n2. Testing invalid token signature:\n";
    $invalidSignatureToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
    $result = $tokenManager->validateToken($invalidSignatureToken);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    // Test 3: Expired token
    echo "\n3. Testing expired token:\n";
    $expiredToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyLCJleHAiOjE1MTYyMzkwMjJ9.s3xu8vkRF8XPtk_GHpqXpvPpPgh5WVjfM4ynz3Ksq8Q';
    $result = $tokenManager->validateToken($expiredToken);
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    // Test 4: Generate and validate a valid token
    echo "\n4. Testing valid token generation and validation:\n";
    // Note: In a real scenario, we would use Firebase\JWT\JWT to generate a valid token
    echo "Skipped - Requires JWT generation library\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
