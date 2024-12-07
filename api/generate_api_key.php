<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Verify user credentials
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    $email = $data['email'];
    $password = $data['password'];

    // Supabase configuration
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY'); // Using service role key for admin access
    $supabaseAnonKey = getenv('SUPABASE_KEY');

    if (!$supabaseUrl || !$supabaseKey) {
        throw new Exception('Supabase configuration is missing');
    }

    // First, sign in the user to get their ID
    $client = new GuzzleHttp\Client();
    $response = $client->post($supabaseUrl . '/auth/v1/token?grant_type=password', [
        'headers' => [
            'apikey' => $supabaseAnonKey,
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'email' => $email,
            'password' => $password
        ]
    ]);

    $authData = json_decode($response->getBody(), true);
    $userId = $authData['user']['id'];
    $accessToken = $authData['access_token'];

    // Generate new API key
    $apiKey = bin2hex(random_bytes(32)); // 64 characters long
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Update user with new API key in Supabase
    $response = $client->patch($supabaseUrl . '/rest/v1/users?id=eq.' . $userId, [
        'headers' => [
            'apikey' => $supabaseKey,
            'Authorization' => 'Bearer ' . $supabaseKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=minimal'
        ],
        'json' => [
            'api_token' => $apiKey,
            'api_token_expires' => $expiresAt
        ]
    ]);

    if ($response->getStatusCode() !== 204) {
        throw new Exception('Failed to update API key');
    }

    echo json_encode([
        'success' => true,
        'api_key' => $apiKey,
        'expires_at' => $expiresAt,
        'user_id' => $userId
    ]);

} catch (GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $statusCode = $response->getStatusCode();
    $error = json_decode($response->getBody(), true);
    
    http_response_code($statusCode);
    echo json_encode(['error' => $error['message'] ?? 'Authentication failed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
