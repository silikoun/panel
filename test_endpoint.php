<?php
$token = '8d257aab281c0179010f379fe992b3bc0068d68f78dd65e1a28258a8b95b96ed';
$url = 'https://panel-production-5838.up.railway.app/validate_token.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing token validation...\n";
echo "URL: $url\n";
echo "Token: $token\n\n";

// Initialize cURL
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $token]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

// Create a temporary file for the verbose output
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Execute the request
echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Get verbose information
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Status Code: " . $httpCode . "\n\n";
echo "Verbose Log:\n" . $verboseLog . "\n\n";

if ($response === false) {
    echo "cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "Response:\n" . $response . "\n";
    
    // Try to decode JSON response
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\nDecoded Response:\n";
        print_r($data);
    }
}

curl_close($ch);

// Now test with the token in Authorization header
echo "\n\nTesting with Authorization header...\n";
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_VERBOSE, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing only

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

echo "HTTP Status Code: " . $httpCode . "\n\n";
echo "Verbose Log:\n" . $verboseLog . "\n\n";

if ($response === false) {
    echo "cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "Response:\n" . $response . "\n";
    
    // Try to decode JSON response
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\nDecoded Response:\n";
        print_r($data);
    }
}

curl_close($ch);
