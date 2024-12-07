<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use Dotenv\Dotenv;

try {
    // Try to load .env file
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        echo "Found and loaded .env file\n";
    } else {
        echo "No .env file found\n";
    }

    // Check environment variables
    $vars = [
        'SUPABASE_URL',
        'SUPABASE_SERVICE_ROLE_KEY',
        'JWT_SECRET_KEY'
    ];

    echo "\nEnvironment variable status:\n";
    foreach ($vars as $var) {
        $value = getenv($var);
        echo "$var: " . ($value ? "set (length: " . strlen($value) . ")" : "not set") . "\n";
        if ($value) {
            echo "First 5 chars: " . substr($value, 0, 5) . "...\n";
        }
    }

    // Check $_ENV array
    echo "\n\$_ENV array status:\n";
    foreach ($vars as $var) {
        echo "$var: " . (isset($_ENV[$var]) ? "set" : "not set") . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
