#!/bin/bash

# Create environment file
echo "<?php
putenv('SUPABASE_URL=${SUPABASE_URL}');
putenv('SUPABASE_KEY=${SUPABASE_KEY}');
putenv('SUPABASE_SERVICE_ROLE_KEY=${SUPABASE_SERVICE_ROLE_KEY}');
putenv('SITE_URL=${SITE_URL}');
" > /var/www/html/env.php

# Make the environment file readable
chmod 644 /var/www/html/env.php

# Start PHP development server with custom configuration
php -c php.ini -S 0.0.0.0:${PORT:-8080} -t /var/www/html
