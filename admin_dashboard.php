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
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get dashboard statistics
try {
    $client = $supabase->createClient();
    
    // Get total users count
    $usersQuery = $client->from('users')->select('*', ['count' => 'exact'])->execute();
    $totalUsers = $usersQuery->count ?? 0;

    // Get active subscriptions
    $subscriptionsQuery = $client->from('subscriptions')
        ->select('*', ['count' => 'exact'])
        ->eq('status', 'active')
        ->execute();
    $activeSubscriptions = $subscriptionsQuery->count ?? 0;

    // Get new users today
    $today = date('Y-m-d');
    $newUsersQuery = $client->from('users')
        ->select('*', ['count' => 'exact'])
        ->gte('created_at', $today)
        ->execute();
    $newUsersToday = $newUsersQuery->count ?? 0;

    // Get recent activity logs
    $logsQuery = $client->from('activity_logs')
        ->select('*')
        ->order('created_at', ['ascending' => false])
        ->limit(10)
        ->execute();
    $recentLogs = $logsQuery->data ?? [];

} catch (Exception $e) {
    error_log('Error fetching dashboard data: ' . $e->getMessage());
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
                <a href="users.php" class="flex items-center px-6 py-3 hover:bg-indigo-600">
                    <i class="fas fa-users mr-3"></i>
                    Users
                </a>
                <a href="subscriptions.php" class="flex items-center px-6 py-3 hover:bg-indigo-600">
                    <i class="fas fa-credit-card mr-3"></i>
                    Subscriptions
                </a>
                <a href="settings.php" class="flex items-center px-6 py-3 hover:bg-indigo-600">
                    <i class="fas fa-cog mr-3"></i>
                    Settings
                </a>
                <a href="logout.php" class="flex items-center px-6 py-3 hover:bg-indigo-600 mt-auto">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <header class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Dashboard Overview</h1>
                <div class="flex items-center">
                    <span class="mr-4 text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user']['email']); ?></span>
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-bell mr-2"></i>
                        Notifications
                    </button>
                </div>
            </header>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-indigo-100 rounded-full">
                            <i class="fas fa-users text-indigo-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Total Users</h3>
                            <p class="text-2xl font-semibold"><?php echo number_format($totalUsers); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-chart-line text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">Active Subscriptions</h3>
                            <p class="text-2xl font-semibold"><?php echo number_format($activeSubscriptions); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-gray-500 text-sm">New Users Today</h3>
                            <p class="text-2xl font-semibold"><?php echo number_format($newUsersToday); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($log['user_email']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Add any JavaScript for interactivity here
    </script>
</body>
</html>
