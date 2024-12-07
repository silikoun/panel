<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load environment variables
try {
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        error_log("Loaded .env file");
    } else {
        error_log(".env file not found");
    }
} catch (Exception $e) {
    error_log("Error loading .env file: " . $e->getMessage());
}

// Function to get environment variable from multiple sources
function getEnvVar($name) {
    $value = getenv($name);
    if ($value === false) {
        $value = isset($_ENV[$name]) ? $_ENV[$name] : null;
    }
    if ($value === null) {
        $value = isset($_SERVER[$name]) ? $_SERVER[$name] : null;
    }
    return $value;
}

// Check required environment variables
$requiredEnvVars = ['SUPABASE_URL', 'SUPABASE_SERVICE_ROLE_KEY', 'JWT_SECRET_KEY'];
$missingVars = [];

foreach ($requiredEnvVars as $var) {
    $value = getEnvVar($var);
    if (!$value) {
        $missingVars[] = $var;
        error_log("Missing required environment variable: " . $var);
    } else {
        error_log("Found environment variable: " . $var . " (length: " . strlen($value) . ")");
    }
}

if (!empty($missingVars)) {
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'System configuration error: Missing environment variables: ' . implode(', ', $missingVars)
    ]);
    exit();
}

// Get the token from either the Authorization header or POST body
$token = null;
$headers = apache_request_headers();

if (isset($headers['Authorization'])) {
    $auth = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
        $token = $matches[1];
        error_log("Token found in Authorization header");
    }
} else {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (isset($data['token'])) {
        $token = $data['token'];
        error_log("Token found in request body");
    }
}

if (!$token) {
    http_response_code(400);
    echo json_encode([
        'valid' => false,
        'message' => 'No token provided'
    ]);
    exit();
}

try {
    require_once __DIR__ . '/classes/TokenManager.php';
    $tokenManager = new TokenManager();
    $result = $tokenManager->validateToken($token);
    
    if ($result['valid']) {
        http_response_code(200);
    } else {
        http_response_code(401);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error validating token: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'Error validating token: ' . $e->getMessage()
    ]);
}
