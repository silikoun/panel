<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Test script for generating API key
$baseUrl = getenv('SITE_URL') ?: 'http://localhost';
$url = $baseUrl . '/api/generate_api_key.php';

// Test user credentials
$data = [
    'email' => 'issambenouhoud@gmail.com',
    'password' => 'ainsebaa'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Origin: ' . $baseUrl
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: " . $httpCode . "\n";
echo "Response:\n" . $response . "\n";
