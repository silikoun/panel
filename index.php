<?php
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>WooCommerce Scraper - The Best Product Scraping Tool</title>
    <meta name="description" content="Increase your productivity with WooCommerce Scraper. Export products from any WooCommerce store instantly. The fastest and most accurate scraping tool for e-commerce entrepreneurs.">
    <meta name="keywords" content="WooCommerce, product scraper, e-commerce automation, product management, Chrome extension">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://wooscraper.com/">
    <meta property="og:title" content="WooScraper - WooCommerce Product Scraping Solution">
    <meta property="og:description" content="Automate your WooCommerce product management with our powerful Chrome extension.">
    <meta property="og:image" content="https://wooscraper.com/images/og-image.jpg">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://wooscraper.com/">
    <meta property="twitter:title" content="WooScraper - WooCommerce Product Scraping Solution">
    <meta property="twitter:description" content="Automate your WooCommerce product management with our powerful Chrome extension.">
    <meta property="twitter:image" content="https://wooscraper.com/images/twitter-image.jpg">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="favicon.png">

    <!-- Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-10">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold text-gray-800">WooScraper</h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <a href="login.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative bg-white overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="relative z-10 pb-8 bg-white sm:pb-16 md:pb-20 lg:max-w-2xl lg:w-full lg:pb-28 xl:pb-32">
                <main class="mt-10 mx-auto max-w-7xl px-4 sm:mt-12 sm:px-6 lg:mt-16 lg:px-8 xl:mt-28">
                    <div class="sm:text-center lg:text-left">
                        <h1 class="text-4xl tracking-tight font-extrabold text-gray-900 sm:text-5xl md:text-6xl">
                            <span class="block">Increase your productivity</span>
                            <span class="block text-indigo-600">with WooCommerce Scraper</span>
                        </h1>
                        <p class="mt-3 text-base text-gray-500 sm:mt-5 sm:text-lg sm:max-w-xl sm:mx-auto md:mt-5 md:text-xl lg:mx-0">
                            The best tool to scrape and extract all products from any WooCommerce and WordPress sites in an instant. Our scraper tool is faster, more efficient, and cheaper than all our competitors.
                        </p>
                        <div class="mt-5 sm:mt-8 sm:flex sm:justify-center lg:justify-start">
                            <div class="rounded-md shadow">
                                <a href="signup.php" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 md:py-4 md:text-lg md:px-10">
                                    Get Started
                                </a>
                            </div>
                            <div class="mt-3 sm:mt-0 sm:ml-3">
                                <a href="#features" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 md:py-4 md:text-lg md:px-10">
                                    Learn More
                                </a>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="py-12 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">Features</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Why Choose WooCommerce Scraper?
                </p>
            </div>

            <div class="mt-10">
                <div class="space-y-10 md:space-y-0 md:grid md:grid-cols-3 md:gap-x-8 md:gap-y-10">
                    <!-- Fastest -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Fastest</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Scrape and export an entire website's products in few seconds
                        </p>
                    </div>

                    <!-- Most Accurate -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Most Accurate Data</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            The data extracted are exactly those present on the site at the time of extraction
                        </p>
                    </div>

                    <!-- Reliable -->
                    <div class="relative">
                        <div class="absolute flex items-center justify-center h-12 w-12 rounded-md bg-indigo-500 text-white">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                            </svg>
                        </div>
                        <p class="ml-16 text-lg leading-6 font-medium text-gray-900">Reliable Infrastructure</p>
                        <p class="mt-2 ml-16 text-base text-gray-500">
                            Use our tool anytime on any device with our guaranteed uninterrupted service
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div id="pricing" class="bg-gray-100 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center mb-12">
                <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">Pricing</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Choose Your Plan
                </p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                    Let's choose the package that is best for you and explore it happily and cheerfully.
                </p>
            </div>

            <div class="mt-10 space-y-12 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-x-8">
                <!-- Free Plan -->
                <div class="relative p-8 bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold text-gray-900">Free Plan</h3>
                        <p class="mt-4 flex items-baseline text-gray-900">
                            <span class="text-5xl font-extrabold tracking-tight">$0</span>
                            <span class="ml-1 text-xl font-semibold">/forever</span>
                        </p>
                        <ul class="mt-6 space-y-6">
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export 5 products</span>
                            </li>
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export to Excel/CSV</span>
                            </li>
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Show product details</span>
                            </li>
                        </ul>
                    </div>
                    <a href="signup.php" class="mt-8 block w-full bg-indigo-600 border border-transparent rounded-md py-3 px-6 text-center font-medium text-white hover:bg-indigo-700">
                        Start Free
                    </a>
                </div>

                <!-- Premium Plan -->
                <div class="relative p-8 bg-white border border-gray-200 rounded-2xl shadow-sm flex flex-col">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold text-gray-900">Premium Plan</h3>
                        <p class="mt-4 flex items-baseline text-gray-900">
                            <span class="text-5xl font-extrabold tracking-tight">$39.99</span>
                            <span class="ml-1 text-xl font-semibold">/month</span>
                        </p>
                        <ul class="mt-6 space-y-6">
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export unlimited products</span>
                            </li>
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export to Excel/CSV/Shopify/WooCommerce</span>
                            </li>
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export product reviews</span>
                            </li>
                            <li class="flex">
                                <svg class="flex-shrink-0 w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span class="ml-3 text-gray-500">Export specified collections</span>
                            </li>
                        </ul>
                    </div>
                    <a href="signup.php" class="mt-8 block w-full bg-indigo-600 border border-transparent rounded-md py-3 px-6 text-center font-medium text-white hover:bg-indigo-700">
                        Get Premium
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="bg-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="lg:text-center">
                <h2 class="text-base text-indigo-600 font-semibold tracking-wide uppercase">Testimonials</h2>
                <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                    Trusted by Many Happy Customers
                </p>
                <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                    These are the stories of our customers who have joined us with great pleasure when using our scraping tool.
                </p>
            </div>
        </div>
    </div>

    <!-- Sign Up Section -->
    <div class="bg-indigo-700">
        <div class="max-w-2xl mx-auto text-center py-16 px-4 sm:py-20 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-extrabold text-white sm:text-4xl">
                <span class="block">Ready to get started?</span>
                <span class="block">Start scraping products today.</span>
            </h2>
            <p class="mt-4 text-lg leading-6 text-indigo-200">
                Join thousands of e-commerce entrepreneurs who are already using WooCommerce Scraper to grow their business.
            </p>
            <a href="signup.php" class="mt-8 w-full inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50 sm:w-auto">
                Sign up for free
            </a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="mt-8 border-t border-gray-700 pt-8 md:flex md:items-center md:justify-between">
                <div class="flex space-x-6 md:order-2">
                    <a href="#" class="text-gray-400 hover:text-gray-300">
                        <span class="sr-only">Twitter</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/>
                        </svg>
                    </a>
                </div>
                <p class="mt-8 text-base text-gray-400 md:mt-0 md:order-1">
                    &copy; 2024 WooScraper. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>