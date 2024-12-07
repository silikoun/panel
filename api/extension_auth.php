<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Get API key from header
    $headers = apache_request_headers();
    $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;
    
    if (!$apiKey) {
        throw new Exception('No API key provided');
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
        $expiryDate = strtotime($user['api_token_expires']);
        if ($expiryDate < time()) {
            throw new Exception('API key expired');
        }
    }
    
    // Return success with user info
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
