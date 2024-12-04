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

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    ob_end_clean();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $client = new Client();
        
        $response = $client->post('https://kgqwiwjayaydewyuygxt.supabase.co/auth/v1/token?grant_type=password', [
            'headers' => [
                'apikey' => $_ENV['SUPABASE_KEY'],
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'email' => $email,
                'password' => $password,
                'grant_type' => 'password'
            ],
            'verify' => false
        ]);

        $data = json_decode($response->getBody(), true);
        error_log("Auth Response: " . print_r($data, true));

        if (isset($data['access_token'])) {
            $_SESSION['user'] = $data;
            $_SESSION['user_email'] = $email;
            ob_end_clean();
            header('Location: index.php');
            exit;
        } else {
            error_log("Auth failed: " . print_r($data, true));
            ob_end_clean();
            header('Location: login.php?error=1&message=' . urlencode('Invalid credentials'));
            exit;
        }
    } catch (RequestException $e) {
        error_log("Auth Error: " . $e->getMessage());
        if ($e->hasResponse()) {
            $errorBody = json_decode($e->getResponse()->getBody(), true);
            error_log("Error Response: " . print_r($errorBody, true));
        }
        ob_end_clean();
        header('Location: login.php?error=1&message=' . urlencode('Authentication failed'));
        exit;
    } catch (Exception $e) {
        error_log("General Error: " . $e->getMessage());
        ob_end_clean();
        header('Location: login.php?error=1&message=' . urlencode('System error'));
        exit;
    }
} else {
    ob_end_clean();
    header('Location: login.php');
    exit;
}
