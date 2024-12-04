#!/bin/bash

# Substitute environment variables in Apache configs
envsubst '${PORT}' < /etc/apache2/ports.conf > /etc/apache2/ports.conf.tmp
mv /etc/apache2/ports.conf.tmp /etc/apache2/ports.conf

envsubst '${PORT}' < /etc/apache2/sites-available/000-default.conf > /etc/apache2/sites-available/000-default.conf.tmp
mv /etc/apache2/sites-available/000-default.conf.tmp /etc/apache2/sites-available/000-default.conf

# Make sure logs directory exists
mkdir -p ${APACHE_LOG_DIR}
chown -R www-data:www-data ${APACHE_LOG_DIR}

# Start Apache
exec apache2-foreground
