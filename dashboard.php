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

// Check if user is trying to access wrong dashboard
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Get dashboard statistics
try {
    $client = $supabase->createClient();
    
    // Get user's subscription info
    $subscriptionQuery = $client->from('subscriptions')
        ->select('*')
        ->eq('user_id', $_SESSION['user']['id'])
        ->order('created_at', ['ascending' => false])
        ->limit(1)
        ->execute();
    $subscription = $subscriptionQuery->data[0] ?? null;

    // Get user's recent activity
    $logsQuery = $client->from('activity_logs')
        ->select('*')
        ->eq('user_id', $_SESSION['user']['id'])
        ->order('created_at', ['ascending' => false])
        ->limit(5)
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
    <title>Client Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-indigo-700 text-white">
            <div class="p-6">
                <h1 class="text-2xl font-bold">Client Panel</h1>
            </div>
            <nav class="mt-6">
                <a href="dashboard.php" class="flex items-center px-6 py-3 bg-indigo-800">
                    <i class="fas fa-home mr-3"></i>
                    Dashboard
                </a>
                <a href="profile.php" class="flex items-center px-6 py-3 hover:bg-indigo-600">
                    <i class="fas fa-user mr-3"></i>
                    Profile
                </a>
                <a href="subscription.php" class="flex items-center px-6 py-3 hover:bg-indigo-600">
                    <i class="fas fa-credit-card mr-3"></i>
                    Subscription
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
                <h1 class="text-3xl font-bold text-gray-800">Welcome Back!</h1>
                <div class="flex items-center">
                    <span class="mr-4 text-gray-600"><?php echo htmlspecialchars($_SESSION['user']['email']); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user']['email']); ?>" 
                         class="w-10 h-10 rounded-full border-2 border-indigo-200">
                </div>
            </header>

            <!-- Subscription Status -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold mb-2">Subscription Status</h2>
                        <p class="text-gray-600">
                            Current Plan: 
                            <span class="font-semibold"><?php echo ucfirst($subscription['plan'] ?? 'Free'); ?></span>
                        </p>
                        <p class="text-gray-600">
                            Status: 
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo ($subscription['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                <?php echo ucfirst($subscription['status'] ?? 'Inactive'); ?>
                            </span>
                        </p>
                    </div>
                    <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                        Manage Subscription
                    </button>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                <?php if (empty($recentLogs)): ?>
                    <p class="text-gray-500">No recent activity</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-clock text-indigo-600"></i>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <?php echo htmlspecialchars($log['details'] ?? ''); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <?php echo date('M j, Y H:i', strtotime($log['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
