<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Load environment variables
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Missing environment variables']);
    exit;
}

$client = new GuzzleHttp\Client();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    $userId = $_GET['userId'] ?? '';
    
    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    try {
        $response = $client->request('GET', $supabaseUrl . '/auth/v1/admin/users/' . $userId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $supabaseKey,
                'apikey' => $supabaseKey
            ]
        ]);

        echo $response->getBody();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch user data: ' . $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action']) || $data['action'] !== 'update' || !isset($data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    try {
        // Update user metadata (plan)
        $response = $client->request('PUT', $supabaseUrl . '/auth/v1/admin/users/' . $data['userId'], [
            'headers' => [
                'Authorization' => 'Bearer ' . $supabaseKey,
                'apikey' => $supabaseKey,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'user_metadata' => ['plan' => $data['plan']],
                'app_metadata' => ['status' => $data['status']]
            ]
        ]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update user: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
