#!/bin/bash
# Run artisan as www-data to prevent permission issues
cd /var/www/html/x_linic
sudo -u www-data php artisan "$@"
