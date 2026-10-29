#!/usr/bin/env bash
# Pasos después de cada despliegue en Hostinger (el Git de Hostinger solo
# baja el código y corre composer install). Correr por SSH desde la raíz:
#
#   cd ~/domains/voteusoft.com/public_html && bash deploy.sh
#
# No necesita Node: public/build se compila en local y va en el repositorio.
set -euo pipefail

cd "$(dirname "$0")"

if [ ! -f .env ]; then
    echo "Falta el .env (cópialo desde .env.example y complétalo)." >&2
    exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force
php artisan db:seed --class=RolesYPermisosSeeder --force

# Enlace public/storage (imágenes de pagos y similares)
[ -e public/storage ] || php artisan storage:link

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Los workers del cron toman el código nuevo
php artisan queue:restart

echo "Despliegue listo."
