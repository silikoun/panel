<?php
require __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Get Supabase credentials
$supabaseUrl = $_SERVER['SUPABASE_URL'] ?? $_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL');
$supabaseKey = $_SERVER['SUPABASE_SERVICE_ROLE_KEY'] ?? $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseKey) {
    die("Missing Supabase credentials\n");
}

// Create Guzzle client
$client = new GuzzleHttp\Client([
    'base_uri' => $supabaseUrl,
    'headers' => [
        'apikey' => $supabaseKey,
        'Authorization' => 'Bearer ' . $supabaseKey,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=minimal'
    ]
]);

// Read and execute SQL file
$sql = file_get_contents(__DIR__ . '/supabase_schema.sql');
$sqlCommands = array_filter(explode(';', $sql), 'trim');

echo "Running database migration...\n";

try {
    foreach ($sqlCommands as $command) {
        if (empty(trim($command))) continue;
        
        $response = $client->post('/rest/v1/rpc/exec_sql', [
            'json' => [
                'query' => trim($command)
            ]
        ]);
        
        echo "✓ Executed SQL command successfully\n";
    }
    
    echo "\nMigration completed successfully!\n";
} catch (Exception $e) {
    echo "Error during migration:\n";
    echo $e->getMessage() . "\n";
    
    if ($e instanceof GuzzleHttp\Exception\ClientException) {
        echo "\nResponse body:\n";
        echo $e->getResponse()->getBody() . "\n";
    }
}
