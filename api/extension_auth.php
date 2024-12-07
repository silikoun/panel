<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../auth/SupabaseAuth.php';

$auth = new SupabaseAuth();

try {
    $headers = apache_request_headers();
    
    // Check if this is a token verification request
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            $token = $matches[1];
            $result = $auth->verifyExtensionToken($token);
            
            if ($result === false) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired token']);
                exit;
            }

            echo json_encode([
                'valid' => true,
                'user_id' => $result['user_id'],
                'expires_at' => $result['expires_at']
            ]);
            exit;
        }
    }
    
    // If no Authorization header, check for API key
    $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;
    
    if (!$apiKey) {
        throw new Exception('No authentication provided');
    }
    
    // Query Supabase for user with this API key
    $ch = curl_init('https://kgqwiwjayaydewyuygxt.supabase.co/rest/v1/users?api_token=eq.' . urlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY')
        ]
    ]);
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode !== 200) {
        throw new Exception('Database error');
    }
    
    $users = json_decode($response, true);
    
    if (empty($users)) {
        throw new Exception('Invalid API key');
    }
    
    $user = $users[0];
    
    // Check if API key is expired
    if (isset($user['api_token_expires'])) {
        $expiryDate = new DateTime($user['api_token_expires']);
        if ($expiryDate < new DateTime()) {
            throw new Exception('API key has expired');
        }
    }
    
    // Generate a new extension token
    $tokenResult = $auth->generateExtensionToken($user['id']);
    
    echo json_encode([
        'success' => true,
        'token' => $tokenResult['token'],
        'expires_at' => $tokenResult['expires_at'],
        'user' => [
            'id' => $user['id'],
            'email' => $user['email']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
