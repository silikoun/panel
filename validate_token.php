<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the token from the request
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? '';

if (empty($token)) {
    echo json_encode(['valid' => false, 'message' => 'No token provided']);
    exit;
}

try {
    // Initialize Supabase client
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => false
    ]);

    // Check if token exists in tokens.json
    $tokensFile = __DIR__ . '/tokens.json';
    $tokenValid = false;
    $message = 'Invalid token';

    if (file_exists($tokensFile)) {
        $tokens = json_decode(file_get_contents($tokensFile), true) ?? [];
        foreach ($tokens as $tokenData) {
            if ($tokenData['api_token'] === $token) {
                $tokenValid = true;
                $message = 'Token is valid';
                break;
            }
        }
    }

    echo json_encode([
        'valid' => $tokenValid,
        'message' => $message
    ]);

} catch (Exception $e) {
    error_log("Token validation error: " . $e->getMessage());
    echo json_encode([
        'valid' => false,
        'message' => 'Error validating token'
    ]);
}
