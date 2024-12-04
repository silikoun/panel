#!/bin/bash

# Substitute environment variables in Apache config
envsubst '${PORT}' < /etc/apache2/sites-available/000-default.conf > /etc/apache2/sites-available/000-default.conf.tmp
mv /etc/apache2/sites-available/000-default.conf.tmp /etc/apache2/sites-available/000-default.conf

# Start Apache
apache2-foreground
