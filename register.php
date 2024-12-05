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
    
    // Test Supabase connection first
    $testClient = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => false,
        'timeout' => 30,
        'connect_timeout' => 30
    ]);

    error_log("Testing Supabase connection...");
    try {
        $testResponse = $testClient->get('/rest/v1/');
        error_log("Supabase connection test successful");
    } catch (Exception $e) {
        error_log("Supabase connection test failed: " . $e->getMessage());
        throw new Exception("Failed to connect to Supabase. Please check your credentials.");
    }

    // Generate API token
    function generateApiToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    $apiToken = generateApiToken();
    error_log("Generated API token");
    
    // Set the site URL and redirect URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $siteUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
    $redirectTo = $siteUrl . '/verify.php';
    
    error_log("Site URL: " . $siteUrl);
    error_log("Redirect URL: " . $redirectTo);

    // Prepare signup data
    $signupData = [
        'email' => $email,
        'password' => $password,
        'data' => [
            'plan' => $plan,
            'api_token' => $apiToken
        ],
        'options' => [
            'data' => [
                'plan' => $plan,
                'api_token' => $apiToken
            ],
            'emailRedirectTo' => $redirectTo
        ]
    ];

    error_log("Sending signup request with data: " . json_encode($signupData));

    // Sign up the user
    $signupResponse = $testClient->post('/auth/v1/signup', [
        'json' => $signupData
    ]);

    $statusCode = $signupResponse->getStatusCode();
    $responseBody = json_decode($signupResponse->getBody()->getContents(), true);
    
    error_log("Signup response - Status: " . $statusCode);
    error_log("Response body: " . json_encode($responseBody));

    if ($statusCode === 200 && isset($responseBody['id'])) {
        try {
            // Store token in local file as backup
            $tokenData = [
                'user_id' => $responseBody['id'],
                'email' => $email,
                'api_token' => $apiToken,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $tokensFile = __DIR__ . '/tokens.json';
            $existingTokens = [];
            
            if (file_exists($tokensFile)) {
                $existingTokens = json_decode(file_get_contents($tokensFile), true) ?? [];
            }
            
            // Check if user already has a token
            $existingTokens = array_filter($existingTokens, function($item) use ($email) {
                return $item['email'] !== $email;
            });
            
            $existingTokens[] = $tokenData;
            file_put_contents($tokensFile, json_encode($existingTokens, JSON_PRETTY_PRINT));
            
            error_log("Token stored in local file");
            
            header('Location: signup.php?success=1&message=' . urlencode('Registration successful! Please check your email to verify your account.'));
            exit;
        } catch (Exception $e) {
            error_log("Failed to store token locally: " . $e->getMessage());
            header('Location: signup.php?success=1&message=' . urlencode('Registration successful! Please check your email to verify your account.'));
            exit;
        }
    }
    
    error_log("Registration failed with status code: " . $statusCode);
    throw new Exception("Registration failed with status code: " . $statusCode);

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
