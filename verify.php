<?php
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the access token from URL
$access_token = $_GET['access_token'] ?? null;
$type = $_GET['type'] ?? '';

if ($access_token && $type === 'signup') {
    // Get user data from Supabase
    $client = new GuzzleHttp\Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ],
        'verify' => false
    ]);

    try {
        // Get user data
        $response = $client->get('/auth/v1/user');
        $userData = json_decode($response->getBody()->getContents(), true);

        // Get the API token from tokens.json
        $tokensFile = __DIR__ . '/tokens.json';
        if (file_exists($tokensFile)) {
            $tokens = json_decode(file_get_contents($tokensFile), true) ?? [];
            $userToken = null;
            
            foreach ($tokens as $token) {
                if ($token['email'] === $userData['email']) {
                    $userToken = $token['api_token'];
                    break;
                }
            }
            
            if ($userToken) {
                $_SESSION['user'] = [
                    'access_token' => $access_token,
                    'token_type' => $_GET['token_type'] ?? 'bearer',
                    'expires_in' => $_GET['expires_in'] ?? 3600,
                    'refresh_token' => $_GET['refresh_token'] ?? '',
                    'expires_at' => $_GET['expires_at'] ?? '',
                    'api_token' => $userToken,
                    'email' => $userData['email']
                ];
                
                // Redirect to the dashboard with success
                header('Location: index.php?verified=1');
                exit;
            }
        }
        
        // If we get here, something went wrong with finding the token
        header('Location: login.php?error=1&message=' . urlencode('Error retrieving API token'));
        exit;
        
    } catch (Exception $e) {
        error_log('Error in verify.php: ' . $e->getMessage());
        header('Location: login.php?error=1&message=' . urlencode('Verification failed'));
        exit;
    }
} else {
    // Invalid verification attempt
    header('Location: login.php?error=1&message=' . urlencode('Invalid verification link'));
    exit;
}
