<?php
require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

error_log("Starting email verification process");

// Get verification token from URL
$type = $_GET['type'] ?? '';
$token = $_GET['token'] ?? '';

error_log("Verification type: " . $type);
error_log("Token received: " . substr($token, 0, 10) . '...');

if (empty($type) || empty($token)) {
    error_log("Missing type or token");
    header('Location: login.php?error=1&message=' . urlencode('Invalid verification link'));
    exit;
}

try {
    $client = new Client([
        'verify' => false,
        'http_errors' => false
    ]);

    // Verify the token with Supabase
    $response = $client->get($_ENV['SUPABASE_URL'] . '/auth/v1/verify', [
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'query' => [
            'token' => $token,
            'type' => $type
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $data = json_decode($response->getBody(), true);

    error_log("Verification response - Status: " . $statusCode);
    error_log("Verification response - Body: " . json_encode($data));

    if ($statusCode === 200) {
        // Update user status in our database if needed
        try {
            $publicClient = new Client([
                'verify' => false,
                'http_errors' => false
            ]);

            $publicResponse = $publicClient->patch($_ENV['SUPABASE_URL'] . '/rest/v1/users', [
                'headers' => [
                    'apikey' => $_ENV['SUPABASE_KEY'],
                    'Authorization' => 'Bearer ' . $_ENV['SUPABASE_KEY'],
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation'
                ],
                'json' => [
                    'email_verified' => true,
                    'status' => 'active'
                ]
            ]);

            error_log("User status update response - Status: " . $publicResponse->getStatusCode());
        } catch (Exception $e) {
            error_log("Error updating user status: " . $e->getMessage());
        }

        // Redirect to login with success message
        header('Location: login.php?success=1&message=' . urlencode('Email verified successfully! You can now log in.'));
        exit;
    } else {
        $errorMessage = isset($data['error_description']) ? $data['error_description'] : 
                      (isset($data['msg']) ? $data['msg'] : 
                      (isset($data['error']) ? $data['error'] : 'Verification failed'));
        
        error_log("Verification failed: " . $errorMessage);
        header('Location: login.php?error=1&message=' . urlencode($errorMessage));
        exit;
    }

} catch (Exception $e) {
    error_log("Verification error: " . $e->getMessage());
    header('Location: login.php?error=1&message=' . urlencode('Verification failed. Please try again or contact support.'));
    exit;
}
