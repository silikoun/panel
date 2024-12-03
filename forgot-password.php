<?php
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$error = $_GET['error'] ?? '';
$message = $_GET['message'] ?? '';
$success = $_GET['success'] ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - WooScraper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f5ff 0%, #f0e7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 400px;
            width: 100%;
        }
        .reset-btn {
            background: #7c3aed;
            border: none;
            padding: 12px;
            width: 100%;
        }
        .reset-btn:hover {
            background: #6d28d9;
        }
        .form-control:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="reset-container mx-auto">
                    <h1 class="h3 mb-4 text-center">Reset Password</h1>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        Password reset instructions have been sent to your email.
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>

                    <p class="text-muted mb-4">Enter your email address and we'll send you instructions to reset your password.</p>

                    <form action="reset-password.php" method="post">
                        <div class="mb-4">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <button type="submit" class="btn reset-btn text-white mb-3">Send Reset Link</button>

                        <div class="text-center">
                            <p class="mb-0">Remember your password? <a href="login.php" class="text-decoration-none">Log in</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
