<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'auth/SupabaseAuth.php';
require_once 'includes/functions.php';

// Initialize Supabase client
$supabase = new SupabaseAuth();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user']['is_admin']) || $_SESSION['user']['is_admin'] !== true) {
    header('Location: dashboard.php');
    exit;
}

// Function to make Supabase REST API requests
function makeSupabaseRequest($endpoint, $method = 'GET', $data = null, $auth = false) {
    $headers = [];

    if ($auth) {
        $headers = [
            'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Content-Type: application/json'
        ];
    } else {
        $headers = [
            'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];
    }
    
    // For Auth API requests, use the Auth URL
    $baseUrl = getenv('SUPABASE_URL');
    if ($auth) {
        // Extract project reference from URL
        preg_match('/https:\/\/([^.]+)\.supabase\.co/', getenv('SUPABASE_URL'), $matches);
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
        'apikey: ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
        'Authorization: Bearer ' . getenv('SUPABASE_SERVICE_ROLE_KEY'),
        'Content-Type: application/json'
    ];
    
    // Extract project reference from URL
    preg_match('/https:\/\/([^.]+)\.supabase\.co/', getenv('SUPABASE_URL'), $matches);
    $projectRef = $matches[1];
    $baseUrl = getenv('SUPABASE_URL') . '/auth/v1';
    
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

// Get dashboard statistics
try {
    // Get total users count and list from auth.users table
    $recentUsers = makeSupabaseRequest('/rest/v1/auth.users?select=id,email,created_at,role&order=created_at.desc&limit=10');
    $totalUsers = count(makeSupabaseRequest('/rest/v1/auth.users?select=id'));

    // Get active subscriptions
    $recentSubscriptions = makeSupabaseRequest('/rest/v1/subscriptions?status=eq.active&select=*&order=created_at.desc&limit=10');
    $activeSubscriptions = count($recentSubscriptions);

    // Get new users today from auth.users table
    $today = date('Y-m-d');
    $allUsers = makeSupabaseRequest('/rest/v1/auth.users?select=id,email,created_at,role');
    $newUsersToday = array_filter($allUsers, function($user) use ($today) {
        return date('Y-m-d', strtotime($user['created_at'])) === $today;
    });
    $newUsersTodayCount = count($newUsersToday);

    // Get API tokens usage
    $recentTokens = makeSupabaseRequest('/rest/v1/tokens?select=*&order=created_at.desc&limit=10');

    // Get usage logs
    $recentUsageLogs = makeSupabaseRequest('/rest/v1/usage_logs?select=*&order=created_at.desc&limit=10');

    // Get recent activity logs
    $recentLogs = makeSupabaseRequest('/rest/v1/activity_logs?select=*&order=created_at.desc&limit=10');

} catch (Exception $e) {
    error_log('Error fetching dashboard data: ' . $e->getMessage());
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-indigo-700 text-white">
            <div class="p-6">
                <h1 class="text-2xl font-bold">Admin Panel</h1>
            </div>
            <nav class="mt-6">
                <a href="admin_dashboard.php" class="flex items-center px-6 py-3 bg-indigo-800">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a href="users.php" class="flex items-center px-6 py-3 hover:bg-indigo-800">
                    <i class="fas fa-users mr-3"></i>
                    Users
                </a>
                <a href="subscriptions.php" class="flex items-center px-6 py-3 hover:bg-indigo-800">
                    <i class="fas fa-credit-card mr-3"></i>
                    Subscriptions
                </a>
                <a href="tokens.php" class="flex items-center px-6 py-3 hover:bg-indigo-800">
                    <i class="fas fa-key mr-3"></i>
                    API Tokens
                </a>
                <a href="logs.php" class="flex items-center px-6 py-3 hover:bg-indigo-800">
                    <i class="fas fa-history mr-3"></i>
                    Logs
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-auto">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <header class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                <div class="flex items-center">
                    <span class="mr-4 text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user']['email']); ?></span>
                    <a href="logout.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Logout</a>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-gray-500 text-sm font-medium">Total Users</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $totalUsers; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-gray-500 text-sm font-medium">Active Subscriptions</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $activeSubscriptions; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-gray-500 text-sm font-medium">New Users Today</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $newUsersTodayCount; ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-gray-500 text-sm font-medium">Active API Tokens</h3>
                    <p class="text-3xl font-bold text-gray-800"><?php echo count($recentTokens); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- Recent Users -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold mb-4">Recent Users</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Admin</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">User</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Subscriptions -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold mb-4">Recent Subscriptions</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentSubscriptions as $sub): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($sub['user_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($sub['plan']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo ucfirst(htmlspecialchars($sub['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($log['user_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- API Usage -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold mb-4">API Usage</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Token</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Endpoint</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentUsageLogs as $usage): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo substr(htmlspecialchars($usage['token_id']), 0, 8); ?>...</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($usage['endpoint']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M j, Y H:i', strtotime($usage['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add any JavaScript for interactivity here
    </script>
</body>
</html>
