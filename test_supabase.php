<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use Dotenv\Dotenv;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
    echo "✅ Successfully loaded .env file\n";
} catch (Exception $e) {
    die("❌ Error loading .env file: " . $e->getMessage() . "\n");
}

// Get environment variables
$supabaseUrl = $_ENV['SUPABASE_URL'] ?? null;
$supabaseServiceRoleKey = $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? null;

// Check if variables are set
echo "\nChecking environment variables:\n";
echo "SUPABASE_URL: " . ($supabaseUrl ? "✅ Set" : "❌ Missing") . "\n";
echo "SUPABASE_SERVICE_ROLE_KEY: " . ($supabaseServiceRoleKey ? "✅ Set" : "❌ Missing") . "\n";

if (!$supabaseUrl || !$supabaseServiceRoleKey) {
    die("\n❌ Missing required environment variables\n");
}

$client = new Client([
    'verify' => false,
    'http_errors' => false
]);

echo "\n🔍 Fetching all users:\n";

try {
    // Fetch users from auth.users
    $authResponse = $client->request('GET', $supabaseUrl . '/auth/v1/admin/users', [
        'headers' => [
            'Authorization' => 'Bearer ' . $supabaseServiceRoleKey,
            'apikey' => $supabaseServiceRoleKey
        ]
    ]);
    
    $statusCode = $authResponse->getStatusCode();
    echo "Auth API Status Code: " . $statusCode . "\n";
    
    if ($statusCode !== 200) {
        throw new Exception("Failed to fetch auth users. Status code: " . $statusCode);
    }
    
    $authUsers = json_decode($authResponse->getBody(), true);
    
    if (!is_array($authUsers)) {
        throw new Exception("Invalid response format from auth API");
    }
    
    // Get the users array from the 'users' key if present
    $users = isset($authUsers['users']) ? $authUsers['users'] : $authUsers;
    
    echo "\nFound " . count($users) . " users\n";
    echo "\nUser Details:\n";
    echo str_repeat("-", 100) . "\n";
    
    $userNumber = 1;
    foreach ($users as $user) {
        if (!is_array($user)) continue;
        
        echo "\n👤 User " . $userNumber . ":\n";
        echo "ID: " . ($user['id'] ?? 'N/A') . "\n";
        echo "Email: " . ($user['email'] ?? 'N/A') . "\n";
        echo "Status: " . (isset($user['email_confirmed_at']) ? 'Active' : 'Pending') . "\n";
        echo "Created: " . (isset($user['created_at']) ? date('Y-m-d H:i:s', strtotime($user['created_at'])) : 'N/A') . "\n";
        echo "Last Login: " . (isset($user['last_sign_in_at']) ? date('Y-m-d H:i:s', strtotime($user['last_sign_in_at'])) : 'Never') . "\n";
        
        // Display user metadata
        if (isset($user['user_metadata']) && is_array($user['user_metadata'])) {
            echo "Metadata:\n";
            if (isset($user['user_metadata']['plan'])) echo "  - Plan: " . $user['user_metadata']['plan'] . "\n";
            if (isset($user['user_metadata']['is_admin'])) echo "  - Admin: " . ($user['user_metadata']['is_admin'] ? 'Yes' : 'No') . "\n";
            if (isset($user['user_metadata']['api_token'])) {
                $token = $user['user_metadata']['api_token'];
                echo "  - API Token: " . substr($token, 0, 8) . "..." . substr($token, -8) . "\n";
            }
        }
        
        // Display app metadata
        if (isset($user['app_metadata']) && is_array($user['app_metadata'])) {
            echo "App Metadata:\n";
            if (isset($user['app_metadata']['provider'])) echo "  - Provider: " . $user['app_metadata']['provider'] . "\n";
            if (isset($user['app_metadata']['providers'])) {
                echo "  - Providers: " . implode(', ', $user['app_metadata']['providers']) . "\n";
            }
        }
        
        echo str_repeat("-", 50) . "\n";
        $userNumber++;
    }
    
    // Fetch users from public.users
    echo "\n🔍 Checking public.users table:\n";
    $publicResponse = $client->request('GET', $supabaseUrl . '/rest/v1/users', [
        'headers' => [
            'Authorization' => 'Bearer ' . $supabaseServiceRoleKey,
            'apikey' => $supabaseServiceRoleKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation'
        ]
    ]);
    
    $statusCode = $publicResponse->getStatusCode();
    echo "Public API Status Code: " . $statusCode . "\n";
    
    if ($statusCode === 200) {
        $publicUsers = json_decode($publicResponse->getBody(), true);
        if (is_array($publicUsers)) {
            echo "Found " . count($publicUsers) . " users in public.users table\n";
            
            foreach ($publicUsers as $user) {
                echo "\nPublic User Details:\n";
                echo "ID: " . ($user['id'] ?? 'N/A') . "\n";
                echo "Email: " . ($user['email'] ?? 'N/A') . "\n";
                if (isset($user['api_token'])) {
                    $token = $user['api_token'];
                    echo "API Token: " . substr($token, 0, 8) . "..." . substr($token, -8) . "\n";
                }
                if (isset($user['api_token_expires'])) {
                    echo "Token Expires: " . $user['api_token_expires'] . "\n";
                }
            }
        }
    }
    
    // Summary
    echo "\n📊 Summary:\n";
    $activeUsers = count(array_filter($users, function($user) {
        return isset($user['email_confirmed_at']);
    }));
    $pendingUsers = count($users) - $activeUsers;
    
    echo "Total Users: " . count($users) . "\n";
    echo "Active Users: " . $activeUsers . "\n";
    echo "Pending Users: " . $pendingUsers . "\n";
    
    $premiumUsers = count(array_filter($users, function($user) {
        return isset($user['user_metadata']['plan']) && 
               strtolower($user['user_metadata']['plan']) !== 'free';
    }));
    echo "Premium Users: " . $premiumUsers . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
