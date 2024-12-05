<?php
// Disable error display
ini_set('display_errors', 0);
error_reporting(0);

// Set headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'API is working correctly'
]);
