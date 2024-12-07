<?php
session_start();
require __DIR__ . '/vendor/autoload.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Load environment variables
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseKey) {
    error_log('Missing Supabase environment variables');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

$client = new GuzzleHttp\Client([
    'verify' => false, // Only if needed for local development
    'http_errors' => false // Handle errors manually
]);

// GET request to fetch user data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get') {
    $userId = $_GET['userId'] ?? '';
    
    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required']);
        exit;
    }

    try {
        error_log("Fetching user data for ID: " . $userId);
        $response = $client->request('GET', $supabaseUrl . '/auth/v1/admin/users/' . $userId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $supabaseKey,
                'apikey' => $supabaseKey
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        error_log("Supabase response status: " . $statusCode);
        error_log("Supabase response body: " . $body);

        if ($statusCode === 200) {
            header('Content-Type: application/json');
            echo $body;
        } else {
            http_response_code($statusCode);
            echo json_encode(['error' => 'Failed to fetch user data from Supabase']);
        }
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}
// POST request to update or delete user
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action']) || !isset($data['userId'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
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

            error_log("Updating user: " . $data['userId']);
            $response = $client->request('PUT', $supabaseUrl . '/auth/v1/admin/users/' . $data['userId'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $supabaseKey,
                    'apikey' => $supabaseKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => $updateData
            ]);

            $statusCode = $response->getStatusCode();
            error_log("Update response status: " . $statusCode);

            if ($statusCode === 200) {
                echo json_encode(['success' => true]);
            } else {
                $body = (string) $response->getBody();
                error_log("Update error response: " . $body);
                http_response_code($statusCode);
                echo json_encode(['error' => 'Failed to update user']);
            }
        }
        elseif ($data['action'] === 'delete') {
            error_log("Deleting user: " . $data['userId']);
            $response = $client->request('DELETE', $supabaseUrl . '/auth/v1/admin/users/' . $data['userId'], [
                'headers' => [
                    'Authorization' => 'Bearer ' . $supabaseKey,
                    'apikey' => $supabaseKey
                ]
            ]);

            $statusCode = $response->getStatusCode();
            error_log("Delete response status: " . $statusCode);

            if ($statusCode === 200) {
                echo json_encode(['success' => true]);
            } else {
                $body = (string) $response->getBody();
                error_log("Delete error response: " . $body);
                http_response_code($statusCode);
                echo json_encode(['error' => 'Failed to delete user']);
            }
        }
        else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Operation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
