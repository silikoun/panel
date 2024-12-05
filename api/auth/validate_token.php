<?php
// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');
error_reporting(E_ALL);

require_once '../../includes/cors_handler.php';

// Log all headers for debugging
$headers = getallheaders();
error_log('Received headers: ' . json_encode($headers));

handleCORS();

require_once '../../vendor/autoload.php';
require_once '../../classes/Database.php';
require_once '../../classes/TokenManager.php';

// Get raw input and log it
$rawInput = file_get_contents('php://input');
error_log('Raw input received: ' . $rawInput);

header('Content-Type: application/json');

try {
    // Check if JWT_SECRET_KEY is set
    if (!getenv('JWT_SECRET_KEY')) {
        error_log('JWT_SECRET_KEY is not set in environment');
        throw new Exception('JWT_SECRET_KEY environment variable is not set');
    }

    // Initialize Database with error catching
    try {
        $database = new Database();
        $db = $database->connect();
    } catch (Exception $e) {
        error_log('Database connection error: ' . $e->getMessage());
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }

    // Initialize TokenManager
    try {
        $tokenManager = new TokenManager($db);
    } catch (Exception $e) {
        error_log('TokenManager initialization error: ' . $e->getMessage());
        throw new Exception('Token manager initialization failed: ' . $e->getMessage());
    }

    // Parse and validate input
    $data = json_decode($rawInput);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg() . ' for input: ' . $rawInput);
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($data->token)) {
        error_log('Token not provided in request');
        throw new Exception('Token is required');
    }

    error_log('Attempting to validate token of length: ' . strlen($data->token));

    // Check rate limit
    if (!$tokenManager->checkRateLimit($_SERVER['REMOTE_ADDR'])) {
        throw new Exception('Too many validation attempts. Please try again later.');
    }

    // Validate token
    try {
        $tokenData = $tokenManager->validateToken($data->token, 'access');
        if (!$tokenData) {
            error_log('Token validation failed');
            throw new Exception('Invalid token or token has expired');
        }

        // For non-JWT tokens, we'll use default user data
        if (strlen($data->token) === 64 && ctype_xdigit($data->token)) {
            $userData = [
                'id' => 1,
                'email' => 'default@example.com'
            ];
        } else {
            // Get user data for JWT tokens
            $stmt = $db->prepare("SELECT id, email FROM users WHERE id = ?");
            $stmt->execute([$tokenData->sub]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userData) {
                error_log('User not found for token subject: ' . $tokenData->sub);
                throw new Exception('User not found');
            }
        }

        $response = [
            'status' => 'success',
            'valid' => true,
            'message' => 'Token is valid',
            'user_data' => $userData
        ];
    } catch (Exception $e) {
        error_log('Token validation error: ' . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    error_log('Error in validate_token.php: ' . $e->getMessage());
    $response = [
        'status' => 'error',
        'valid' => false,
        'message' => $e->getMessage()
    ];
    http_response_code(400);
}

// Log response before sending
error_log('Sending response: ' . json_encode($response));

// Ensure clean output
while (ob_get_level()) ob_end_clean();
echo json_encode($response);
exit;
