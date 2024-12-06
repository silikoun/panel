#!/bin/bash

# Export all environment variables to the PHP process
export $(echo "${RAILWAY_ENVIRONMENT_VARIABLES}" | tr ',' '\n' | xargs)

# Start PHP development server
php -S 0.0.0.0:${PORT:-8080} -t /var/www/html
