<?php
session_start();
require_once '../vendor/autoload.php';
require_once '../auth/SupabaseAuth.php';
require_once '../includes/functions.php';

// Initialize Supabase client
$supabase = new SupabaseAuth();

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['is_admin']) || !$_SESSION['user']['is_admin']) {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Handle subscription actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $subscriptionId = $_POST['subscription_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'cancel':
                $supabase->cancelSubscription($subscriptionId);
                $message = "Subscription cancelled successfully";
                break;
            case 'activate':
                $supabase->activateSubscription($subscriptionId);
                $message = "Subscription activated successfully";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get subscriptions list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $subscriptions = $supabase->getSubscriptions($offset, $perPage);
    $totalSubscriptions = $supabase->getTotalSubscriptions();
    $totalPages = ceil($totalSubscriptions / $perPage);
    $totalRevenue = $supabase->getTotalRevenue();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Management - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <nav class="bg-gray-900 w-64 px-4 py-6 flex flex-col">
            <div class="flex items-center mb-8">
                <h2 class="text-2xl font-bold text-white">Admin Panel</h2>
            </div>
            <div class="flex flex-col flex-1">
                <a href="index.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800 mb-2">Dashboard</a>
                <a href="users.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800 mb-2">Users</a>
                <a href="subscriptions.php" class="text-gray-300 hover:text-white py-2 px-4 rounded bg-gray-800 mb-2">Subscriptions</a>
            </div>
            <div class="mt-auto">
                <a href="../logout.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800">Logout</a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden">
            <header class="bg-white shadow-md p-4">
                <h1 class="text-xl font-semibold">Subscription Management</h1>
                <p class="text-sm text-gray-600 mt-1">Total Revenue: <?php echo formatCurrency($totalRevenue); ?></p>
            </header>

            <main class="p-6">
                <?php if (isset($message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Subscriptions Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Billing</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($subscriptions as $subscription): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-10 w-10 rounded-full" src="<?php echo getGravatar($subscription['user_email']); ?>" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo h($subscription['user_email']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo h($subscription['user_id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo h($subscription['plan']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($subscription['status'] === 'active'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo formatDate($subscription['next_billing_date']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="subscription_id" value="<?php echo h($subscription['id']); ?>">
                                            <?php if ($subscription['status'] === 'active'): ?>
                                                <button type="submit" name="action" value="cancel" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to cancel this subscription?')">Cancel</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="activate" class="text-green-600 hover:text-green-900">Activate</button>
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
                    <div class="mt-4 flex justify-center">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="<?php echo $page === $i ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?> relative inline-flex items-center px-4 py-2 border text-sm font-medium">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
