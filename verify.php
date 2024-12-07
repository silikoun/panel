<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/TokenManager.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method is allowed');
    }
    
    // Get token from Authorization header or request body
    $token = null;
    
    // Check Authorization header
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            $token = $matches[1];
        }
    }
    
    // If no token in header, check request body
    if (!$token) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (isset($data['token'])) {
            $token = $data['token'];
        }
    }
    
    // Validate token presence
    if (!$token) {
        throw new Exception('No token provided');
    }
    
    error_log("Token received: " . substr($token, 0, 10) . "...");
    
    // Initialize TokenManager and validate token
    $tokenManager = new TokenManager();
    
    $result = $tokenManager->validateToken($token);
    
    if ($result['valid']) {
        // Update last activity timestamp
        $user = $result['user'];
        $ch = curl_init(getenv('SUPABASE_URL') . '/rest/v1/users?id=eq.' . $user['id']);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
                'Content-Type: application/json',
                'Prefer: return=representation'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'updated_at' => date('c')
            ])
        ]);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode !== 200) {
            error_log("Failed to update last activity. Status code: " . $statusCode);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['last_activity'] = time();
        
        echo json_encode(['valid' => true, 'user' => $user]);
    } else {
        echo json_encode(['valid' => false, 'message' => 'Invalid token']);
    }
} catch (Exception $e) {
    error_log("Error in verify.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
