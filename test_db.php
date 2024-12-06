<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Get Supabase credentials
$supabaseUrl = $_SERVER['SUPABASE_URL'] ?? $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
$supabaseKey = $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] ?? $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseKey) {
    die("Missing Supabase credentials\n");
}

echo "Testing Supabase connection...\n";
echo "URL: $supabaseUrl\n";
echo "Key length: " . strlen($supabaseKey) . "\n\n";

// Create Guzzle client
$client = new GuzzleHttp\Client([
    'base_uri' => $supabaseUrl,
    'headers' => [
        'apikey' => $supabaseKey,
        'Authorization' => 'Bearer ' . $supabaseKey
    ]
]);

try {
    // Test tokens table
    $response = $client->get('/rest/v1/tokens?select=*');
    $tokens = json_decode($response->getBody(), true);
    echo "✓ Successfully connected to tokens table\n";
    echo "Found " . count($tokens) . " tokens\n\n";

    // Test usage_logs table
    $response = $client->get('/rest/v1/usage_logs?select=*');
    $logs = json_decode($response->getBody(), true);
    echo "✓ Successfully connected to usage_logs table\n";
    echo "Found " . count($logs) . " usage logs\n\n";

    echo "Database connection test successful!\n";
} catch (Exception $e) {
    echo "Error testing database:\n";
    echo $e->getMessage() . "\n";
    
    if ($e instanceof GuzzleHttp\Exception\ClientException) {
        echo "\nResponse body:\n";
        echo $e->getResponse()->getBody() . "\n";
    }
}
