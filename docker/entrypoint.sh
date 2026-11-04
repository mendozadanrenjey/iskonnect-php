#!/bin/sh
set -eu

data_dir="${APP_DATA_DIR:-/data}"
port="${PORT:-8080}"

case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a numeric TCP port." >&2
        exit 1
        ;;
esac

mkdir -p "$data_dir/profiles" "$data_dir/storage/organization-files" "$data_dir/storage/organization-officers"
cp /var/www/html/public/uploads/profiles/.htaccess "$data_dir/profiles/.htaccess"
chown -R www-data:www-data "$data_dir"

rm -rf /var/www/html/storage /var/www/html/public/uploads/profiles
ln -s "$data_dir/storage" /var/www/html/storage
ln -s "$data_dir/profiles" /var/www/html/public/uploads/profiles

sed -ri "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \\*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
