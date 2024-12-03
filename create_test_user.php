<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$email = 'test@example.com';  // Change this to your desired test email
$password = 'Test123!@#';     // Change this to your desired password

try {
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'http_errors' => false
    ]);

    // Create user
    $response = $client->post('/auth/v1/signup', [
        'json' => [
            'email' => $email,
            'password' => $password
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $body = $response->getBody()->getContents();
    $data = json_decode($body);

    echo "<pre>";
    echo "Status Code: " . $statusCode . "\n";
    echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    
    if ($statusCode === 200) {
        echo "\nTest user created successfully!\n";
        echo "Email: $email\n";
        echo "Password: $password\n";
    } else {
        echo "\nError creating user: " . ($data->error ?? 'Unknown error') . "\n";
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
