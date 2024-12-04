<?php
ob_start();

ini_set('memory_limit', '1G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Load .env file first if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
        error_log("Loaded .env file");
    } catch (Exception $e) {
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

// Debug: Print all environment variables
error_log("All environment variables:");
error_log(print_r($_ENV, true));
error_log("All getenv variables:");
error_log(print_r(getenv(), true));
error_log("All _SERVER variables:");
error_log(print_r($_SERVER, true));

// Set Supabase URL and Key
$_ENV['SUPABASE_URL'] = 'https://kgqwiwjayaydewyuygxt.supabase.co';

// Debug key access
error_log("SUPABASE_KEY from _SERVER: " . ($_SERVER['SUPABASE_KEY'] ?? 'not set'));
error_log("SUPABASE_KEY from getenv: " . (getenv('SUPABASE_KEY') ?: 'not set'));
error_log("SUPABASE_KEY from _ENV: " . ($_ENV['SUPABASE_KEY'] ?? 'not set'));

// Try to get the key from all possible sources
$supabaseKey = '';

// Try _ENV first (since we loaded .env)
if (isset($_ENV['SUPABASE_KEY']) && !empty($_ENV['SUPABASE_KEY'])) {
    $supabaseKey = $_ENV['SUPABASE_KEY'];
    error_log("Got key from _ENV");
}
// Try _SERVER next
else if (isset($_SERVER['SUPABASE_KEY']) && !empty($_SERVER['SUPABASE_KEY'])) {
    $supabaseKey = $_SERVER['SUPABASE_KEY'];
    error_log("Got key from _SERVER");
}
// Try getenv last
else if (($envKey = getenv('SUPABASE_KEY')) !== false && !empty($envKey)) {
    $supabaseKey = $envKey;
    error_log("Got key from getenv");
}

$_ENV['SUPABASE_KEY'] = $supabaseKey;

// Log final state
error_log("Final SUPABASE_KEY length: " . strlen($_ENV['SUPABASE_KEY']));
error_log("Final SUPABASE_KEY value: " . substr($_ENV['SUPABASE_KEY'], 0, 10) . "...");

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    ob_end_clean();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Validate environment variables
    if (empty($_ENV['SUPABASE_KEY'])) {
        error_log("Missing SUPABASE_KEY after all attempts");
        ob_end_clean();
        header('Location: login.php?error=1&message=' . urlencode('Missing required environment variable: SUPABASE_KEY'));
        exit;
    }

    try {
        $client = new Client();
        
        // Remove trailing slash from URL if present
        $baseUrl = rtrim($_ENV['SUPABASE_URL'], '/');
        error_log("Making request to: " . $baseUrl . '/auth/v1/token?grant_type=password');
        
        $response = $client->post($baseUrl . '/auth/v1/token?grant_type=password', [
            'headers' => [
                'apikey' => $_ENV['SUPABASE_KEY'],
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'email' => $email,
                'password' => $password,
                'grant_type' => 'password'
            ],
            'verify' => false
        ]);

        $data = json_decode($response->getBody(), true);
        error_log("Auth Response: " . print_r($data, true));

        if (isset($data['access_token'])) {
            $_SESSION['user'] = $data;
            $_SESSION['user_email'] = $email;
            ob_end_clean();
            header('Location: index.php');
            exit;
        } else {
            error_log("Auth failed: " . print_r($data, true));
            ob_end_clean();
            header('Location: login.php?error=1&message=' . urlencode('Invalid credentials'));
            exit;
        }
    } catch (RequestException $e) {
        error_log("Auth Error: " . $e->getMessage());
        if ($e->hasResponse()) {
            $errorBody = json_decode($e->getResponse()->getBody(), true);
            error_log("Error Response: " . print_r($errorBody, true));
        }
        ob_end_clean();
        header('Location: login.php?error=1&message=' . urlencode('Authentication failed'));
        exit;
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        ob_end_clean();
        header('Location: login.php?error=1&message=' . urlencode('System error'));
        exit;
    }
} else {
    ob_end_clean();
    header('Location: login.php');
    exit;
}
