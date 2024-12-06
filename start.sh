#!/bin/bash

# Debug: Print environment variables
echo "Debug: Environment variables in start.sh:"
echo "SUPABASE_URL: ${SUPABASE_URL}"
echo "SUPABASE_KEY length: ${#SUPABASE_KEY}"
echo "SUPABASE_SERVICE_ROLE_KEY length: ${#SUPABASE_SERVICE_ROLE_KEY}"
echo "SITE_URL: ${SITE_URL}"

# Create environment file with proper quoting and error checking
cat > /var/www/html/env.php << 'EOF'
<?php
// Debug: Print raw environment variables from PHP
error_log("Raw environment variables from env.php:");
error_log("SUPABASE_URL: " . getenv("SUPABASE_URL"));
error_log("SUPABASE_KEY length: " . strlen(getenv("SUPABASE_KEY")));
error_log("SUPABASE_SERVICE_ROLE_KEY length: " . strlen(getenv("SUPABASE_SERVICE_ROLE_KEY")));

// Set environment variables with proper escaping
putenv("SUPABASE_URL=" . base64_decode("${SUPABASE_URL_BASE64:-}"));
putenv("SUPABASE_KEY=" . base64_decode("${SUPABASE_KEY_BASE64:-}"));
putenv("SUPABASE_SERVICE_ROLE_KEY=" . base64_decode("${SUPABASE_SERVICE_ROLE_KEY_BASE64:-}"));
putenv("SITE_URL=${SITE_URL}");

// Verify environment variables were set
error_log("Environment variables after setting:");
error_log("SUPABASE_URL: " . getenv("SUPABASE_URL"));
error_log("SUPABASE_KEY length: " . strlen(getenv("SUPABASE_KEY")));
error_log("SUPABASE_SERVICE_ROLE_KEY length: " . strlen(getenv("SUPABASE_SERVICE_ROLE_KEY")));
EOF

# Make the environment file readable
chmod 644 /var/www/html/env.php

# Export variables to current shell
export SUPABASE_URL_BASE64=$(echo -n "${SUPABASE_URL}" | base64)
export SUPABASE_KEY_BASE64=$(echo -n "${SUPABASE_KEY}" | base64)
export SUPABASE_SERVICE_ROLE_KEY_BASE64=$(echo -n "${SUPABASE_SERVICE_ROLE_KEY}" | base64)

# Start PHP development server with custom configuration
exec php -c php.ini -S 0.0.0.0:${PORT:-8080} -t /var/www/html
