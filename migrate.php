<?php
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Function to run SQL file on Railway MySQL
function runRailwayMigration($pdo, $file) {
    try {
        $sql = file_get_contents($file);
        $pdo->exec($sql);
        echo "Successfully executed Railway migration: " . basename($file) . "\n";
    } catch (PDOException $e) {
        echo "Error executing Railway migration " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

// Function to run SQL file on Supabase
function runSupabaseMigration($file) {
    try {
        $client = new GuzzleHttp\Client();
        $sql = file_get_contents($file);
        
        $response = $client->post(getenv('SUPABASE_URL') . '/rest/v1/rpc/migrate', [
            'headers' => [
                'apikey' => getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Authorization' => 'Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'sql' => $sql
            ]
        ]);
        
        echo "Successfully executed Supabase migration: " . basename($file) . "\n";
    } catch (Exception $e) {
        echo "Error executing Supabase migration " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

try {
    // Connect to Railway MySQL
    $pdo = new PDO(
        "mysql:host=" . getenv('MYSQLHOST') . ";dbname=" . getenv('MYSQLDATABASE'),
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all migration files
    $migrations = glob(__DIR__ . '/migrations/*.sql');
    sort($migrations); // Execute in order
    
    foreach ($migrations as $migration) {
        // Check if it's a Railway or Supabase migration based on filename
        if (strpos(basename($migration), 'railway_') === 0) {
            runRailwayMigration($pdo, $migration);
        } else {
            runSupabaseMigration($migration);
        }
    }
    
    echo "All migrations completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
}
