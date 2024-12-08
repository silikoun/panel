<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../auth/SupabaseAuth.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Get token from request
$data = json_decode(file_get_contents('php://input'), true);
$token = $data['token'] ?? null;

// Also check Authorization header
if (!$token) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
    }
}

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Token is required']);
    exit();
}

try {
    $auth = new SupabaseAuth();
    $result = $auth->verifyExtensionToken($token);

    if ($result === false) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'error' => 'Invalid token']);
        exit();
    }

    echo json_encode([
        'valid' => true,
        'user_id' => $result['user_id'],
        'expires_at' => $result['expires_at']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log($e->getMessage());
}
