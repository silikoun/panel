<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Database.php';

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->connect();
    
    // Read and execute SQL schema
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }
    
    echo "Database initialized successfully!\n";
    echo "Default admin credentials:\n";
    echo "Email: admin@wooscraper.com\n";
    echo "Password: admin123\n";
    
} catch (PDOException $e) {
    die("Database initialization failed: " . $e->getMessage() . "\n");
}
