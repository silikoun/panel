<?php
require_once 'vendor/autoload.php';
require_once 'auth/SupabaseAuth.php';
require_once 'includes/functions.php';

// Initialize Supabase client
$supabase = new SupabaseAuth();

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']['is_admin']) {
    header('Location: login.php');
    exit();
}

// Handle subscription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $subscriptionId = $_POST['subscription_id'] ?? '';
    
    if ($action && $subscriptionId) {
        try {
            switch ($action) {
                case 'cancel':
                    $supabase->updateSubscription($subscriptionId, [
                        'status' => 'cancelled',
                        'cancelled_at' => date('Y-m-d H:i:s')
                    ]);
                    $supabase->logActivity(
                        $_SESSION['user']['id'],
                        $_SESSION['user']['email'],
                        'Cancelled subscription',
                        ['subscription_id' => $subscriptionId]
                    );
                    break;
                    
                case 'activate':
                    $supabase->updateSubscription($subscriptionId, [
                        'status' => 'active',
                        'cancelled_at' => null
                    ]);
                    $supabase->logActivity(
                        $_SESSION['user']['id'],
                        $_SESSION['user']['email'],
                        'Activated subscription',
                        ['subscription_id' => $subscriptionId]
                    );
                    break;
            }
            $success = "Subscription successfully {$action}d";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get subscriptions with pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    // Get total subscriptions count
    $totalQuery = $supabase->createClient()
        ->from('subscriptions')
        ->select('*', ['count' => 'exact'])
        ->execute();
    $totalSubscriptions = $totalQuery->count;
    $totalPages = ceil($totalSubscriptions / $perPage);

    // Get subscriptions for current page
    $subscriptionsQuery = $supabase->createClient()
        ->from('subscriptions')
        ->select('*, users:auth.users(email)')
        ->order('created_at', ['ascending' => false])
        ->range($offset, $offset + $perPage - 1)
        ->execute();
    $subscriptions = $subscriptionsQuery->data;

    // Calculate total revenue
    $revenueQuery = $supabase->createClient()
        ->from('subscriptions')
        ->select('plan')
        ->eq('status', 'active')
        ->execute();
    
    $totalRevenue = 0;
    $planPrices = [
        'basic' => 9.99,
        'pro' => 19.99,
        'enterprise' => 49.99
    ];
    
    foreach ($revenueQuery->data as $subscription) {
        $totalRevenue += $planPrices[strtolower($subscription->plan)] ?? 0;
    }

} catch (Exception $e) {
    $error = "Error loading subscriptions: " . $e->getMessage();
    $subscriptions = [];
    $totalPages = 0;
    $totalRevenue = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Management - Admin Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
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
                    <a href="dashboard.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-home w-6"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="users.php" class="flex items-center text-gray-300 hover:bg-gray-800 px-4 py-2 rounded">
                        <i class="fas fa-users w-6"></i>
                        <span>Users</span>
                    </a>
                    <a href="subscriptions.php" class="flex items-center text-gray-300 bg-gray-800 px-4 py-2 rounded">
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
                <h1 class="text-xl font-semibold">Subscription Management</h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Total Revenue: $<?php echo number_format($totalRevenue, 2); ?>/month</span>
                </div>
            </header>

            <!-- Subscription Plans -->
            <main class="p-6">
                <?php if (isset($success)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Subscription Plans -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Subscription Plans</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Basic Plan -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-xl font-semibold mb-2">Basic</h3>
                            <p class="text-gray-600 mb-4">Perfect for getting started</p>
                            <p class="text-3xl font-bold mb-4">$9.99<span class="text-sm text-gray-500">/month</span></p>
                            <button class="w-full bg-blue-500 text-white rounded-lg px-4 py-2">Edit Plan</button>
                        </div>

                        <!-- Pro Plan -->
                        <div class="bg-white rounded-lg shadow-md p-6 border-2 border-blue-500">
                            <h3 class="text-xl font-semibold mb-2">Pro</h3>
                            <p class="text-gray-600 mb-4">Most popular choice</p>
                            <p class="text-3xl font-bold mb-4">$19.99<span class="text-sm text-gray-500">/month</span></p>
                            <button class="w-full bg-blue-500 text-white rounded-lg px-4 py-2">Edit Plan</button>
                        </div>

                        <!-- Enterprise Plan -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-xl font-semibold mb-2">Enterprise</h3>
                            <p class="text-gray-600 mb-4">For large scale operations</p>
                            <p class="text-3xl font-bold mb-4">$49.99<span class="text-sm text-gray-500">/month</span></p>
                            <button class="w-full bg-blue-500 text-white rounded-lg px-4 py-2">Edit Plan</button>
                        </div>
                    </div>
                </div>

                <!-- Subscriptions List -->
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h2 class="text-lg font-semibold">Active Subscriptions</h2>
                            <div class="flex space-x-2">
                                <input type="text" 
                                       placeholder="Search subscriptions..." 
                                       class="px-4 py-2 border rounded-lg"
                                       x-data
                                       @input.debounce="window.location.search = '?search=' + $event.target.value">
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        User
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Plan
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Start Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Next Billing
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <img class="h-10 w-10 rounded-full" 
                                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($subscription->users->email); ?>" 
                                                         alt="">
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($subscription->users->email); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo ucfirst($subscription->plan); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($subscription->status === 'active'): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    <?php echo ucfirst($subscription->status); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($subscription->created_at)); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                            if ($subscription->current_period_end) {
                                                echo date('M j, Y', strtotime($subscription->current_period_end));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="subscription_id" value="<?php echo $subscription->id; ?>">
                                                <?php if ($subscription->status === 'active'): ?>
                                                    <button type="submit" 
                                                            name="action" 
                                                            value="cancel"
                                                            class="text-red-600 hover:text-red-900"
                                                            onclick="return confirm('Are you sure you want to cancel this subscription?')">
                                                        Cancel
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" 
                                                            name="action" 
                                                            value="activate"
                                                            class="text-green-600 hover:text-green-900">
                                                        Activate
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing
                                        <span class="font-medium"><?php echo $offset + 1; ?></span>
                                        to
                                        <span class="font-medium">
                                            <?php echo min($offset + $perPage, $totalSubscriptions); ?>
                                        </span>
                                        of
                                        <span class="font-medium"><?php echo $totalSubscriptions; ?></span>
                                        results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" 
                                         aria-label="Pagination">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <a href="?page=<?php echo $i; ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 <?php echo $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : ''; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
