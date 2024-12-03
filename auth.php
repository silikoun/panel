<?php
ob_start();

ini_set('memory_limit', '1G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => __DIR__ . '/certs/cacert.pem'
    ]);
} catch (Exception $e) {
    error_log('Client initialization error: ' . $e->getMessage());
    die('Initialization error: ' . $e->getMessage());
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    unset($_SESSION['user']);
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $response = $client->post('/auth/v1/token?grant_type=password', [
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['SUPABASE_KEY']
            ],
            'json' => [
                'email' => $email,
                'password' => $password
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        error_log("Auth Response - Status: $statusCode, Body: $body");

        if ($statusCode === 200 && isset($data['access_token'])) {
            $_SESSION['user'] = $data;
            header('Location: index.php');
            exit;
        } else {
            $errorMessage = $data['error_description'] ?? $data['msg'] ?? 'Authentication failed';
            header('Location: index.php?error=1&message=' . urlencode($errorMessage));
            exit;
        }
    } catch (RequestException $e) {
        error_log("Auth Error: " . $e->getMessage());
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $errorBody = $response->getBody()->getContents();
            error_log("Error Response: " . $errorBody);
        }
        header('Location: index.php?error=1&message=' . urlencode('Connection error'));
        exit;
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        header('Location: index.php?error=1&message=' . urlencode('System error'));
        exit;
    }
}

ob_end_clean();
header('Location: index.php');
exit;
