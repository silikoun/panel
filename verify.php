<?php
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the access token from URL
$access_token = $_GET['access_token'] ?? null;
$type = $_GET['type'] ?? '';

if ($access_token && $type === 'signup') {
    // Store the token in session
    $_SESSION['user'] = [
        'access_token' => $access_token,
        'token_type' => $_GET['token_type'] ?? 'bearer',
        'expires_in' => $_GET['expires_in'] ?? 3600,
        'refresh_token' => $_GET['refresh_token'] ?? '',
        'expires_at' => $_GET['expires_at'] ?? ''
    ];
    
    // Redirect to the dashboard
    header('Location: index.php?verified=1');
    exit;
} else {
    // Invalid verification attempt
    header('Location: login.php?error=1&message=' . urlencode('Invalid verification link'));
    exit;
}
