<?php
require 'vendor/autoload.php';
session_start();

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$error = $_GET['error'] ?? '';
$message = $_GET['message'] ?? '';
$plan = $_GET['plan'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - WooScraper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f8f5ff 0%, #f0e7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .signup-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2rem;
            max-width: 450px;
            width: 100%;
        }
        .signup-btn {
            background: #7c3aed;
            border: none;
            padding: 12px;
            width: 100%;
        }
        .signup-btn:hover {
            background: #6d28d9;
        }
        .form-control:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.25);
        }
        .plan-badge {
            background: #f8f5ff;
            color: #7c3aed;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="signup-container mx-auto">
                    <h1 class="h3 mb-4 text-center">Create your account</h1>
                    
                    <?php if ($plan === 'plus'): ?>
                    <div class="plan-badge text-center mb-4">
                        Plus Plan - $10/month
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($_GET['message']); ?>
                    </div>
                    <?php endif; ?>

                    <form action="register.php" method="post">
                        <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan); ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   required minlength="8">
                            <div class="form-text">Must be at least 8 characters long</div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required minlength="8">
                        </div>

                        <button type="submit" class="btn signup-btn text-white mb-3">Create Account</button>

                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">Log in</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
