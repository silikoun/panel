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

// GET request to fetch user data
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
}
// POST request to update or delete user
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action']) || !isset($data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    try {
        if ($data['action'] === 'update') {
            $updateData = [
                'email' => $data['email'],
                'user_metadata' => ['plan' => $data['plan']],
                'app_metadata' => ['status' => $data['status']]
            ];

            // Add password update if provided
            if (!empty($data['password'])) {
                $updateData['password'] = $data['password'];
            }

            $response = $client->request('PUT', $supabaseUrl . '/auth/v1/admin/users/' . $data['userId'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $supabaseKey,
                    'apikey' => $supabaseKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $updateData
            ]);

            echo json_encode(['success' => true]);
        }
        elseif ($data['action'] === 'delete') {
            $response = $client->request('DELETE', $supabaseUrl . '/auth/v1/admin/users/' . $data['userId'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $supabaseKey,
                    'apikey' => $supabaseKey
                ]
            ]);

            echo json_encode(['success' => true]);
        }
        else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Operation failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
