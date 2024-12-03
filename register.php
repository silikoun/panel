<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

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
        'verify' => __DIR__ . '/certs/cacert.pem'
    ]);

    // First, sign up the user
    $signupResponse = $client->post('/auth/v1/signup', [
        'json' => [
            'email' => $email,
            'password' => $password
        ]
    ]);

    $statusCode = $signupResponse->getStatusCode();
    $body = $signupResponse->getBody()->getContents();
    
    error_log("Signup response - Status: " . $statusCode);
    error_log("Response body: " . $body);

    if ($statusCode === 200) {
        // Now sign in to get the session
        $loginResponse = $client->post('/auth/v1/token?grant_type=password', [
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $loginBody = $loginResponse->getBody()->getContents();
        $loginData = json_decode($loginBody, true);

        if (isset($loginData['access_token'])) {
            error_log("Registration and login successful");
            // Store user data in session
            session_start();
            $_SESSION['user'] = $loginData;
            
            // Update user metadata with plan
            $client->post('/auth/v1/user', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $loginData['access_token']
                ],
                'json' => [
                    'data' => [
                        'plan' => $plan
                    ]
                ]
            ]);
            
            // Redirect to dashboard
            header('Location: index.php');
            exit;
        }
    }
    
    $data = json_decode($body, true);
    $error = $data['error_description'] ?? $data['msg'] ?? 'Registration failed';
    error_log("Registration failed with error: " . $error);
    header('Location: signup.php?error=1&message=' . urlencode($error));
    exit;

} catch (RequestException $e) {
    error_log("Registration request error: " . $e->getMessage());
    if ($e->hasResponse()) {
        $response = $e->getResponse();
        $errorBody = $response->getBody()->getContents();
        error_log("Error response body: " . $errorBody);
        $data = json_decode($errorBody, true);
        $error = $data['error_description'] ?? $data['msg'] ?? 'Registration failed';
    } else {
        $error = 'Connection error';
    }
    header('Location: signup.php?error=1&message=' . urlencode($error));
    exit;
} catch (Exception $e) {
    error_log("General registration error: " . $e->getMessage());
    header('Location: signup.php?error=1&message=' . urlencode('System error'));
    exit;
}
