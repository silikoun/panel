<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'vendor/autoload.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

use Dotenv\Dotenv;
use GuzzleHttp\Client;

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    try {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        error_log("Environment variables loaded successfully");
    } catch (Exception $e) {
        error_log("Error loading .env file: " . $e->getMessage());
    }
}

// Get and validate the request body
$rawInput = file_get_contents('php://input');
error_log("Received raw input: " . $rawInput);

try {
    $data = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg() . " for input: " . $rawInput);
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    error_log("Decoded JSON data: " . json_encode($data));
} catch (Exception $e) {
    error_log("JSON parsing error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid request format'
    ]);
    exit;
}

$token = $data['token'] ?? '';
error_log("Extracted token: " . $token);

if (empty($token)) {
    error_log("No token provided in request");
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'No token provided']);
    exit;
}

error_log("Processing token validation for token: " . $token);

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

    if (!file_exists($tokensFile)) {
        error_log("tokens.json file not found at path: " . $tokensFile);
        throw new Exception('Token storage file not found');
    }

    $tokensContent = file_get_contents($tokensFile);
    if ($tokensContent === false) {
        error_log("Could not read tokens.json at path: " . $tokensFile);
        throw new Exception('Could not read token storage file');
    }
    error_log("Loaded tokens.json content: " . $tokensContent);

    $tokens = json_decode($tokensContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error parsing tokens.json: " . json_last_error_msg() . " for content: " . $tokensContent);
        throw new Exception('Error parsing token storage file');
    }
    error_log("Number of tokens in file: " . count($tokens));

    foreach ($tokens as $tokenData) {
        error_log("Comparing token: " . $tokenData['api_token'] . " with provided token: " . $token);
        if ($tokenData['api_token'] === $token) {
            $tokenValid = true;
            $message = 'Token is valid';
            error_log("Token match found!");
            break;
        }
    }

    if (!$tokenValid) {
        error_log("No matching token found in tokens.json");
    }

    http_response_code($tokenValid ? 200 : 401);
    echo json_encode([
        'valid' => $tokenValid,
        'message' => $message
    ]);

} catch (Exception $e) {
    error_log("Token validation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'Error validating token: ' . $e->getMessage()
    ]);
}
