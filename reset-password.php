<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit;
}

$email = $_POST['email'] ?? '';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot-password.php?error=1&message=' . urlencode('Invalid email address'));
    exit;
}

try {
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => __DIR__ . '/certs/cacert.pem'
    ]);

    // Request password reset from Supabase
    $response = $client->post('/auth/v1/recover', [
        'json' => [
            'email' => $email
        ]
    ]);

    $statusCode = $response->getStatusCode();

    if ($statusCode === 200) {
        header('Location: forgot-password.php?success=1');
        exit;
    } else {
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        $error = $data['error_description'] ?? $data['msg'] ?? 'Password reset request failed';
        header('Location: forgot-password.php?error=1&message=' . urlencode($error));
        exit;
    }
} catch (RequestException $e) {
    error_log("Password reset error: " . $e->getMessage());
    if ($e->hasResponse()) {
        $response = $e->getResponse();
        $errorBody = $response->getBody()->getContents();
        error_log("Error response: " . $errorBody);
        $data = json_decode($errorBody, true);
        $error = $data['error_description'] ?? $data['msg'] ?? 'Password reset request failed';
    } else {
        $error = 'Connection error';
    }
    header('Location: forgot-password.php?error=1&message=' . urlencode($error));
    exit;
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    header('Location: forgot-password.php?error=1&message=' . urlencode('System error'));
    exit;
}
