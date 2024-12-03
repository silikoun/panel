# WooCommerce Dashboard with Supabase Auth

A modern dashboard for WooCommerce with Supabase authentication integration.

## Setup Instructions

1. Install dependencies:
```bash
composer install
```

2. Configure environment variables:
   - Copy `.env.example` to `.env`
   - Fill in your Supabase and WooCommerce credentials

3. Set up your Supabase project:
   - Create a new project at https://supabase.com
   - Enable Email Auth in Authentication settings
   - Copy your project URL and anon key to .env file

4. Set up WooCommerce:
   - Generate API keys in WooCommerce > Settings > Advanced > REST API
   - Add the credentials to your .env file

5. Run the application:
```bash
php -S localhost:8000
```

## Features

- Supabase Authentication
- WooCommerce API Integration
- Modern UI with Tailwind CSS
- API Key Management
- Premium Features Section
