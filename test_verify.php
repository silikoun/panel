<?php
require_once __DIR__ . '/vendor/autoload.php';

// Test configuration
$baseUrl = 'https://panel-production-5838.up.railway.app';
$testToken = '8d257aab281c0179010f379fe992b3bc0068d68f78dd65e1a28258a8b95b96ed'; // Your test token

// Function to make API request
function testEndpoint($url, $token, $method = 'POST') {
    $ch = curl_init($url);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_VERBOSE => true,
        CURLOPT_HEADER => true
    ]);
    
    echo "Testing verification endpoint...\n";
    echo "URL: $url\n";
    echo "Token: $token\n\n";
    
    echo "Sending request...\n";
    $response = curl_exec($ch);
    
    // Split headers and body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "HTTP Status Code: " . $statusCode . "\n\n";
    
    echo "Response Headers:\n";
    echo $headers . "\n";
    
    echo "Response Body:\n";
    echo $body . "\n";
    
    if ($decodedBody = json_decode($body, true)) {
        echo "\nDecoded Response:\n";
        print_r($decodedBody);
    }
    
    curl_close($ch);
}

// Test with Authorization header
echo "=== Testing with Authorization header ===\n";
testEndpoint($baseUrl . '/verify.php', $testToken);

// Test with invalid token
echo "\n\n=== Testing with invalid token ===\n";
testEndpoint($baseUrl . '/verify.php', 'invalid_token_here');

// Test with no token (should return 400)
echo "\n\n=== Testing with no token ===\n";
testEndpoint($baseUrl . '/verify.php', '');
