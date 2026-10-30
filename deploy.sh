#!/usr/bin/env bash
# Pasos después de cada despliegue en Hostinger. Normalmente NO hace falta
# correrlo: la tarea programada deploy:automatico (cada minuto, vía el cron
# schedule:run) hace lo mismo sola cuando llega un commit nuevo. Queda para
# correrlo a mano si algo falla (ver storage/logs/despliegue.log):
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

# El Git de Hostinger corre su propio composer install (con dependencias de
# desarrollo) en cada push. Instalamos igual que él: si aquí se usara
# --no-dev, cada despliegue quitaría paquetes que la caché de Hostinger acaba
# de registrar (p. ej. laravel-lang) y la app respondería 500 unos segundos.
# vendor/ no es accesible desde la web (.htaccess de la raíz).
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php bootstrap/cache/config.php

composer install --optimize-autoloader --no-interaction
php artisan package:discover

php artisan migrate --force
php artisan db:seed --class=RolesYPermisosSeeder --force

# Enlace public/storage (imágenes de pagos y similares)
[ -e public/storage ] || php artisan storage:link

# No optimize:clear: también vaciaría la caché de la app (precálculo del Histórico)
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan event:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Los workers del cron toman el código nuevo
php artisan queue:restart

echo "Despliegue listo."
