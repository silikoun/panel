<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$baseUrl = getenv('SITE_URL') ?: 'http://localhost';
$url = $baseUrl . '/api/auth.php';

// Test credentials
$email = 'issambenouhoud@gmail.com';
$password = 'ainsebaa';

function makeRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Origin: ' . getenv('SITE_URL')
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "\nHTTP Status Code: " . $httpCode . "\n";
    echo "Response:\n" . $response . "\n";
    
    return json_decode($response, true);
}

// Test 1: Sign In
echo "\n=== Testing Sign In ===\n";
$signInResult = makeRequest($url, [
    'action' => 'signin',
    'email' => $email,
    'password' => $password
]);

if (!$signInResult || !isset($signInResult['api_key'])) {
    die("Sign in failed\n");
}

$apiKey = $signInResult['api_key'];
$refreshToken = $signInResult['refresh_token'];

// Test 2: Verify API Key
echo "\n=== Testing API Key Verification ===\n";
makeRequest($url, [
    'action' => 'verify_api_key',
    'api_key' => $apiKey
]);

// Test 3: Refresh Token
echo "\n=== Testing Token Refresh ===\n";
makeRequest($url, [
    'action' => 'refresh',
    'refresh_token' => $refreshToken
]);
