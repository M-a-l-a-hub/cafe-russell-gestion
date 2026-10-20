#!/bin/sh
set -e

# Render fournit $PORT ; 10000 par défaut en local.
PORT="${PORT:-10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

mkdir -p /app/storage/logs
chown -R www-data:www-data /app/storage

# Refuse de démarrer avec une configuration nginx invalide.
nginx -t

exec /usr/bin/supervisord -c /etc/supervisord-app.conf
