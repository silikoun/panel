<?php
ini_set('memory_limit', '1G');
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;
use GuzzleHttp\Client;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$isAuthenticated = isset($_SESSION['user']);
$error = null;

try {
    $client = new Client([
        'base_uri' => $_ENV['SUPABASE_URL'],
        'headers' => [
            'apikey' => $_ENV['SUPABASE_KEY'],
            'Content-Type' => 'application/json'
        ],
        'verify' => __DIR__ . '/certs/cacert.pem',
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
            <!-- Dashboard -->
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold">WooCommerce Dashboard</h1>
                        <form action="auth.php" method="POST" class="inline">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                                Logout
                            </button>
                        </form>
                    </div>
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold mb-4">API Configuration</h2>
                        <div class="bg-purple-50 p-6 rounded-lg">
                            <?php
                            // Get user's current token
                            $currentToken = '';
                            if (isset($_SESSION['user']['user']['user_metadata']['api_token'])) {
                                $currentToken = $_SESSION['user']['user']['user_metadata']['api_token'];
                            }
                            ?>
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-purple-700">API Token</h3>
                                <div class="flex space-x-2">
                                    <button onclick="copyToken()" class="text-purple-600 hover:text-purple-800">
                                        Copy Token
                                    </button>
                                    <button onclick="generateNewToken()" class="text-purple-600 hover:text-purple-800">
                                        Generate New Token
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
                    
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold mb-4">Premium Features Section</h2>
                        <div class="bg-purple-50 p-6 rounded-lg">
                            <h2 class="text-xl font-bold mb-2">Get Plus for $10/mo</h2>
                            <p class="text-purple-800 mb-4">
                                With Plus, company and contact details of websites you visit are shown here.
                            </p>
                            <a href="#" class="inline-flex items-center text-purple-600 font-semibold hover:text-purple-800">
                                Sign up 
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
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
            
            // Show success message
            messageDiv.classList.remove('hidden');
            setTimeout(() => {
                messageDiv.classList.add('hidden');
            }, 2000);
        }

        function generateNewToken() {
            fetch('generate_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('apiToken').value = data.token;
                    alert('New token generated successfully!');
                } else {
                    alert('Error: ' + (data.message || 'Failed to generate token'));
                }
            })
            .catch(error => {
                alert('Error generating token. Please try again.');
            });
        }
    </script>
</body>
</html>
        }
    </script>
</body>
</html>
