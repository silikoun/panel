<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Initialize environment variables with defaults
$_ENV['SUPABASE_URL'] = getenv('SUPABASE_URL') ?: '';
$_ENV['SUPABASE_KEY'] = getenv('SUPABASE_KEY') ?: '';

// Only try to load .env if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
    } catch (Exception $e) {
        // Log error but don't crash
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

error_log("Starting registration process");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: signup.php');
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$plan = $_POST['plan'] ?? 'free';

error_log("Registration attempt - Email: " . $email . ", Plan: " . $plan);

// Validate inputs
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("Invalid email format: " . $email);
    header('Location: signup.php?error=1&message=' . urlencode('Invalid email address'));
    exit;
}

if (strlen($password) < 8) {
    error_log("Password too short");
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
    
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => false
    ]);

    // Generate API token
    function generateApiToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    $apiToken = generateApiToken();
    
    // Set the site URL and redirect URL
    $siteUrl = isset($_ENV['SITE_URL']) ? $_ENV['SITE_URL'] : 'https://' . $_SERVER['HTTP_HOST'];
    $redirectTo = $siteUrl . '/verify.php';

    // Sign up the user with the API token included in metadata
    $signupResponse = $client->post('/auth/v1/signup', [
        'json' => [
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
        ]
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
            
            // Show success message and ask user to verify email
            header('Location: signup.php?success=1&message=' . urlencode('Registration successful! Please check your email to verify your account.'));
            exit;
        } catch (Exception $e) {
            error_log("Failed to store token locally: " . $e->getMessage());
            // Continue with registration even if token storage fails
            header('Location: signup.php?success=1&message=' . urlencode('Registration successful! Please check your email to verify your account.'));
            exit;
        }
    }
    
    error_log("Registration failed with status code: " . $statusCode);
    header('Location: signup.php?error=1&message=' . urlencode('Registration failed. Please try again.'));
    exit;

} catch (RequestException $e) {
    error_log("Registration error: " . $e->getMessage());
    if ($e->hasResponse()) {
        $errorBody = json_decode($e->getResponse()->getBody(), true);
        error_log("Error response: " . print_r($errorBody, true));
        
        // Check for specific error messages
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
    header('Location: signup.php?error=1&message=' . urlencode('System error. Please try again.'));
    exit;
}
