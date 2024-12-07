<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'auth/SupabaseAuth.php';
require_once 'auth/SupabaseClient.php';
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
    header('Location: login.php?error=unauthorized');
    exit;
}

// Get dashboard statistics
try {
    $client = $supabase->createClient();
    error_log('Client created successfully');

    // Get total users
    $usersQuery = $client->from('users')
        ->select('*', ['count' => 'exact'])
        ->execute();
    $totalUsers = $usersQuery->count ?? 0;
    error_log('Total users: ' . $totalUsers);

    // Get active subscriptions
    $subscriptionsQuery = $client->from('subscriptions')
        ->select('*', ['count' => 'exact'])
        ->eq('status', 'active')
        ->execute();
    $activeSubscriptions = $subscriptionsQuery->count ?? 0;
    error_log('Active subscriptions: ' . $activeSubscriptions);

    // Calculate monthly revenue
    $firstDayOfMonth = date('Y-m-01');
    $revenueQuery = $client->from('subscriptions')
        ->select('plan')
        ->gte('created_at', $firstDayOfMonth)
        ->eq('status', 'active')
        ->execute();
    
    $monthlyRevenue = 0;
    $planPrices = [
        'basic' => 9.99,
        'pro' => 19.99,
        'premium' => 29.99
    ];

    foreach ($revenueQuery->data as $subscription) {
        $monthlyRevenue += $planPrices[strtolower($subscription->plan)] ?? 0;
    }
    error_log('Monthly revenue: ' . $monthlyRevenue);

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
    error_log('Recent logs fetched: ' . count($recentLogs));

    // Get user growth data for chart
    $sixMonthsAgo = date('Y-m-d', strtotime('-6 months'));
    $userGrowthQuery = $client->from('users')
        ->select('created_at')
        ->gte('created_at', $sixMonthsAgo)
        ->execute();
    
    $userGrowthData = [];
    $monthLabels = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('M', strtotime("-$i months"));
        $monthLabels[] = $month;
        $userGrowthData[] = 0;
    }

    foreach ($userGrowthQuery->data as $user) {
        $month = date('M', strtotime($user->created_at));
        $index = array_search($month, $monthLabels);
        if ($index !== false) {
            $userGrowthData[$index]++;
        }
    }

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <nav class="bg-gray-900 w-64 px-4 py-6 flex flex-col">
            <div class="flex items-center mb-8">
                <h2 class="text-2xl font-bold text-white">Admin Panel</h2>
            </div>
            
            <div class="flex-1">
                <nav class="space-y-2">
                    <a href="dashboard.php" class="flex items-center text-gray-300 bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-home w-6"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="users.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-users w-6"></i>
                        <span>Users</span>
                    </a>
                    <a href="subscriptions.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-credit-card w-6"></i>
                        <span>Subscriptions</span>
                    </a>
                    <a href="settings.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-cog w-6"></i>
                        <span>Settings</span>
                    </a>
                </nav>
            </div>
            
            <div class="mt-auto">
                <a href="logout.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                    <i class="fas fa-sign-out-alt w-6"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden">
            <!-- Top Bar -->
            <header class="bg-white shadow-md p-4 flex justify-between items-center">
                <h1 class="text-xl font-semibold">Dashboard Overview</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Welcome, Admin</span>
                    <img src="https://ui-avatars.com/api/?name=Admin" class="w-8 h-8 rounded-full">
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Total Users -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-500 bg-opacity-10">
                                <i class="fas fa-users text-blue-500 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Total Users</h3>
                                <p class="text-2xl font-semibold"><?php echo number_format($totalUsers); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Active Subscriptions -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-500 bg-opacity-10">
                                <i class="fas fa-credit-card text-green-500 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Active Subscriptions</h3>
                                <p class="text-2xl font-semibold"><?php echo number_format($activeSubscriptions); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Revenue -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-500 bg-opacity-10">
                                <i class="fas fa-dollar-sign text-yellow-500 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Monthly Revenue</h3>
                                <p class="text-2xl font-semibold">$<?php echo number_format($monthlyRevenue, 2); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- New Users Today -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-500 bg-opacity-10">
                                <i class="fas fa-user-plus text-purple-500 text-2xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">New Users Today</h3>
                                <p class="text-2xl font-semibold"><?php echo number_format($newUsersToday); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- User Growth Chart -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4">User Growth</h3>
                        <canvas id="userGrowthChart"></canvas>
                    </div>

                    <!-- Revenue Chart -->
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
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-gray-400"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($log->action); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log->user_email); ?> • 
                                        <?php echo date('M j, Y H:i', strtotime($log->created_at)); ?>
                                    </p>
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
        const userCtx = document.getElementById('userGrowthChart').getContext('2d');
        new Chart(userCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthLabels); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode($userGrowthData); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthLabels); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: [1200, 1900, 3000, 5000, 4000, <?php echo $monthlyRevenue; ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    </script>
</body>
</html>
