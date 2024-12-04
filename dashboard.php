<?php
ini_set('memory_limit', '1G');
require 'vendor/autoload.php';
session_start();

use GuzzleHttp\Client;

// Load .env file first if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
        error_log("Loaded .env file");
    } catch (Exception $e) {
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

// Set Supabase URL and Key
$_ENV['SUPABASE_URL'] = 'https://kgqwiwjayaydewyuygxt.supabase.co';

// Try to get the key from all possible sources
$supabaseKey = '';

// Try _ENV first (since we loaded .env)
if (isset($_ENV['SUPABASE_KEY']) && !empty($_ENV['SUPABASE_KEY'])) {
    $supabaseKey = $_ENV['SUPABASE_KEY'];
    error_log("Got key from _ENV");
}
// Try _SERVER next
else if (isset($_SERVER['SUPABASE_KEY']) && !empty($_SERVER['SUPABASE_KEY'])) {
    $supabaseKey = $_SERVER['SUPABASE_KEY'];
    error_log("Got key from _SERVER");
}
// Try getenv last
else if (($envKey = getenv('SUPABASE_KEY')) !== false && !empty($envKey)) {
    $supabaseKey = $envKey;
    error_log("Got key from getenv");
}

$_ENV['SUPABASE_KEY'] = $supabaseKey;

$isAuthenticated = isset($_SESSION['user']);
$error = null;

// Redirect to login if not authenticated
if (!$isAuthenticated) {
    header('Location: login.php');
    exit;
}

// Get user's current token
$currentToken = '';
if (isset($_SESSION['user']['user']['user_metadata']['api_token'])) {
    $currentToken = $_SESSION['user']['user']['user_metadata']['api_token'];
}

// Validate environment variables
if (empty($_ENV['SUPABASE_KEY'])) {
    error_log("Missing SUPABASE_KEY after all attempts");
    $isInitialized = false;
    $error = 'Missing required environment variable: SUPABASE_KEY';
} else {
    try {
        // Remove trailing slash from URL if present
        $baseUrl = rtrim($_ENV['SUPABASE_URL'], '/');
        error_log("Using Supabase URL: " . $baseUrl);
        
        $client = new Client([
            'base_uri' => $baseUrl,
            'headers' => [
                'apikey' => $_ENV['SUPABASE_KEY'],
                'Content-Type' => 'application/json'
            ],
            'verify' => false,
            'http_errors' => false
        ]);
        $isInitialized = true;
    } catch (Exception $e) {
        $isInitialized = false;
        error_log('Client initialization error: ' . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WooCommerce Product Scraper</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="text-xl font-bold text-gray-800 hover:text-indigo-600">WooScraper</a>
                    </div>
                    <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                        <a href="dashboard.php" class="border-indigo-500 text-gray-900 inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium">
                            Dashboard
                        </a>
                    </div>
                </div>
                <div class="flex items-center">
                    <form action="auth.php" method="post" class="ml-4">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
            <div class="p-6">
                <h2 class="text-2xl font-bold mb-4">Welcome to Your Dashboard</h2>
                
                <!-- API Token Section -->
                <div class="mb-8 bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">API Token</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <p class="text-sm text-gray-600">Use this token to authenticate your Chrome extension</p>
                                <button onclick="copyToken()" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                    Copy Token
                                </button>
                            </div>
                            <div class="relative">
                                <input type="text" id="apiToken" 
                                    class="w-full px-3 py-2 border rounded-lg bg-gray-100 text-gray-800" 
                                    value="<?php echo htmlspecialchars($currentToken); ?>" 
                                    readonly>
                                <div id="tokenMessage" class="text-sm text-green-600 hidden mt-2">Token copied to clipboard!</div>
                            </div>
                            <div class="mt-4 bg-blue-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">How to use your API token:</h4>
                                <ol class="list-decimal list-inside space-y-1 text-sm text-blue-700">
                                    <li>Copy your API token</li>
                                    <li>Open the WooCommerce Scraper extension</li>
                                    <li>Paste the token in the extension's settings</li>
                                    <li>Click Save to connect your account</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900">Total Products</h3>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">0</div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900">Active Tasks</h3>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">0</div>
                        </div>
                    </div>

                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <h3 class="text-lg font-medium text-gray-900">Completed Tasks</h3>
                            <div class="mt-1 text-3xl font-semibold text-gray-900">0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Token Copy Script -->
    <script>
        function copyToken() {
            const tokenInput = document.getElementById('apiToken');
            const messageDiv = document.getElementById('tokenMessage');
            
            tokenInput.select();
            document.execCommand('copy');
            
            messageDiv.classList.remove('hidden');
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 2000);
        }
    </script>
</body>
</html>
