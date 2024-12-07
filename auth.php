<?php
session_start();
require 'vendor/autoload.php';
require_once 'auth/SupabaseAuth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        header('Location: login.php?error=1&message=' . urlencode('Email and password are required'));
        exit;
    }

    try {
        // Initialize Supabase client
        $supabase = new SupabaseAuth();
        
        // Attempt to sign in
        $authData = $supabase->signIn($email, $password);
        
        if (isset($authData['access_token'])) {
            // Get user role from database
            $client = $supabase->createClient();
            $userQuery = $client->from('users')
                ->select('*')
                ->eq('email', $email)
                ->execute();
            
            $user = $userQuery->data[0] ?? null;
            $role = $user['role'] ?? 'user';
            
            error_log('User role from database: ' . $role);
            
            // Store session data
            $_SESSION['user'] = [
                'id' => $authData['user']['id'],
                'email' => $authData['user']['email'],
                'access_token' => $authData['access_token'],
                'refresh_token' => $authData['refresh_token'],
                'role' => $role
            ];

            error_log('Session data: ' . json_encode($_SESSION['user']));

            // Redirect based on role
            if ($role === 'admin') {
                error_log('Redirecting to admin dashboard');
                header('Location: admin_dashboard.php');
            } else {
                error_log('Redirecting to client dashboard');
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $errorMessage = $authData['error_description'] ?? ($authData['error'] ?? 'Invalid credentials');
            error_log('Login Error: ' . $errorMessage);
            header('Location: login.php?error=1&message=' . urlencode($errorMessage));
            exit;
        }
    } catch (Exception $e) {
        error_log('Login Exception: ' . $e->getMessage());
        header('Location: login.php?error=1&message=' . urlencode('Authentication failed: ' . $e->getMessage()));
        exit;
    }
}

// If no POST data, redirect to login
header('Location: login.php');
exit;
