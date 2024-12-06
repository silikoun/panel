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
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['is_admin']) || $_SESSION['user']['is_admin'] !== true) {
    error_log('Admin access denied. Session data: ' . json_encode($_SESSION));
    header('Location: login.php');
    exit;
}

// Initialize Supabase client
try {
    // Get project reference from URL
    $urlParts = parse_url($supabaseUrl);
    $projectRef = explode('.', $urlParts['host'])[0];
    error_log("Project reference: " . $projectRef);

    // Create client for auth admin API
    $client = new Client([
        'verify' => false,
        'http_errors' => false
    ]);

    error_log("Making request to Auth Admin API");
    error_log("Using service role key: " . substr($supabaseKey, 0, 10) . '...');
    
    // Use the Auth API endpoint with full URL
    $authUrl = "https://{$projectRef}.supabase.co/auth/v1/admin/users";
    error_log("Full auth URL: " . $authUrl);
    
    $response = $client->get($authUrl, [
        'headers' => [
            'apikey' => $supabaseKey,
            'Authorization' => 'Bearer ' . $supabaseKey,
            'Content-Type' => 'application/json'
        ]
    ]);

    $statusCode = $response->getStatusCode();
    $responseBody = (string) $response->getBody();
    error_log("Response status: " . $statusCode);
    error_log("Response body: " . $responseBody);

    if ($statusCode === 401) {
        error_log("Authentication failed. Response: " . $responseBody);
        throw new Exception("Authentication failed. Please check your service role key.");
    }

    if ($statusCode !== 200) {
        error_log("Error response: " . $responseBody);
        throw new Exception("Failed to fetch users. Status code: " . $statusCode);
    }

    $responseData = json_decode($responseBody, true);
    if (!is_array($responseData) || !isset($responseData['users'])) {
        error_log("Invalid response format: " . gettype($responseData));
        throw new Exception('Invalid response format: expected array with users key');
    }

    error_log("Number of users fetched: " . count($responseData['users']));
    
    // Filter out any non-array items and initialize default values
    $users = [];
    foreach ($responseData['users'] as $user) {
        if (!is_array($user)) {
            error_log("Invalid user data format: " . gettype($user));
            continue;
        }

        error_log("Processing user: " . json_encode($user));

        // Ensure app_metadata and user_metadata are arrays
        if (!isset($user['app_metadata']) || !is_array($user['app_metadata'])) {
            $user['app_metadata'] = [];
        }
        if (!isset($user['user_metadata']) || !is_array($user['user_metadata'])) {
            $user['user_metadata'] = [];
        }

        // Set default values
        $user['user_metadata']['plan'] = $user['user_metadata']['plan'] ?? 'free';
        
        // Get user status with null coalescing for banned and confirmed flags
        $isBanned = ($user['banned'] ?? false) === true;
        $isConfirmed = ($user['confirmed'] ?? false) === true;
        $user['status'] = $isBanned ? 'BANNED' : ($isConfirmed ? 'ACTIVE' : 'PENDING');
        
        $users[] = $user;
        
        error_log("Added user with email: " . ($user['email'] ?? 'No Email') . " and status: " . $user['status']);
    }
    
    error_log("Successfully processed " . count($users) . " users");
    
    // Fetch tokens from local storage
    $tokensFile = __DIR__ . '/tokens.json';
    $tokens = [];
    
    if (file_exists($tokensFile)) {
        $tokensData = json_decode(file_get_contents($tokensFile), true);
        if (is_array($tokensData)) {
            $tokens = $tokensData;
            error_log("Loaded " . count($tokens) . " tokens from local storage");
        } else {
            error_log("Invalid tokens data format in file");
        }
    }

    // Calculate statistics
    $stats = [
        'total_users' => count($users),
        'active_tokens' => count(array_filter($tokens, function($t) {
            return is_array($t) && isset($t['status']) && $t['status'] === 'active';
        })),
        'active_subscriptions' => count(array_filter($users, function($u) {
            return is_array($u) && 
                   isset($u['user_metadata']['plan']) && 
                   $u['user_metadata']['plan'] !== 'free';
        })),
        'new_users_today' => count(array_filter($users, function($u) {
            return is_array($u) && 
                   isset($u['created_at']) && 
                   date('Y-m-d', strtotime($u['created_at'])) === date('Y-m-d');
        }))
    ];
    
    error_log("Statistics calculated: " . json_encode($stats));

} catch (Exception $e) {
    error_log("Error in admin dashboard: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $error = "Error fetching data: " . $e->getMessage();
    $users = [];
    $tokens = [];
    $stats = [
        'total_users' => 0,
        'active_tokens' => 0,
        'active_subscriptions' => 0,
        'new_users_today' => 0
    ];
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
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

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
                            <?php
                            // Get tokens for this user
                            $userTokens = array_filter($tokens, function($t) use ($user) {
                                return is_array($t) && 
                                       isset($t['email']) && 
                                       isset($user['email']) && 
                                       $t['email'] === $user['email'];
                            });
                             
                            $activeTokens = array_filter($userTokens, function($t) {
                                return is_array($t) && 
                                       isset($t['status']) && 
                                       $t['status'] === 'active';
                            });
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['email'] ?? 'No Email'); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                Joined: <?php 
                                                    $joinDate = isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : 'Unknown';
                                                    echo htmlspecialchars($joinDate);
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php 
                                            $plan = isset($user['user_metadata']['plan']) ? ucfirst($user['user_metadata']['plan']) : 'Free';
                                            echo htmlspecialchars($plan);
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        Active: <?php echo count($activeTokens); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">Total: <?php echo count($userTokens); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                        $statusClass = match($user['status']) {
                                            'ACTIVE' => 'bg-green-100 text-green-800',
                                            'BANNED' => 'bg-red-100 text-red-800',
                                            'PENDING' => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        echo $statusClass;
                                    ?>">
                                        <?php echo htmlspecialchars($user['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900" 
                                       onclick="viewUser('<?php echo htmlspecialchars($user['id'] ?? ''); ?>')">
                                        View
                                    </a>
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900" 
                                       onclick="editUser('<?php echo htmlspecialchars($user['id'] ?? ''); ?>')">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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
        function showViewModal() {
            document.getElementById('viewUserModal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }

        async function viewUser(userId) {
            try {
                // Get user data
                const response = await fetch(`update_user.php?action=get&userId=${userId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch user data');
                }

                const userData = await response.json();
                
                // Populate modal with user data
                document.getElementById('viewUserEmail').textContent = userData.email;
                document.getElementById('viewUserPlan').textContent = userData.user_metadata?.plan || 'free';
                document.getElementById('viewUserStatus').textContent = userData.status;

                // Show modal
                showViewModal();
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading user data');
            }
        }

        function showEditModal() {
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editUserModal').classList.add('hidden');
        }

        async function editUser(userId) {
            try {
                // Get user data
                const response = await fetch(`update_user.php?action=get&userId=${userId}`, {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch user data');
                }

                const userData = await response.json();
                
                // Populate modal with user data
                document.getElementById('editUserId').value = userId;
                document.getElementById('editUserEmail').value = userData.email;
                document.getElementById('editUserPlan').value = userData.user_metadata?.plan || 'free';
                document.getElementById('editUserStatus').value = userData.status;
                document.getElementById('editUserPassword').value = '';

                // Show modal
                showEditModal();
            } catch (error) {
                console.error('Error:', error);
                alert('Error loading user data');
            }
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
                        'Content-Type': 'application/json'
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
                    throw new Error('Failed to update user');
                }

                // Close modal and reload page
                closeEditModal();
                location.reload();
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating user');
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
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        userId: userId
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to delete user');
                }

                // Close modal and reload page
                closeEditModal();
                location.reload();
            } catch (error) {
                console.error('Error:', error);
                alert('Error deleting user');
            }
        }
    </script>
</body>
</html>
