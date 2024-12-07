<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../vendor/autoload.php';
require_once '../auth/SupabaseAuth.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$auth = new SupabaseAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['action'])) {
        throw new Exception('Action is required');
    }

    switch ($data['action']) {
        case 'signin':
            if (!isset($data['email']) || !isset($data['password'])) {
                throw new Exception('Email and password are required');
            }

            // Sign in user
            $authResult = $auth->signIn($data['email'], $data['password']);
            
            // Generate API key
            $apiKeyResult = $auth->generateApiKey($authResult['user']['id']);
            
            // Generate JWT
            $jwt = $auth->generateJWT($authResult['user']['id'], $data['email']);

            echo json_encode([
                'success' => true,
                'user' => $authResult['user'],
                'access_token' => $authResult['access_token'],
                'refresh_token' => $authResult['refresh_token'],
                'api_key' => $apiKeyResult['api_key'],
                'api_key_expires' => $apiKeyResult['expires_at'],
                'jwt' => $jwt
            ]);
            break;

        case 'refresh':
            if (!isset($data['refresh_token'])) {
                throw new Exception('Refresh token is required');
            }

            $result = $auth->refreshToken($data['refresh_token']);
            echo json_encode([
                'success' => true,
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token']
            ]);
            break;

        case 'verify_api_key':
            if (!isset($data['api_key'])) {
                throw new Exception('API key is required');
            }

            $user = $auth->verifyApiKey($data['api_key']);
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                throw new Exception('Invalid or expired API key');
            }
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
