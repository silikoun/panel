<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../auth/SupabaseAuth.php';
require_once '../includes/functions.php';

// Initialize Supabase client
$supabase = new SupabaseAuth();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['user']['is_admin']) || $_SESSION['user']['is_admin'] !== true) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Get dashboard statistics
try {
    $totalUsers = $supabase->getTotalUsers();
    $activeSubscriptions = $supabase->getActiveSubscriptions();
    $monthlyRevenue = $supabase->getMonthlyRevenue();
    $newUsersToday = $supabase->getNewUsersToday();
    $recentLogs = $supabase->getRecentActivityLogs();
    $userGrowthData = $supabase->getUserGrowthData();
    $revenueData = $supabase->getRevenueData();
} catch (Exception $e) {
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <nav class="bg-gray-900 w-64 px-4 py-6 flex flex-col">
            <div class="flex items-center mb-8">
                <h2 class="text-2xl font-bold text-white">Admin Panel</h2>
            </div>
            <div class="flex flex-col flex-1">
                <a href="index.php" class="text-gray-300 hover:text-white py-2 px-4 rounded bg-gray-800 mb-2">Dashboard</a>
                <a href="users.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800 mb-2">Users</a>
                <a href="subscriptions.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800 mb-2">Subscriptions</a>
            </div>
            <div class="mt-auto">
                <a href="../logout.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800">Logout</a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden">
            <!-- Top Bar -->
            <header class="bg-white shadow-md p-4 flex justify-between items-center">
                <h1 class="text-xl font-semibold">Dashboard Overview</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, <?php echo htmlspecialchars($_SESSION['user']['email']); ?></span>
                </div>
            </header>

            <!-- Main Content -->
            <main class="p-6">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Total Users</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-900"><?php echo formatNumber($totalUsers); ?></span>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Active Subscriptions</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-900"><?php echo formatNumber($activeSubscriptions); ?></span>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm font-medium mb-2">Monthly Revenue</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-900"><?php echo formatCurrency($monthlyRevenue); ?></span>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-gray-500 text-sm font-medium mb-2">New Users Today</h3>
                        <div class="flex items-center">
                            <span class="text-3xl font-bold text-gray-900"><?php echo formatNumber($newUsersToday); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4">User Growth</h3>
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4">Revenue Overview</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>
                    <div class="space-y-4">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?php echo h($log['action']); ?></p>
                                    <p class="text-sm text-gray-500"><?php echo h($log['user_email']); ?></p>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo timeAgo($log['created_at']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Initialize Charts -->
    <script>
        // User Growth Chart
        const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($userGrowthData, 'date')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($userGrowthData, 'count')); ?>,
                    borderColor: '#3b82f6',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($revenueData, 'date')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($revenueData, 'amount')); ?>,
                    backgroundColor: '#3b82f6'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
