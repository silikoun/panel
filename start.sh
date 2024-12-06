#!/bin/bash

# Create PHP configuration file
echo "env[SUPABASE_URL] = ${SUPABASE_URL}
env[SUPABASE_KEY] = ${SUPABASE_KEY}
env[SUPABASE_SERVICE_ROLE_KEY] = ${SUPABASE_SERVICE_ROLE_KEY}
env[SITE_URL] = ${SITE_URL}" > /tmp/railway.conf

# Export variables to current shell
export SUPABASE_URL
export SUPABASE_KEY
export SUPABASE_SERVICE_ROLE_KEY
export SITE_URL

# Start PHP development server with custom configuration
PHP_INI_SCAN_DIR=/tmp php -S 0.0.0.0:${PORT:-8080} -t /var/www/html
