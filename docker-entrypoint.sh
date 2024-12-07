#!/bin/sh
set -e

# Start PHP-FPM
php-fpm -F -R &

# Start Nginx
nginx -g 'daemon off;'
