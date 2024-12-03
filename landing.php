<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WooCommerce Scraper - Your Ultimate Product Research Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #7c3aed;
            --secondary-color: #6d28d9;
        }
        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
        }
        .hero-section {
            background: linear-gradient(135deg, #f8f5ff 0%, #f0e7ff 100%);
            padding: 80px 0;
        }
        .feature-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .primary-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            border: none;
            transition: background 0.3s ease;
        }
        .primary-btn:hover {
            background: var(--secondary-color);
            color: white;
        }
        .price-card {
            border: 2px solid #f0e7ff;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }
        .price-card:hover {
            transform: translateY(-5px);
        }
        .price-card.featured {
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
        <div class="container">
            <a class="navbar-brand" href="#">
                <strong>WooScraper</strong>
            </a>
            <div class="d-flex">
                <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                <a href="signup.php" class="primary-btn text-decoration-none">Sign Up</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Supercharge Your WooCommerce Research</h1>
                    <p class="lead mb-4">Instantly extract product data from any WooCommerce store. Save hours of manual research with our powerful Chrome extension.</p>
                    <a href="signup.php" class="primary-btn text-decoration-none">Start Free Trial</a>
                </div>
                <div class="col-lg-6">
                    <img src="assets/hero-image.png" alt="WooScraper Demo" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Powerful Features for Product Research</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card card h-100 p-4">
                        <h3 class="h5 mb-3">One-Click Data Extraction</h3>
                        <p>Extract product titles, prices, descriptions, and images with a single click.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card card h-100 p-4">
                        <h3 class="h5 mb-3">Bulk Export</h3>
                        <p>Export data from multiple products simultaneously in CSV or JSON format.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card card h-100 p-4">
                        <h3 class="h5 mb-3">Store Analytics</h3>
                        <p>Get insights into pricing trends and product popularity.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Simple, Transparent Pricing</h2>
            <div class="row justify-content-center g-4">
                <div class="col-md-4">
                    <div class="price-card card h-100 p-4">
                        <div class="card-body">
                            <h3 class="h4 mb-3">Free</h3>
                            <h4 class="display-6 mb-3">$0</h4>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2">✓ Basic data extraction</li>
                                <li class="mb-2">✓ 50 products per month</li>
                                <li class="mb-2">✓ CSV export</li>
                            </ul>
                            <a href="signup.php" class="btn btn-outline-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="price-card featured card h-100 p-4">
                        <div class="card-body">
                            <h3 class="h4 mb-3">Plus</h3>
                            <h4 class="display-6 mb-3">$10<small class="fs-6">/mo</small></h4>
                            <ul class="list-unstyled mb-4">
                                <li class="mb-2">✓ Advanced data extraction</li>
                                <li class="mb-2">✓ Unlimited products</li>
                                <li class="mb-2">✓ Store analytics</li>
                                <li class="mb-2">✓ Priority support</li>
                            </ul>
                            <a href="signup.php?plan=plus" class="primary-btn w-100 text-center text-decoration-none">Get Plus</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">© 2024 WooScraper. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white text-decoration-none me-3">Privacy Policy</a>
                    <a href="#" class="text-white text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
