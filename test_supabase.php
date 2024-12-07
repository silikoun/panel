<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
    echo "✅ Successfully loaded .env file\n";
} catch (Exception $e) {
    die("❌ Error loading .env file: " . $e->getMessage() . "\n");
}

// Get environment variables
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? null;
$supabaseKey = $_ENV['SUPABASE_KEY'] ?? null;
$supabaseServiceRoleKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? null;

// Check if variables are set
echo "\nChecking environment variables:\n";
echo "SUPABASE_URL: " . ($supabaseUrl ? "✅ Set" : "❌ Missing") . "\n";
echo "SUPABASE_KEY: " . ($supabaseKey ? "✅ Set" : "❌ Missing") . "\n";
echo "SUPABASE_SERVICE_ROLE_KEY: " . ($supabaseServiceRoleKey ? "✅ Set" : "❌ Missing") . "\n";

if (!$supabaseUrl || !$supabaseServiceRoleKey) {
    die("\n❌ Missing required environment variables\n");
}

// Extract project reference
$urlParts = parse_url($supabaseUrl);
$projectRef = explode('.', $urlParts['host'])[0];
echo "\nProject Reference: " . $projectRef . "\n";

// Test different API endpoints
$endpoints = [
    [
        'name' => 'Auth Admin API',
        'url' => "https://{$projectRef}.supabase.co/auth/v1/admin/users",
        'headers' => [
            'apikey' => $supabaseServiceRoleKey,
            'Authorization' => 'Bearer ' . $supabaseServiceRoleKey
        ]
    ],
    [
        'name' => 'REST API',
        'url' => "https://{$projectRef}.supabase.co/rest/v1/users",
        'headers' => [
            'apikey' => $supabaseServiceRoleKey,
            'Authorization' => 'Bearer ' . $supabaseServiceRoleKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation'
        ]
    ]
];

$client = new Client([
    'verify' => false,
    'http_errors' => false
]);

echo "\nTesting API endpoints:\n";

foreach ($endpoints as $endpoint) {
    echo "\n🔍 Testing " . $endpoint['name'] . ":\n";
    echo "URL: " . $endpoint['url'] . "\n";
    
    try {
        $response = $client->get($endpoint['url'], [
            'headers' => $endpoint['headers']
        ]);
        
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        
        echo "Status Code: " . $statusCode . "\n";
        echo "Response Body: " . substr($body, 0, 200) . "...\n";
        
        if ($statusCode === 200) {
            echo "✅ Success!\n";
        } else {
            echo "❌ Failed with status code " . $statusCode . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
