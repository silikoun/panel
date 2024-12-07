<?php
require 'vendor/autoload.php';
require_once 'classes/Database.php';
require_once 'classes/TokenManager.php';

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/test_token.log');
error_log("=== Testing token validation ===");

try {
    $database = new Database();
    $db = $database->connect();
    $tokenManager = new TokenManager($db);

    $token = '8d257aab281c0179010f379fe992b3bc0068d68f78dd65e1a28258a8b95b96ed';
    
    error_log("Testing token: " . $token);
    
    $result = $tokenManager->validateToken($token);
    
    echo "Token validation successful!\n";
    echo "User ID: " . $result->sub . "\n";
    echo "Expires: " . date('Y-m-d H:i:s', $result->exp) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Test error: " . $e->getMessage());
}
