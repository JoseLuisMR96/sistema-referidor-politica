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

# El Git de Hostinger también corre composer install (con dependencias de
# desarrollo) al recibir un push. Si la caché de paquetes queda de esa
# instalación, Laravel busca clases que --no-dev ya quitó y responde 500.
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

composer install --no-dev --optimize-autoloader --no-interaction
php artisan package:discover

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
