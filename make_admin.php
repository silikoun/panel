<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

$client = new Client([
    'verify' => false,
    'http_errors' => false
]);

// The email of the user you want to make admin
$adminEmail = 'admin@wooscraper.com';

try {
    // First, get the user by email
    $response = $client->get('https://kgqwiwjayaydewyuygxt.supabase.co/auth/v1/admin/users?email=' . urlencode($adminEmail), [
        'headers' => [
            'apikey' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.YkPeg0kQJhxYOv3Yc9u9R5w_uUQCw5o3CLLJOEfNx4M',
            'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.YkPeg0kQJhxYOv3Yc9u9R5w_uUQCw5o3CLLJOEfNx4M'
        ]
    ]);

    $userData = json_decode($response->getBody(), true);
    
    if (!empty($userData)) {
        $userId = $userData[0]['id'];
        echo "Found user with ID: " . $userId . "\n";

        // Update user metadata to make them admin
        $updateResponse = $client->put('https://kgqwiwjayaydewyuygxt.supabase.co/auth/v1/admin/users/' . $userId, [
            'headers' => [
                'apikey' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.YkPeg0kQJhxYOv3Yc9u9R5w_uUQCw5o3CLLJOEfNx4M',
                'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.YkPeg0kQJhxYOv3Yc9u9R5w_uUQCw5o3CLLJOEfNx4M',
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'user_metadata' => [
                    'is_admin' => true
                ],
                'app_metadata' => [
                    'role' => 'admin'
                ]
            ]
        ]);

        echo "User role updated successfully!\n";
        echo "Update response: " . $updateResponse->getBody() . "\n";
    } else {
        echo "User not found with email: " . $adminEmail . "\n";
        echo "Response: " . $response->getBody() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
