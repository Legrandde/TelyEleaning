#!/bin/bash
set -e

echo "Démarrage de Tely..."

# À ce stade, le réseau privé Railway (postgres.railway.internal) est
# disponible, contrairement à la phase de build. On peut donc générer
# le cache de config, exécuter les migrations, etc. en toute sécurité.

php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Migrations automatiques au déploiement (retire si tu préfères les lancer manuellement)
php artisan migrate --force

echo " Laravel prêt, lancement de FrankenPHP..."

exec frankenphp run --config /etc/caddy/Caddyfile