# VoteuSoft — Panel de referidos

Panel administrativo para campañas políticas en Colombia. Registra referidos
atribuidos a sus referidores, muestra métricas en un dashboard y envía campañas
masivas de WhatsApp.

> El sistema almacena datos personales (nombre, cédula, teléfono, puesto de
> votación). Cualquier export, log o endpoint nuevo debe tener eso en cuenta.

## Módulos

| Módulo | Qué hace |
|---|---|
| **Referidos** | Formularios públicos atribuidos a un referidor por código (`/registro?ref=CODIGO`) o por enlace único (`/pregoneros/{id_unico}/referir`). |
| **Dashboard** | KPIs, gráficas de género/edad/municipio (Chart.js) y mapa por departamento (Leaflet), con export a Excel. |
| **WhatsApp masivo** | Envío de plantillas de Twilio, captura de respuestas de botones vía webhook y métricas por campaña. |
| **Importados** | Carga de padrones desde Excel. |
| **Usuarios** | Roles y permisos (Administrador, Operador, Analista, Referidor). |

## Stack

PHP 8.2 · Laravel 12 · Livewire 3 · Blade · Alpine.js · Tailwind 3 · MySQL ·
`spatie/laravel-permission` · `maatwebsite/excel` · `twilio/sdk`

## Puesta en marcha

```bash
composer install
cp .env.example .env
php artisan key:generate
# Configurar DB_* y las credenciales de Twilio en .env
php artisan migrate
php artisan db:seed          # roles, permisos, departamentos, municipios y admin
npm install
npm run build
```

El admin inicial se crea a partir de `SEED_ADMIN_EMAIL` y `SEED_ADMIN_PASSWORD`.
Si faltan, el seeder no crea ningún usuario.

## Desarrollo

```bash
composer dev    # serve + queue:listen + pail + vite en paralelo
composer test   # suite de PHPUnit
./vendor/bin/pint
```

## Colas

Los envíos masivos son asíncronos. En producción hacen falta workers activos
(idealmente bajo supervisor); sin ellos no salen los mensajes de campaña. Los
agradecimientos a las respuestas de botones salen directo desde el webhook.

```bash
php artisan queue:work --queue=wpp
php artisan queue:work --queue=whatsapp
```

`QUEUE_CONNECTION=sync` (útil en local) ejecuta los jobs dentro de la misma
petición e ignora los retrasos entre mensajes: no usarlo en producción.

## Webhooks de Twilio

Configurar como **Status Callback** del Messaging Service:

```
https://TU-DOMINIO/api/twilio/whatsapp/status
```

Debe coincidir con `TWILIO_STATUS_CALLBACK_PATH`. Los tres endpoints de webhook
validan la firma `X-Twilio-Signature` mediante el middleware `twilio.signature`;
`TWILIO_VERIFY_WEBHOOK_SIGNATURE=false` solo debe usarse en local.

Detalle del flujo y troubleshooting en [WEBHOOK_SETUP.md](WEBHOOK_SETUP.md).

## Documentación

- [CLAUDE.md](CLAUDE.md) — arquitectura, convenciones y trampas conocidas.
- [WEBHOOK_SETUP.md](WEBHOOK_SETUP.md) — configuración y pruebas de webhooks.
