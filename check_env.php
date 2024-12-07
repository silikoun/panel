<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Function to safely check environment variables
function checkEnvVar($name) {
    $value = getenv($name);
    if ($value === false) {
        $value = isset($_ENV[$name]) ? $_ENV[$name] : null;
    }
    if ($value === null) {
        $value = isset($_SERVER[$name]) ? $_SERVER[$name] : null;
    }
    return [
        'name' => $name,
        'exists' => $value !== null && $value !== false,
        'length' => $value ? strlen($value) : 0
    ];
}

// Check required environment variables
$envVars = [
    'SUPABASE_URL',
    'SUPABASE_SERVICE_ROLE_KEY',
    'JWT_SECRET_KEY'
];

$results = [];
foreach ($envVars as $var) {
    $results[$var] = checkEnvVar($var);
}

// Add PHP version and loaded extensions
$results['php_info'] = [
    'version' => PHP_VERSION,
    'loaded_extensions' => get_loaded_extensions()
];

// Check if the JWT extension is available
$results['jwt_available'] = class_exists('Firebase\JWT\JWT');

echo json_encode($results, JSON_PRETTY_PRINT);
