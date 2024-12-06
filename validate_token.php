<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require 'vendor/autoload.php';
require_once 'classes/Database.php';
require_once 'classes/TokenManager.php';

use Dotenv\Dotenv;

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/token_validation.log');
error_log("=== New token validation request ===");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'message' => 'Method not allowed']);
    exit();
}

// Initialize Database and TokenManager
try {
    $database = new Database();
    $db = $database->connect();
    $tokenManager = new TokenManager($db);
} catch (Exception $e) {
    error_log("Initialization error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'System configuration error']);
    exit();
}

// Get the token from various possible sources
$token = null;
$contentType = $_SERVER["CONTENT_TYPE"] ?? '';

if (strpos($contentType, 'application/json') !== false) {
    // Handle JSON request
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $token = $jsonData['token'] ?? null;
    error_log("Received JSON request");
} else if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
    // Handle form data
    $token = $_POST['token'] ?? null;
    error_log("Received form data request");
} else {
    // Try both sources as fallback
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $token = $jsonData['token'] ?? $_POST['token'] ?? null;
    error_log("Content-Type not specified, attempting to parse both JSON and form data");
}

if (empty($token)) {
    error_log("No token provided in request");
    http_response_code(400);
    echo json_encode(['valid' => false, 'message' => 'No token provided']);
    exit();
}

try {
    // Check rate limiting
    if (!$tokenManager->checkRateLimit($_SERVER['REMOTE_ADDR'])) {
        http_response_code(429);
        echo json_encode([
            'valid' => false,
            'message' => 'Too many validation attempts. Please try again later.'
        ]);
        exit();
    }

    // Validate the token
    $tokenData = $tokenManager->validateToken($token);
    
    // Get user data
    $stmt = $db->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$tokenData->sub]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        throw new Exception('User not found');
    }

    // Return success response with user data
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $userData['id'],
            'email' => $userData['email']
        ],
        'expires' => $tokenData->exp
    ]);

} catch (Exception $e) {
    $statusCode = 401; // Default to unauthorized
    
    // Determine appropriate status code based on error
    if (strpos($e->getMessage(), 'Too many') !== false) {
        $statusCode = 429; // Rate limit
    } else if (strpos($e->getMessage(), 'System configuration') !== false) {
        $statusCode = 500; // Server error
    }
    
    error_log("Validation error: " . $e->getMessage());
    http_response_code($statusCode);
    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}

error_log("=== Token validation request completed ===");
