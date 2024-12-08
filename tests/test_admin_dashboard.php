<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

function makeSupabaseRequest($endpoint, $method = 'GET', $data = null, $auth = false) {
    $headers = [];

    if ($auth) {
        $headers = [
            'apikey: ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
            'Authorization: Bearer ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
            'Content-Type: application/json'
        ];
    } else {
        $headers = [
            'apikey: ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
            'Authorization: Bearer ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // For Auth API requests, use the Auth URL
    $baseUrl = $_ENV['SUPABASE_URL'];
    if ($auth) {
        // Extract project reference from URL
        preg_match('/https:\/\/([^.]+)\.supabase\.co/', $_ENV['SUPABASE_URL'], $matches);
        $projectRef = $matches[1];
        $baseUrl = "https://supabase.com/dashboard/project/{$projectRef}/auth/users";
    }
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode >= 400) {
        throw new Exception("API request failed with status $statusCode: $response");
    }
    
    return json_decode($response, true);
}

function makeSupabaseAuthRequest($endpoint, $method = 'GET', $data = null) {
    $headers = [
        'apikey: ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
        'Authorization: Bearer ' . $_ENV['SUPABASE_SERVICE_ROLE_KEY'],
        'Content-Type: application/json'
    ];
    
    // Extract project reference from URL
    preg_match('/https:\/\/([^.]+)\.supabase\.co/', $_ENV['SUPABASE_URL'], $matches);
    $projectRef = $matches[1];
    $baseUrl = $_ENV['SUPABASE_URL'] . '/auth/v1';
    
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode >= 400) {
        throw new Exception("API request failed with status $statusCode: $response");
    }
    
    return json_decode($response, true);
}

// Test suite
echo "✓ Initialized test suite with Supabase credentials\n\n";
echo "Starting Admin Dashboard Tests...\n\n";

// Test total users
try {
    echo "Testing Total Users Query...\n";
    $users = makeSupabaseRequest('/rest/v1/auth.users?select=id,email,created_at,role');
    $totalUsers = count($users);
    echo "✓ Successfully fetched total users: $totalUsers\n";
    if (!empty($users)) {
        $sampleUser = $users[0];
        // Mask email for privacy
        if (isset($sampleUser['email'])) {
            $sampleUser['email'] = str_replace(substr($sampleUser['email'], 0, strpos($sampleUser['email'], '@')), '****', $sampleUser['email']);
        }
        echo "Sample user data: " . json_encode($sampleUser) . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    die("✗ Failed to fetch total users: " . $e->getMessage() . "\n");
}

// Test active subscriptions
try {
    echo "Testing Active Subscriptions Query...\n";
    $subscriptions = makeSupabaseRequest('/rest/v1/subscriptions?status=eq.active&select=*');
    $activeSubscriptions = count($subscriptions);
    echo "✓ Successfully fetched active subscriptions: $activeSubscriptions\n\n";
} catch (Exception $e) {
    die("✗ Failed to fetch active subscriptions: " . $e->getMessage() . "\n");
}

// Test new users today
try {
    echo "Testing New Users Today Query...\n";
    $today = date('Y-m-d');
    $allUsers = makeSupabaseRequest('/rest/v1/auth.users?select=id,email,created_at,role');
    $newUsersToday = array_filter($allUsers, function($user) use ($today) {
        return date('Y-m-d', strtotime($user['created_at'])) === $today;
    });
    $newUsersTodayCount = count($newUsersToday);
    echo "✓ Successfully fetched new users today: $newUsersTodayCount\n";
    if (!empty($newUsersToday)) {
        $sampleUser = reset($newUsersToday);
        // Mask email for privacy
        if (isset($sampleUser['email'])) {
            $sampleUser['email'] = str_replace(substr($sampleUser['email'], 0, strpos($sampleUser['email'], '@')), '****', $sampleUser['email']);
        }
        echo "Sample new user data: " . json_encode($sampleUser) . "\n";
    }
    echo "\n";
} catch (Exception $e) {
    die("✗ Failed to fetch new users today: " . $e->getMessage() . "\n");
}

// Test recent activity logs
try {
    echo "Testing Recent Activity Logs Query...\n";
    $logs = makeSupabaseRequest('/rest/v1/activity_logs?select=*&order=created_at.desc&limit=10');
    echo "✓ Successfully fetched recent activity logs: " . count($logs) . "\n\n";
} catch (Exception $e) {
    die("✗ Failed to fetch recent activity logs: " . $e->getMessage() . "\n");
}

// Test API tokens
try {
    echo "Testing API Tokens Query...\n";
    $tokens = makeSupabaseRequest('/rest/v1/tokens?select=*&order=created_at.desc&limit=10');
    echo "✓ Successfully fetched API tokens: " . count($tokens) . "\n\n";
} catch (Exception $e) {
    die("✗ Failed to fetch API tokens: " . $e->getMessage() . "\n");
}

// Test usage logs
try {
    echo "Testing Usage Logs Query...\n";
    $usageLogs = makeSupabaseRequest('/rest/v1/usage_logs?select=*&order=created_at.desc&limit=10');
    echo "✓ Successfully fetched usage logs: " . count($usageLogs) . "\n\n";
} catch (Exception $e) {
    die("✗ Failed to fetch usage logs: " . $e->getMessage() . "\n");
}

echo "All tests completed successfully! ✓\n";
