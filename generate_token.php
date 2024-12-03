<?php
require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

header('Content-Type: application/json');

// Check if user is logged in and has access token
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['access_token'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$userId = $_SESSION['user']['user']['id'];
$accessToken = $_SESSION['user']['access_token'];

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function storeToken($userId, $token, $accessToken) {
    try {
        $client = new Client([
            'base_uri' => $_ENV['SUPABASE_URL'],
            'headers' => [
                'apikey' => $_ENV['SUPABASE_KEY'],
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal'
            ],
            'verify' => __DIR__ . '/certs/cacert.pem'
        ]);

        // Update metadata
        $response = $client->put('/auth/v1/user', [
            'json' => [
                'data' => [
                    'api_token' => $token
                ]
            ]
        ]);

        $statusCode = $response->getStatusCode();
        error_log('Token update status code: ' . $statusCode);
        
        if ($statusCode === 200) {
            // Update session
            if (!isset($_SESSION['user']['user']['user_metadata'])) {
                $_SESSION['user']['user']['user_metadata'] = [];
            }
            $_SESSION['user']['user']['user_metadata']['api_token'] = $token;
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log('Error storing token: ' . $e->getMessage());
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            error_log('Response: ' . $e->getResponse()->getBody());
        }
        return false;
    }
}

try {
    // Generate new token
    $token = generateSecureToken();
    
    if (storeToken($userId, $token, $accessToken)) {
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error storing token']);
    }
} catch (Exception $e) {
    error_log('Token generation error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error generating token']);
}
