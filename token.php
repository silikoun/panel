<?php
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Update CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Get the POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Test connection endpoint
    if (isset($data['test']) && $data['test'] === 'connection') {
        echo json_encode([
            'status' => 'success',
            'message' => 'Connection successful'
        ]);
        exit;
    }

    // Token validation endpoint
    if (isset($data['action']) && $data['action'] === 'validate' && isset($data['token'])) {
        try {
            $token = $data['token'];
            
            // Here you should add your token validation logic
            // For now, we'll just check if it matches a test token
            $isValid = ($token === '81f8a7934e4fafd6593063c7606eb2302e3fbc09fee6538da86037fc0c4643bd');
            
            if ($isValid) {
                echo json_encode([
                    'valid' => true,
                    'message' => 'Token is valid'
                ]);
            } else {
                echo json_encode([
                    'valid' => false,
                    'error' => 'Invalid token'
                ]);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'valid' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode([
    'error' => 'Invalid request'
]);
