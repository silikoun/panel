<?php
function handleCORS() {
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
        
        // Explicitly allow required headers
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, Authorization');
        header('Access-Control-Max-Age: 3600');
        exit(0);
    }

    // Set response headers
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
