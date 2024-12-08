<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables from .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function createSupabaseClient($url, $key) {
    return new \Supabase\CreateClient($url, $key, [
        'headers' => [
            'Authorization' => 'Bearer ' . $key
        ]
    ]);
}

// Database configuration
define('SUPABASE_URL', $_ENV['SUPABASE_URL']);
define('SUPABASE_KEY', $_ENV['SUPABASE_KEY']);
define('SUPABASE_SERVICE_ROLE_KEY', $_ENV['SUPABASE_SERVICE_ROLE_KEY']);
define('JWT_SECRET_KEY', $_ENV['JWT_SECRET_KEY']);
