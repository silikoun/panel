<?php
ini_set('memory_limit', '1G');
require 'vendor/autoload.php';
require_once 'classes/Database.php';
session_start();

// Check if user is admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['user']['role']) || $_SESSION['user']['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Initialize Database
$database = new Database();
$db = $database->connect();

// Get all users with their subscription details
$query = "
    SELECT 
        u.id,
        u.email,
        u.created_at,
        u.last_login,
        s.subscription_type,
        s.status as subscription_status,
        s.start_date,
        s.end_date,
        COUNT(t.id) as total_tokens,
        SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tokens
    FROM users u
    LEFT JOIN subscriptions s ON u.id = s.user_id
    LEFT JOIN tokens t ON u.id = t.user_id
    GROUP BY u.id, u.email, u.created_at, u.last_login, s.subscription_type, s.subscription_status, s.start_date, s.end_date
    ORDER BY u.created_at DESC
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching user data: " . $e->getMessage();
}

// Get system statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM tokens WHERE status = 'active') as active_tokens,
        (SELECT COUNT(*) FROM subscriptions WHERE status = 'active') as active_subscriptions,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as new_users_today
";

try {
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching statistics: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WooCommerce Product Scraper</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="text-xl font-bold text-gray-800">WooScraper Admin</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="logout.php" class="text-gray-600 hover:text-gray-900">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Users</p>
                        <p class="text-2xl font-semibold"><?php echo $stats['total_users']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <i class="fas fa-key fa-2x"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Active Tokens</p>
                        <p class="text-2xl font-semibold"><?php echo $stats['active_tokens']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                        <i class="fas fa-crown fa-2x"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Active Subscriptions</p>
                        <p class="text-2xl font-semibold"><?php echo $stats['active_subscriptions']; ?></p>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">New Users Today</p>
                        <p class="text-2xl font-semibold"><?php echo $stats['new_users_today']; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Registered Users
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscription</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tokens</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <div class="text-sm text-gray-500">Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo ucfirst($user['subscription_type'] ?? 'None'); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php if ($user['end_date']): ?>
                                        Expires: <?php echo date('M d, Y', strtotime($user['end_date'])); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">Active: <?php echo $user['active_tokens']; ?></div>
                                <div class="text-sm text-gray-500">Total: <?php echo $user['total_tokens']; ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $user['subscription_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo ucfirst($user['subscription_status'] ?? 'Inactive'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <button class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <button class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript for interactivity here
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any interactive features
        });
    </script>
</body>
</html>
