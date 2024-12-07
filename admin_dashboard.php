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
    error_log('Available environment variables after loading:' . print_r(getenv(), true));
    error_log('SUPABASE_URL: ' . (getenv('SUPABASE_URL') ?: 'not set'));
    error_log('SUPABASE_KEY length: ' . strlen(getenv('SUPABASE_KEY') ?: ''));
    error_log('SUPABASE_SERVICE_ROLE_KEY length: ' . strlen(getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''));
    
    // Get environment variables with detailed error messages
    $supabaseUrl = getenv('SUPABASE_URL');
    if (!$supabaseUrl) {
        error_log('SUPABASE_URL is missing. Available environment variables: ' . print_r(getenv(), true));
        throw new Exception('SUPABASE_URL is not set in environment');
    }
    
    $supabaseServiceRoleKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$supabaseServiceRoleKey) {
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
    
    $supabaseServiceRoleKey = getenv('SUPABASE_SERVICE_ROLE_KEY');
    if (!$supabaseServiceRoleKey && file_exists($envFile)) {
        $envContents = parse_ini_file($envFile);
        $supabaseServiceRoleKey = $envContents['SUPABASE_SERVICE_ROLE_KEY'] ?? null;
    }
        
    if (!$supabaseServiceRoleKey) {
        throw new Exception('SUPABASE_SERVICE_ROLE_KEY is not set in any environment variable location');
    }
    
    error_log('Successfully loaded SUPABASE_SERVICE_ROLE_KEY (length: ' . strlen($supabaseServiceRoleKey) . ')');

    // Debug logging
    error_log('Environment variable sources:');
    error_log('getenv(): ' . print_r(getenv('SUPABASE_URL'), true));
    
    if (empty($supabaseUrl)) {
        throw new Exception('SUPABASE_URL is not set in any environment variable location');
    }
    if (empty($supabaseServiceRoleKey)) {
        throw new Exception('SUPABASE_SERVICE_ROLE_KEY is not set in any environment variable location');
    }

    error_log('Successfully loaded Supabase configuration');
    error_log('Supabase URL: ' . $supabaseUrl);
    error_log('Service Role Key length: ' . strlen($supabaseServiceRoleKey));
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
$supabaseServiceRoleKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

if (!$supabaseUrl || !$supabaseServiceRoleKey) {
    die('Missing environment variables');
}

$client = new GuzzleHttp\Client();

try {
    // Fetch users from Supabase with better error handling
    $headers = [
        'Authorization' => 'Bearer ' . $supabaseServiceRoleKey,
        'apikey' => $supabaseServiceRoleKey,
        'Content-Type' => 'application/json',
        'Prefer' => 'return=representation'
    ];

    // First, get users from auth.users
    $authResponse = $client->request('GET', $supabaseUrl . '/auth/v1/admin/users', [
        'headers' => $headers
    ]);

    $authUsers = json_decode($authResponse->getBody(), true);
    
    // Ensure authUsers is an array
    if (!is_array($authUsers)) {
        error_log('Auth users response is not an array: ' . print_r($authUsers, true));
        $authUsers = [];
    }

    // Create a map of processed auth users
    $users = [];
    foreach ($authUsers as $user) {
        if (!is_array($user)) {
            error_log('Invalid user data: ' . print_r($user, true));
            continue;
        }
        
        // Get user metadata safely
        $metadata = [];
        if (isset($user['user_metadata']) && is_array($user['user_metadata'])) {
            $metadata = $user['user_metadata'];
            error_log('User metadata for ' . ($user['email'] ?? 'unknown') . ': ' . print_r($metadata, true));
        }
        
        // Initialize user with default values
        $processedUser = [
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'status' => isset($user['email_confirmed_at']) ? 'Active' : 'Pending',
            'plan' => $metadata['plan'] ?? 'Free',
            'joined_date' => isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : 'Unknown',
            'last_login' => isset($user['last_sign_in_at']) ? date('Y-m-d H:i:s', strtotime($user['last_sign_in_at'])) : 'Never',
            'is_admin' => isset($metadata['is_admin']) && $metadata['is_admin'] === true,
            'api_token' => $metadata['api_token'] ?? null,
            'confirmed_at' => $user['email_confirmed_at'] ?? null,
            'banned_until' => $user['banned_until'] ?? null,
            'app_metadata' => $user['app_metadata'] ?? [],
            'user_metadata' => $metadata
        ];
        
        error_log('Processed user: ' . print_r($processedUser, true));
        
        // Handle special statuses
        if ($processedUser['banned_until'] !== null) {
            $processedUser['status'] = 'Banned';
            error_log('User ' . $processedUser['email'] . ' is banned until ' . $processedUser['banned_until']);
        }
        
        if ($processedUser['id']) {
            $users[$processedUser['id']] = $processedUser;
        }
    }

    try {
        // Then, get additional user data from public.users table
        $publicUsersResponse = $client->request('GET', $supabaseUrl . '/rest/v1/users', [
            'headers' => $headers
        ]);

        $publicUsers = json_decode($publicUsersResponse->getBody(), true);
        
        // Ensure publicUsers is an array
        if (!is_array($publicUsers)) {
            error_log('Public users response is not an array: ' . print_r($publicUsers, true));
            $publicUsers = [];
        }

        // Merge additional data from public users
        foreach ($publicUsers as $publicUser) {
            if (!isset($publicUser['id']) || !isset($users[$publicUser['id']])) continue;
            
            // Update API token information if available
            if (isset($publicUser['api_token'])) {
                $users[$publicUser['id']]['api_token'] = $publicUser['api_token'];
            }
            
            // Add any additional fields from public.users as needed
            if (isset($publicUser['api_token_expires'])) {
                $users[$publicUser['id']]['api_token_expires'] = $publicUser['api_token_expires'];
            }
        }

    } catch (Exception $e) {
        error_log('Error fetching public users: ' . $e->getMessage());
        error_log('Error trace: ' . $e->getTraceAsString());
    }

    // Convert users map back to array for display
    $users = array_values($users);
    
    // Calculate statistics with debug logging
    $totalUsers = count($users);
    error_log("Total Users: " . $totalUsers);
    
    $activeUsers = count(array_filter($users, function($user) {
        return $user['status'] === 'Active';
    }));
    error_log("Active Users: " . $activeUsers);
    
    $pendingUsers = count(array_filter($users, function($user) {
        return $user['status'] === 'Pending';
    }));
    error_log("Pending Users: " . $pendingUsers);
    
    $premiumUsers = count(array_filter($users, function($user) {
        $isPremium = strtolower($user['plan']) === 'premium';
        error_log("User {$user['email']} plan: {$user['plan']} isPremium: " . ($isPremium ? 'true' : 'false'));
        return $isPremium;
    }));
    error_log("Premium Users: " . $premiumUsers);

} catch (Exception $e) {
    error_log('Error fetching users: ' . $e->getMessage());
    error_log('Error trace: ' . $e->getTraceAsString());
    $error = 'Failed to fetch users: ' . $e->getMessage();
    $users = [];
    $totalUsers = $activeUsers = $pendingUsers = $premiumUsers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-full">
        <nav class="bg-indigo-600">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt text-white text-2xl"></i>
                            <span class="text-white text-xl font-bold ml-2">Admin Dashboard</span>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-white"><?php echo htmlspecialchars($_SESSION['user']['email']); ?></span>
                        <a href="logout.php" class="text-white hover:bg-indigo-700 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-out-alt mr-1"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white shadow">
            <div class="mx-auto max-w-7xl py-6 px-4 sm:px-6 lg:px-8">
                <div class="md:flex md:items-center md:justify-between">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-3xl font-bold text-gray-900">Dashboard Overview</h1>
                    </div>
                    <div class="mt-4 flex md:mt-0 md:ml-4">
                        <button type="button" onclick="exportUsers()" class="ml-3 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-download mr-2"></i> Export Users
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats Section -->
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-users text-indigo-600 text-3xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            <?php 
                                            echo number_format($totalUsers); 
                                            error_log("Frontend Total Users: " . $totalUsers);
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-user-check text-green-600 text-3xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            <?php 
                                            echo number_format($activeUsers); 
                                            error_log("Frontend Active Users: " . $activeUsers);
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-clock text-yellow-600 text-3xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Pending Users</dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            <?php 
                                            echo number_format($pendingUsers); 
                                            error_log("Frontend Pending Users: " . $pendingUsers);
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="p-5">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-crown text-purple-600 text-3xl"></i>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Premium Users</dt>
                                        <dd class="text-lg font-semibold text-gray-900">
                                            <?php 
                                            echo number_format($premiumUsers); 
                                            error_log("Frontend Premium Users: " . $premiumUsers);
                                            ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="mt-8 bg-white shadow rounded-lg p-6">
                    <div class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search Users</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" name="search" id="search" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search by email or name" onkeyup="filterUsers()">
                            </div>
                        </div>
                        <div class="w-full md:w-48">
                            <label for="statusFilter" class="block text-sm font-medium text-gray-700">Status</label>
                            <select id="statusFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" onchange="filterUsers()">
                                <option value="all">All Status</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div class="w-full md:w-48">
                            <label for="planFilter" class="block text-sm font-medium text-gray-700">Plan</label>
                            <select id="planFilter" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md" onchange="filterUsers()">
                                <option value="all">All Plans</option>
                                <option value="free">Free</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="mt-8 bg-white shadow rounded-lg">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">API Token</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="usersTableBody">
                                <?php foreach ($users as $user): ?>
                                <tr class="user-row">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <span class="text-gray-500 font-medium"><?php echo strtoupper(substr($user['email'] ?? 'U', 0, 1)); ?></span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['email'] ?? 'No Email'); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['id'] ?? 'No ID'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status = $user['status'] ?? 'Unknown';
                                        $statusClass = match($status) {
                                            'Active' => 'bg-green-100 text-green-800',
                                            'Pending' => 'bg-yellow-100 text-yellow-800',
                                            'Banned' => 'bg-red-100 text-red-800',
                                            default => 'bg-gray-100 text-gray-800'
                                        };
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($status); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $plan = strtolower($user['plan'] ?? 'free');
                                        $planClass = $plan === 'premium' ? 'text-purple-600' : 'text-gray-600';
                                        ?>
                                        <span class="text-sm <?php echo $planClass; ?>"><?php echo ucfirst(htmlspecialchars($plan)); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($user['joined_date'] ?? 'Unknown'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php if (isset($user['api_token'])): ?>
                                            <span class="font-mono"><?php echo substr($user['api_token'], 0, 8) . '...' . substr($user['api_token'], -8); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No token</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-3 justify-end">
                                            <button class="text-indigo-600 hover:text-indigo-900">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
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
                                            <option value="premium">Premium</option>
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

        function filterUsers() {
            const search = document.getElementById('search').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const planFilter = document.getElementById('planFilter').value;
            const rows = document.getElementsByClassName('user-row');

            Array.from(rows).forEach(row => {
                const email = row.querySelector('td:first-child').textContent.toLowerCase();
                const status = row.querySelector('td:nth-child(2)').textContent.trim().toLowerCase();
                const plan = row.querySelector('td:nth-child(3)').textContent.trim().toLowerCase();

                const matchesSearch = email.includes(search);
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                const matchesPlan = planFilter === 'all' || plan === planFilter;

                row.style.display = matchesSearch && matchesStatus && matchesPlan ? '' : 'none';
            });
        }

        async function exportUsers() {
            const rows = Array.from(document.getElementsByClassName('user-row'))
                .filter(row => row.style.display !== 'none');
            
            const csvContent = [
                ['Email', 'Status', 'Plan', 'Joined', 'Last Login'].join(',')
            ];

            rows.forEach(row => {
                const email = row.querySelector('td:nth-child(1)').textContent.trim();
                const status = row.querySelector('td:nth-child(2)').textContent.trim();
                const plan = row.querySelector('td:nth-child(3)').textContent.trim();
                const joined = row.querySelector('td:nth-child(4)').textContent.trim();
                const lastLogin = row.querySelector('td:nth-child(5)').textContent.trim();

                csvContent.push([email, status, plan, joined, lastLogin].join(','));
            });

            const blob = new Blob([csvContent.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('href', url);
            a.setAttribute('download', 'users_export.csv');
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }

        function confirmDeleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
                deleteUser(userId);
            }
        }
    </script>
</body>
</html>
