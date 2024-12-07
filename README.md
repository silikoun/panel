.# WooCommerce Dashboard with Supabase Auth

A modern dashboard for WooCommerce with Supabase authentication integration.

## Token Authentication System

### Overview
The system uses a two-step authentication process:
1. Users authenticate with their API key
2. System generates a secure extension token for ongoing access

### Database Schema
```sql
CREATE TABLE IF NOT EXISTS extension_tokens (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    CONSTRAINT unique_active_token_per_user UNIQUE (user_id, is_active)
);

CREATE INDEX IF NOT EXISTS idx_token_lookup ON extension_tokens (token) WHERE is_active = TRUE;
```

### Authentication Flow
1. **Initial Authentication (API Key)**
   - User enters API key in Chrome extension
   - Extension sends key to `/api/extension_auth.php`
   - Server validates API key and generates extension token
   - Token is returned to extension with 30-day expiry

2. **Ongoing Authentication (Extension Token)**
   - Extension stores token in localStorage
   - Token is included in all API requests
   - Server validates token before processing requests
   - Expired tokens are automatically deactivated

3. **Token Management**
   - Only one active token per user
   - Old tokens are deactivated when new ones are generated
   - Tokens expire after 30 days
   - Failed validations clear stored tokens

### Security Features
- Secure token generation using cryptographic functions
- Automatic token expiration
- One active token per user
- Token deactivation on logout
- Database indexing for efficient lookups

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
   - Run the extension_tokens migration

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
- Secure Extension Token System
