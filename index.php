<?php
ini_set('memory_limit', '1G');
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;
use GuzzleHttp\Client;

// Try to load .env file if it exists, otherwise use environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Set environment variables if not already set
if (!isset($_ENV['SUPABASE_URL'])) {
    $_ENV['SUPABASE_URL'] = getenv('SUPABASE_URL');
}
if (!isset($_ENV['SUPABASE_KEY'])) {
    $_ENV['SUPABASE_KEY'] = getenv('SUPABASE_KEY');
}

$isAuthenticated = isset($_SESSION['user']);
$error = null;

try {
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
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

// If user is not logged in, redirect to landing page
if (!isset($_SESSION['user'])) {
    header('Location: landing.php');
    exit;
}

// Get user's current token
$currentToken = '';
if (isset($_SESSION['user']['user']['user_metadata']['api_token'])) {
    $currentToken = $_SESSION['user']['user']['user_metadata']['api_token'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WooCommerce Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <?php if (!$isInitialized): ?>
            <div class="max-w-md mx-auto bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php else: ?>
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <!-- Header with Logout -->
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">WooCommerce Dashboard</h1>
                        <form action="auth.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                                Logout
                            </button>
                        </form>
                    </div>

                    <!-- API Token Section -->
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold mb-4">API Configuration</h2>
                        <div class="bg-purple-50 p-6 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-purple-700">API Token</h3>
                                <div class="flex space-x-2">
                                    <button onclick="copyToken()" class="text-purple-600 hover:text-purple-800">
                                        Copy Token
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <input type="text" id="apiToken" 
                                    class="w-full px-3 py-2 border rounded-lg mb-4 bg-gray-50" 
                                    value="<?php echo htmlspecialchars($currentToken); ?>" 
                                    readonly>
                                <div id="tokenMessage" class="text-sm text-green-600 hidden mb-2">Token copied!</div>
                            </div>
                            <div class="bg-blue-50 p-4 rounded-lg text-sm text-blue-800">
                                <p class="mb-2"><strong>How to use:</strong></p>
                                <ol class="list-decimal list-inside space-y-1">
                                    <li>Copy your API token</li>
                                    <li>Open the WooCommerce Scraper extension</li>
                                    <li>Paste the token in the extension's settings</li>
                                    <li>Click Save to connect your account</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

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