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

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

error_log("=== Starting token validation ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// Get and validate the request body
$rawInput = file_get_contents('php://input');
error_log("Raw input received: " . $rawInput);

// If the input is empty, try getting from POST
if (empty($rawInput)) {
    error_log("Raw input was empty, checking POST data");
    $token = $_POST['token'] ?? '';
    if (!empty($token)) {
        $data = ['token' => $token];
        error_log("Found token in POST data: " . $token);
    } else {
        error_log("No token found in POST data");
        http_response_code(400);
        echo json_encode(['valid' => false, 'message' => 'No token provided']);
        exit;
    }
} else {
    try {
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON decode error: " . json_last_error_msg());
            throw new Exception('Invalid JSON format');
        }
    } catch (Exception $e) {
        error_log("Error parsing JSON: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'message' => 'Invalid request format: ' . $e->getMessage()
        ]);
        exit;
    }
}

$token = $data['token'] ?? '';
error_log("Token to validate: " . $token);

if (empty($token)) {
    error_log("Token is empty");
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'No token provided']);
    exit;
}

try {
    // Check if token exists in tokens.json
    $tokensFile = __DIR__ . '/tokens.json';
    error_log("Looking for tokens file at: " . $tokensFile);
    
    if (!file_exists($tokensFile)) {
        error_log("tokens.json file not found!");
        throw new Exception('Token storage file not found');
    }

    $tokensContent = file_get_contents($tokensFile);
    if ($tokensContent === false) {
        error_log("Failed to read tokens.json");
        throw new Exception('Could not read token storage file');
    }

    $tokens = json_decode($tokensContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse tokens.json: " . json_last_error_msg());
        throw new Exception('Error parsing token storage file');
    }

    error_log("Number of tokens in file: " . count($tokens));
    
    $tokenValid = false;
    $message = 'Invalid token';
    
    foreach ($tokens as $index => $tokenData) {
        error_log("Checking token " . ($index + 1) . ": " . $tokenData['api_token']);
        if (trim($tokenData['api_token']) === trim($token)) {
            $tokenValid = true;
            $message = 'Token is valid';
            error_log("Token match found!");
            break;
        }
    }

    if (!$tokenValid) {
        error_log("No matching token found");
        error_log("Provided token: " . $token);
        error_log("Available tokens: " . json_encode(array_column($tokens, 'api_token')));
    }

    $response = [
        'valid' => $tokenValid,
        'message' => $message
    ];
    
    error_log("Sending response: " . json_encode($response));
    http_response_code($tokenValid ? 200 : 401);
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error during validation: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'Error validating token: ' . $e->getMessage()
    ]);
}

error_log("=== Token validation completed ===");
