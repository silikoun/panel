<?php
require 'vendor/autoload.php';
session_start();

// Initialize environment variables with defaults
$_ENV['SUPABASE_URL'] = getenv('SUPABASE_URL') ?: '';
$_ENV['SUPABASE_KEY'] = getenv('SUPABASE_KEY') ?: '';

// Only try to load .env if it exists
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    try {
        $dotenv->load();
    } catch (Exception $e) {
        // Log error but don't crash
        error_log('Error loading .env file: ' . $e->getMessage());
    }
}

$error = $_GET['error'] ?? '';
$message = $_GET['message'] ?? '';
$plan = $_GET['plan'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - WooCommerce Scraper</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <a href="index.php" class="text-xl font-bold text-gray-800 hover:text-indigo-600">WooScraper</a>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="login.php" class="text-gray-600 hover:text-indigo-600">
                        Already have an account?
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex-grow flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-lg">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Create your account
                </h2>
                <?php if ($plan === 'premium'): ?>
                <div class="mt-4 inline-flex items-center justify-center w-full">
                    <span class="px-4 py-2 rounded-full text-indigo-600 bg-indigo-100 text-sm font-semibold">
                        Premium Plan - $39.99/month
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
            <div class="rounded-md bg-red-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($message); ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
            <div class="rounded-md bg-green-50 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($_GET['message']); ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form class="mt-8 space-y-6" action="register.php" method="post">
                <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan); ?>">
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <label for="email" class="sr-only">Email address</label>
                        <input id="email" name="email" type="email" required 
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                               placeholder="Email address">
                    </div>
                    <div>
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" required minlength="8"
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                               placeholder="Password">
                    </div>
                    <div>
                        <label for="confirm_password" class="sr-only">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required minlength="8"
                               class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                               placeholder="Confirm password">
                    </div>
                </div>

                <div>
                    <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Create Account
                    </button>
                </div>

                <div class="text-sm text-center">
                    <p class="text-gray-600">
                        Already have an account? 
                        <a href="login.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                            Sign in
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('password').addEventListener('input', validatePassword);
        document.getElementById('confirm_password').addEventListener('input', validatePassword);

        function validatePassword() {
            const password = document.getElementById('password');
            const confirm = document.getElementById('confirm_password');
            
            if (password.value !== confirm.value) {
                confirm.setCustomValidity("Passwords don't match");
            } else {
                confirm.setCustomValidity('');
            }
        }
    </script>
</body>
</html>
