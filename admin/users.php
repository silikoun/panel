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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $userId = $_POST['user_id'];
        $action = $_POST['action'];
        
        switch ($action) {
            case 'ban':
                $supabase->banUser($userId);
                $message = "User banned successfully";
                break;
            case 'unban':
                $supabase->unbanUser($userId);
                $message = "User unbanned successfully";
                break;
            case 'delete':
                $supabase->deleteUser($userId);
                $message = "User deleted successfully";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users list with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $users = $supabase->getUsers($offset, $perPage);
    $totalUsers = $supabase->getTotalUsers();
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Dashboard</title>
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
                <a href="users.php" class="text-gray-300 hover:text-white py-2 px-4 rounded bg-gray-800 mb-2">Users</a>
                <a href="subscriptions.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800 mb-2">Subscriptions</a>
            </div>
            <div class="mt-auto">
                <a href="../logout.php" class="text-gray-300 hover:text-white py-2 px-4 rounded hover:bg-gray-800">Logout</a>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="flex-1 overflow-x-hidden">
            <header class="bg-white shadow-md p-4">
                <h1 class="text-xl font-semibold">User Management</h1>
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

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subscription</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <img class="h-10 w-10 rounded-full" src="<?php echo getGravatar($user['email']); ?>" alt="">
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo h($user['email']); ?></div>
                                                <div class="text-sm text-gray-500">ID: <?php echo h($user['id']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['banned']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Banned</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['subscription']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo h($user['subscription']['plan']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500">No subscription</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <form method="POST" class="inline-block">
                                            <input type="hidden" name="user_id" value="<?php echo h($user['id']); ?>">
                                            <?php if ($user['banned']): ?>
                                                <button type="submit" name="action" value="unban" class="text-indigo-600 hover:text-indigo-900 mr-2">Unban</button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="ban" class="text-red-600 hover:text-red-900 mr-2">Ban</button>
                                            <?php endif; ?>
                                            <button type="submit" name="action" value="delete" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
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
