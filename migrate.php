<?php
require 'vendor/autoload.php';
require_once 'auth/SupabaseAuth.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Initialize Supabase client
    $supabase = new SupabaseAuth();
    $client = $supabase->createClient();
    
    // Get all migration files
    $migrations = glob(__DIR__ . '/migrations/*.sql');
    sort($migrations); // Sort to ensure order
    
    foreach ($migrations as $migration) {
        echo "Running migration: " . basename($migration) . "\n";
        
        // Read SQL file
        $sql = file_get_contents($migration);
        
        // Execute migration
        $result = $client->rpc('exec_sql', ['sql' => $sql])->execute();
        
        if ($result->error) {
            throw new Exception("Migration failed: " . $result->error->message);
        }
        
        echo "Migration completed successfully\n";
    }
    
    echo "All migrations completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
