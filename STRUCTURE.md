# Panel Project Structure

## Directory Overview
```
panel/
├── admin/                  # Admin-specific functionality
├── api/                    # API endpoints
├── auth/                   # Authentication system
├── classes/               # PHP classes
├── database/             # Database migrations and schemas
├── docker/               # Docker configuration
├── includes/             # Shared PHP includes
├── migrations/           # Database migrations
├── public/               # Public assets
└── tests/                # Test files
```

## Key Components

### Core Files
- `index.php`: Main entry point
- `config.php`: Configuration settings
- `dashboard.php`: Main dashboard interface
- `admin_dashboard.php`: Admin control panel
- `subscriptions.php`: Subscription management

### Authentication
- `auth.php`: Core authentication logic
- `login.php`: User login
- `register.php`: User registration
- `token.php`: Token management
- `validate_token.php`: Token validation

### Database
- `database.sql`: Main database schema
- `create_tables.sql`: Table creation scripts
- `setup_db.sql`: Database setup

### Configuration
- `.env`: Environment variables
- `php.ini`: PHP configuration
- `composer.json`: Dependencies
- `Dockerfile`: Container configuration

### Testing
- `test_*.php`: Various test files
- `test_config.php`: Test configuration
- `test_auth.php`: Authentication tests
- `test_db.php`: Database tests

## File Purposes

### Main Application Files
1. `index.php`: Entry point and routing
2. `dashboard.php`: User dashboard interface
3. `users.php`: User management
4. `subscriptions.php`: Subscription handling

### Authentication System
1. `auth.php`: Core authentication
2. `token.php`: Token generation/validation
3. `login.php`: User login interface
4. `register.php`: User registration

### Configuration Files
1. `.env`: Environment configuration
2. `config.php`: Application settings
3. `php.ini`: PHP settings
4. `composer.json`: Package dependencies

### Database Management
1. `database.sql`: Schema definition
2. `migrations/`: Database updates
3. `create_tables.sql`: Table structure

## Development Workflow

### Setup
1. Configure `.env` file
2. Run database migrations
3. Install dependencies via composer
4. Set up PHP configuration

### Testing
1. Use `test_*.php` files
2. Configure test environment
3. Run database tests
4. Validate authentication

### Deployment
1. Docker container setup
2. Environment configuration
3. Database migration
4. Service startup

## Best Practices
1. Always check `.env` configuration
2. Run tests before deployment
3. Follow PHP coding standards
4. Maintain database migrations
5. Keep documentation updated
