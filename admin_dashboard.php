<?php
ini_set('memory_limit', '1G');
require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Dotenv\Dotenv;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment configuration
try {
    error_log('Current directory: ' . __DIR__);

    // Try to load environment variables from env.php
    if (file_exists(__DIR__ . '/env.php')) {
        require_once __DIR__ . '/env.php';
        error_log('Loaded environment from env.php');
    }

    // Try to load from .env file for local development
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();
        error_log('Loaded environment from .env file');
    }

    // Debug: Print all available environment variables
    error_log('Available environment variables after loading:');
    error_log('SUPABASE_URL: ' . (getenv('SUPABASE_URL') ?: 'not set'));
    error_log('SUPABASE_KEY length: ' . strlen(getenv('SUPABASE_KEY') ?: ''));
    error_log('SUPABASE_SERVICE_ROLE_KEY length: ' . strlen(getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));

    // Get environment variables with detailed error messages
    $supabaseUrl = getenv('SUPABASE_URL');
    if (!$supabaseUrl) {
        error_log('SUPABASE_URL is missing. Available environment variables: ' . print_r(getenv(), true));
        throw new Exception('SUPABASE_URL is not set in environment');
    }

    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$supabaseKey) {
        error_log('SUPABASE_SERVICE_ROLE_KEY is missing. Available environment variables: ' . print_r(getenv(), true));
        throw new Exception('SUPABASE_SERVICE_ROLE_KEY is not set in environment');
    }

    error_log('Successfully loaded environment variables');

    // Try to get environment variables directly from environment
    $supabaseUrl = getenv('SUPABASE_URL');
    if (!$supabaseUrl) {
        // Try reading from a temporary file if environment variables are not available
        $envFile = '/tmp/railway.conf';
        if (file_exists($envFile)) {
            $envContents = parse_ini_file($envFile);
            $supabaseUrl = $envContents['SUPABASE_URL'] ?? null;
        }
    }

    if (!$supabaseUrl) {
        throw new Exception('SUPABASE_URL is not set in any environment variable location');
    }

    error_log('Successfully loaded SUPABASE_URL: ' . $supabaseUrl);

    $supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$supabaseKey && file_exists($envFile)) {
        $envContents = parse_ini_file($envFile);
        $supabaseKey = $envContents['SUPABASE_SERVICE_ROLE_KEY'] ?? null;
    }

    if (!$supabaseKey) {
        throw new Exception('SUPABASE_SERVICE_ROLE_KEY is not set in any environment variable location');
    }

    error_log('Successfully loaded SUPABASE_SERVICE_ROLE_KEY (length: ' . strlen($supabaseKey) . ')');

    // Debug logging
    error_log('Environment variable sources:');
    error_log('getenv(): ' . print_r(getenv('SUPABASE_URL'), true));

    if (empty($supabaseUrl)) {
        throw new Exception('SUPABASE_URL is not set in any environment variable location');
    }
    if (empty($supabaseKey)) {
        throw new Exception('SUPABASE_SERVICE_ROLE_KEY is not set in any environment variable location');
    }

    error_log('Successfully loaded Supabase configuration');
    error_log('Supabase URL: ' . $supabaseUrl);
    error_log('Service Role Key length: ' . strlen($supabaseKey));
} catch (Exception $e) {
    error_log('Error loading environment: ' . $e->getMessage());
    die('Error loading environment configuration: ' . $e->getMessage());
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Load environment variables
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseKey) {
    die('Missing environment variables');
}

$client = new GuzzleHttp\Client();

try {
    // Fetch users from Supabase
    $response = $client->request('GET', $supabaseUrl . '/auth/v1/admin/users', [
        'headers' => [
            'Authorization' => 'Bearer ' . $supabaseKey,
            'apikey' => $supabaseKey
        ]
    ]);

    $users = json_decode($response->getBody(), true);
} catch (Exception $e) {
    $error = 'Failed to fetch users: ' . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex">
        <!-- Sidebar -->
        <div class="bg-gray-900 text-white w-64 py-6 flex flex-col">
            <div class="px-6 mb-8">
                <h1 class="text-2xl font-bold">Admin Panel</h1>
            </div>
            <nav class="flex-1">
                <a href="#" class="flex items-center px-6 py-3 bg-gray-800">
                    <i class="fas fa-users mr-3"></i>
                    <span>Users</span>
                </a>
                <a href="#" class="flex items-center px-6 py-3 hover:bg-gray-800 transition-colors">
                    <i class="fas fa-chart-line mr-3"></i>
                    <span>Analytics</span>
                </a>
                <a href="#" class="flex items-center px-6 py-3 hover:bg-gray-800 transition-colors">
                    <i class="fas fa-cog mr-3"></i>
                    <span>Settings</span>
                </a>
            </nav>
            <div class="px-6 py-4">
                <a href="logout.php" class="flex items-center text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="px-6 py-4 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800">Dashboard Overview</h2>
                    <div class="flex items-center">
                        <span class="text-gray-600 mr-4"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></span>
                        <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=Admin&background=0D8ABC&color=fff" alt="Profile">
                    </div>
                </div>
            </header>

            <!-- Statistics Cards -->
            <div class="p-6 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-500 bg-opacity-10">
                                <i class="fas fa-users text-blue-500 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Total Users</h3>
                                <p class="text-2xl font-semibold text-gray-800"><?php echo count($users); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-500 bg-opacity-10">
                                <i class="fas fa-crown text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Premium Users</h3>
                                <p class="text-2xl font-semibold text-gray-800">
                                    <?php 
                                    echo count(array_filter($users, function($user) {
                                        return isset($user['user_metadata']['plan']) && $user['user_metadata']['plan'] !== 'free';
                                    }));
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-500 bg-opacity-10">
                                <i class="fas fa-clock text-purple-500 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-gray-500 text-sm">Active Today</h3>
                                <p class="text-2xl font-semibold text-gray-800">
                                    <?php 
                                    echo count(array_filter($users, function($user) {
                                        return isset($user['last_sign_in_at']) && 
                                               strtotime($user['last_sign_in_at']) > strtotime('-24 hours');
                                    }));
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Messages -->
                <?php if (isset($error)): ?>
                    <div class="rounded-md bg-red-50 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Error</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Users Table -->
                <div class="bg-white rounded-lg shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800">User Management</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <img class="h-8 w-8 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['email']); ?>&background=0D8ABC&color=fff" alt="">
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($user['user_metadata']['plan'] ?? 'free') === 'free' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'; ?>">
                                                <?php echo htmlspecialchars($user['user_metadata']['plan'] ?? 'free'); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo isset($user['banned_until']) ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'; ?>">
                                                <?php echo isset($user['banned_until']) ? 'Banned' : 'Active'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php 
                                            $lastLogin = isset($user['last_sign_in_at']) ? new DateTime($user['last_sign_in_at']) : null;
                                            echo $lastLogin ? $lastLogin->format('Y-m-d H:i') : 'Never';
                                            ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="viewUser('<?php echo $user['id']; ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button onclick="editUser('<?php echo $user['id']; ?>')" class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- View User Modal -->
    <div id="viewUserModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                User Details
                            </h3>
                            <div class="mt-4">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p id="viewUserEmail" class="mt-1 p-2 w-full text-gray-900"></p>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Plan</label>
                                    <p id="viewUserPlan" class="mt-1 p-2 w-full text-gray-900"></p>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <p id="viewUserStatus" class="mt-1 p-2 w-full text-gray-900"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm" onclick="closeViewModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Edit User
                            </h3>
                            <div class="mt-4">
                                <form id="editUserForm">
                                    <input type="hidden" id="editUserId" name="userId">
                                    <div class="mb-4">
                                        <label for="editUserEmail" class="block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" name="email" id="editUserEmail" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                    <div class="mb-4">
                                        <label for="editUserPlan" class="block text-sm font-medium text-gray-700">Plan</label>
                                        <select name="plan" id="editUserPlan" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option value="free">Free</option>
                                            <option value="pro">Pro</option>
                                            <option value="enterprise">Enterprise</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label for="editUserStatus" class="block text-sm font-medium text-gray-700">Status</label>
                                        <select name="status" id="editUserStatus" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option value="ACTIVE">Active</option>
                                            <option value="BANNED">Banned</option>
                                            <option value="PENDING">Pending</option>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label for="editUserPassword" class="block text-sm font-medium text-gray-700">New Password (leave empty to keep current)</label>
                                        <input type="password" name="password" id="editUserPassword" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" onclick="deleteUser()">
                        Delete User
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="saveUserChanges()">
                        Save Changes
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" onclick="closeEditModal()">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        async function viewUser(userId) {
            try {
                const response = await fetch(`update_user.php?action=get&userId=${userId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to fetch user data');
                }

                const userData = await response.json();
                
                // Populate modal with user data
                document.getElementById('viewUserEmail').textContent = userData.email || '';
                document.getElementById('viewUserPlan').textContent = userData.user_metadata?.plan || 'free';
                document.getElementById('viewUserStatus').textContent = userData.status || 'ACTIVE';

                // Show modal
                document.getElementById('viewUserModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
            }
        }

        async function editUser(userId) {
            try {
                const response = await fetch(`update_user.php?action=get&userId=${userId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to fetch user data');
                }

                const userData = await response.json();
                
                // Populate modal with user data
                document.getElementById('editUserId').value = userId;
                document.getElementById('editUserEmail').value = userData.email || '';
                document.getElementById('editUserPlan').value = userData.user_metadata?.plan || 'free';
                document.getElementById('editUserStatus').value = userData.status || 'ACTIVE';
                document.getElementById('editUserPassword').value = '';

                // Show modal
                document.getElementById('editUserModal').classList.remove('hidden');
            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
            }
        }

        function closeViewModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }

        function closeEditModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        async function saveUserChanges() {
            const userId = document.getElementById('editUserId').value;
            const email = document.getElementById('editUserEmail').value;
            const plan = document.getElementById('editUserPlan').value;
            const status = document.getElementById('editUserStatus').value;
            const password = document.getElementById('editUserPassword').value;

            try {
                const response = await fetch('update_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'update',
                        userId: userId,
                        email: email,
                        plan: plan,
                        status: status,
                        password: password || undefined
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to update user');
                }

                // Close modal and reload page
                closeEditModal();
                location.reload();
            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
            }
        }

        async function deleteUser() {
            const userId = document.getElementById('editUserId').value;
            
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('update_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        userId: userId
                    })
                });

                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.error || 'Failed to delete user');
                }

                // Close modal and reload page
                closeEditModal();
                location.reload();
            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
            }
        }
    </script>
</body>
</html>
