<?php
session_start();
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        header('Location: login.php?error=1&message=' . urlencode('Email and password are required'));
        exit;
    }

    try {
        $client = new Client([
            'verify' => false,
            'http_errors' => false
        ]);
        
        // Authenticate with Supabase
        $response = $client->post('https://kgqwiwjayaydewyuygxt.supabase.co/auth/v1/token?grant_type=password', [
            'headers' => [
                'apikey' => getenv('SUPABASE_KEY'),
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $data = json_decode($response->getBody(), true);

        error_log('Auth Response - Status: ' . $statusCode);
        error_log('Auth Response - Body: ' . json_encode($data));

        if ($statusCode === 200 && isset($data['access_token'])) {
            // Check if user is admin
            $isAdmin = ($email === 'admin@wooscraper.com');
            error_log('Is admin check: ' . ($isAdmin ? 'true' : 'false'));

            // Store session data
            $_SESSION['user'] = [
                'id' => $data['user']['id'],
                'email' => $data['user']['email'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'role' => $isAdmin ? 'admin' : 'user' 
            ];

            error_log('Final session data: ' . json_encode($_SESSION['user']));

            if ($isAdmin) {
                error_log('Redirecting to admin dashboard');
                header('Location: admin_dashboard.php');
            } else {
                error_log('Redirecting to regular dashboard');
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $errorMessage = isset($data['error_description']) ? $data['error_description'] : (isset($data['error']) ? $data['error'] : 'Invalid credentials');
            error_log('Login Error: ' . $errorMessage);
            header('Location: login.php?error=1&message=' . urlencode($errorMessage));
            exit;
        }

    } catch (Exception $e) {
        error_log('Login Exception: ' . $e->getMessage());
        header('Location: login.php?error=1&message=' . urlencode('Authentication failed: ' . $e->getMessage()));
        exit;
    }
}

// If no POST data, redirect to login
header('Location: login.php');
exit;
