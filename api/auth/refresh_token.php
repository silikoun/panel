<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers,Content-Type,Access-Control-Allow-Methods,Authorization,X-Requested-With');

require_once '../../vendor/autoload.php';
require_once '../../classes/Database.php';
require_once '../../classes/TokenManager.php';

$database = new Database();
$db = $database->connect();
$tokenManager = new TokenManager($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->refresh_token)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Refresh token is required'
    ]);
    exit();
}

try {
    // Check rate limiting
    if (!$tokenManager->checkRateLimit($_SERVER['REMOTE_ADDR'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Too many refresh attempts. Please try again later.'
        ]);
        exit();
    }

    // Refresh tokens
    $tokens = $tokenManager->refreshTokens($data->refresh_token);
    
    if (!$tokens) {
        // If refresh fails, revoke the old token
        $tokenManager->revokeToken($data->refresh_token, 'refresh_failed');
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or expired refresh token'
        ]);
        exit();
    }

    // Return new tokens
    echo json_encode([
        'status' => 'success',
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token'],
        'expires_in' => $tokens['expires_in']
    ]);

} catch (Exception $e) {
    error_log('Token refresh error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Token refresh failed'
    ]);
}
?>
