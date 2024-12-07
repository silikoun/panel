<?php
session_start();
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
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

    error_log('Login attempt for email: ' . $email);

    // Basic validation
    if (empty($email) || empty($password)) {
        error_log('Login failed: Empty email or password');
        header('Location: login.php?error=1&message=' . urlencode('Email and password are required'));
        exit;
    }

    try {
        $client = new Client([
            'verify' => false,
            'http_errors' => false
        ]);
        
        $supabaseUrl = getenv('SUPABASE_URL');
        $supabaseKey = getenv('SUPABASE_KEY');
        
        if (!$supabaseUrl || !$supabaseKey) {
            throw new Exception('Missing Supabase configuration');
        }
        
        error_log('Attempting Supabase authentication...');
        
        // Authenticate with Supabase
        $response = $client->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
            'headers' => [
                'apikey' => $supabaseKey,
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
            // Get user metadata to check if admin
            $userResponse = $client->get($supabaseUrl . '/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $data['access_token'],
                    'apikey' => $supabaseKey
                ]
            ]);
            
            $userData = json_decode($userResponse->getBody(), true);
            error_log('User data: ' . json_encode($userData));
            
            // Check if user is admin
            $isAdmin = false;
            if (isset($userData['user_metadata']['is_admin'])) {
                $isAdmin = $userData['user_metadata']['is_admin'] === true;
            } else if ($email === 'admin@wooscraper.com') {
                $isAdmin = true;
            }
            
            error_log('Is admin check: ' . ($isAdmin ? 'true' : 'false'));

            // Store session data
            $_SESSION['user'] = [
                'id' => $userData['id'],
                'email' => $userData['email'],
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'role' => $isAdmin ? 'admin' : 'user',
                'metadata' => $userData['user_metadata'] ?? []
            ];

            error_log('Session data set: ' . json_encode($_SESSION['user']));

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
        error_log('Stack trace: ' . $e->getTraceAsString());
        header('Location: login.php?error=1&message=' . urlencode('Authentication failed: ' . $e->getMessage()));
        exit;
    }
}

// If no POST data, redirect to login
header('Location: login.php');
exit;
