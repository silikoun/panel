<?php
// Prevent any output before headers
ob_start();

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

error_log("Starting registration process - " . date('Y-m-d H:i:s'));

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
        error_log("Loaded environment variables from .env file");
    } catch (Exception $e) {
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

// Explicitly set Supabase credentials
$_ENV['SUPABASE_URL'] = 'https://kgqwiwjayaydewyuygxt.supabase.co';
$_ENV['SUPABASE_KEY'] = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3MzMyNDI0MTYsImV4cCI6MjA0ODgxODQxNn0._ZUb83R2usvsrSgslrV6Fk4TX1Re3d1clNuU2LPyTtI';

error_log("Using Supabase URL: " . $_ENV['SUPABASE_URL']);
error_log("Supabase key length: " . strlen($_ENV['SUPABASE_KEY']));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.php');
    exit;
}

// Get POST data with proper validation
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);
$confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW);
$plan = filter_input(INPUT_POST, 'plan', FILTER_UNSAFE_RAW) ?? 'free';

error_log("Registration attempt - Email: " . ($email ?? 'not set') . ", Plan: " . $plan);

// Validate inputs
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Invalid email format: " . ($email ?? 'not set'));
    header('Location: signup.php?error=1&message=' . urlencode('Invalid email address'));
    exit;
}

if (!$password || strlen($password) < 8) {
    error_log("Password too short or not set");
    header('Location: signup.php?error=1&message=' . urlencode('Password must be at least 8 characters long'));
    exit;
}

if ($password !== $confirm_password) {
    error_log("Passwords do not match");
    header('Location: signup.php?error=1&message=' . urlencode('Passwords do not match'));
    exit;
}

try {
    error_log("Initializing Supabase client");

    // Generate API token
    function generateApiToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    $apiToken = generateApiToken();
    error_log("Generated API token");

    // Set the site URL and redirect URL
    $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
    $redirectTo = $siteUrl . '/verify.php';

    error_log("Site URL: " . $siteUrl);
    error_log("Redirect URL: " . $redirectTo);

    $client = new Client([
        'verify' => false,
        'http_errors' => false
    ]);

    // Register user with Supabase
    $response = $client->post($_ENV['SUPABASE_URL'] . '/auth/v1/signup', [
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'json' => [
            'email' => $email,
            'password' => $password,
            'data' => [
                'plan' => $plan,
                'api_token' => $apiToken
            ],
            'email_confirm' => true,
            'gotrue_meta_security' => [
                'captcha_token' => null
            ]
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $data = json_decode($response->getBody(), true);

    error_log("Supabase registration response - Status: " . $statusCode);
    error_log("Supabase registration response - Body: " . json_encode($data));

    if ($statusCode === 200 || $statusCode === 201) {
        // Insert user data into our database table
        try {
            $publicClient = new Client([
                'verify' => false,
                'http_errors' => false
            ]);

            $publicResponse = $publicClient->post($_ENV['SUPABASE_URL'] . '/rest/v1/users', [
                'headers' => [
                    'apikey' => $_ENV['SUPABASE_KEY'],
                    'Authorization' => 'Bearer ' . $_ENV['SUPABASE_KEY'],
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation'
                ],
                'json' => [
                    'id' => $data['user']['id'],
                    'email' => $email,
                    'plan' => $plan,
                    'api_token' => $apiToken,
                    'api_token_expires' => date('Y-m-d H:i:s', strtotime('+30 days'))
                ]
            ]);

            error_log("Public user data insertion response - Status: " . $publicResponse->getStatusCode());
            error_log("Public user data insertion response - Body: " . $publicResponse->getBody());
        } catch (Exception $e) {
            error_log("Error inserting public user data: " . $e->getMessage());
        }

        // Redirect to success page with instructions
        $successMessage = "Registration successful! Please check your email to verify your account. If you don't see the email, please check your spam folder.";
        header('Location: signup.php?success=1&message=' . urlencode($successMessage));
        exit;
    } else {
        $errorMessage = isset($data['error_description']) ? $data['error_description'] : 
                      (isset($data['msg']) ? $data['msg'] : 
                      (isset($data['error']) ? $data['error'] : 'Registration failed'));
        
        error_log("Registration failed: " . $errorMessage);
        header('Location: signup.php?error=1&message=' . urlencode($errorMessage));
        exit;
    }

} catch (RequestException $e) {
    error_log("Registration error (RequestException): " . $e->getMessage());
    if ($e->hasResponse()) {
        $errorBody = json_decode($e->getResponse()->getBody(), true);
        error_log("Error response: " . print_r($errorBody, true));
        
        $errorMessage = $errorBody['msg'] ?? $errorBody['error']['message'] ?? 'Registration failed';
        if (stripos($errorMessage, 'already registered') !== false) {
            $errorMessage = 'This email is already registered';
        }
        
        header('Location: signup.php?error=1&message=' . urlencode($errorMessage));
    } else {
        header('Location: signup.php?error=1&message=' . urlencode('Connection error. Please try again.'));
    }
    exit;
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    header('Location: signup.php?error=1&message=' . urlencode($e->getMessage()));
    exit;
}

// Ensure all output is sent and clean the buffer
ob_end_flush();

// Create admin user if it doesn't exist
$createAdmin = true;
if ($createAdmin) {
    $adminEmail = 'admin@wooscraper.com';
    $adminPassword = 'password123';

    try {
        $adminClient = new Client([
            'base_uri' => $_ENV['SUPABASE_URL'],
            'headers' => [
                'apikey' => $_ENV['SUPABASE_KEY'],
                'Content-Type' => 'application/json'
            ],
            'verify' => false,
            'timeout' => 30,
            'connect_timeout' => 30
        ]);

        $adminResponse = $adminClient->post('/auth/v1/signup', [
            'json' => [
                'email' => $adminEmail,
                'password' => $adminPassword
            ]
        ]);

        $adminStatusCode = $adminResponse->getStatusCode();
        $adminResponseBody = json_decode($adminResponse->getBody()->getContents(), true);

        if ($adminStatusCode === 200 && isset($adminResponseBody['id'])) {
            error_log("Admin user created successfully");
        } else {
            error_log("Failed to create admin user");
        }
    } catch (RequestException $e) {
        error_log("Error creating admin user: " . $e->getMessage());
    }
}
