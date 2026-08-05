# CLAUDE.md

Guía para trabajar en este repositorio. El código, los nombres de dominio y los comentarios están en **español**; escribe igual (`APP_LOCALE=es`).

## Qué es esto

Panel administrativo ("MetaTank Politic") para **recolección de datos de campaña política en Colombia**. Tres capas de funcionalidad:

1. **Referidos** — personas se registran vía formularios públicos atribuidos a un referidor.
2. **Dashboard de métricas** — KPIs, gráficas (Chart.js) y mapa de municipios (Leaflet + GeoJSON), con exportación a Excel/CSV.
3. **WhatsApp masivo** — envío de plantillas de Twilio, captura de respuestas de botones vía webhook, y métricas de campaña.

Todo el sistema maneja **PII sensible** (nombre completo, cédula, teléfono, puesto de votación, edad, género). Al agregar logs, exports o endpoints, no expandas la superficie de exposición de esos campos sin que se pida explícitamente.

## Stack

- PHP 8.2 · Laravel 12 · MySQL (`app_referido`)
- Livewire 3 + Volt · Blade · Alpine.js · Tailwind 3 (Vite 7)
- `spatie/laravel-permission` (roles/permisos) · `maatwebsite/excel` + PhpSpreadsheet · `twilio/sdk` 8
- Auth: Laravel Breeze (Livewire stack)
- Chart.js y Leaflet se cargan por **CDN** dentro de [stats.blade.php](resources/views/livewire/dashboard/stats.blade.php), no por Vite.

## Comandos

```bash
composer dev        # serve + queue:listen + pail + vite en paralelo (lo normal para desarrollar)
composer test       # config:clear + artisan test
php artisan migrate
php artisan db:seed # roles/permisos + departamentos + municipios + admin desde SEED_ADMIN_*
npm run dev / npm run build
./vendor/bin/pint   # formateo
```

Workers en producción (sin ellos NO se envían mensajes ni agradecimientos):

```bash
php artisan queue:work --queue=campaigns --max-jobs=10 --max-time=3600
php artisan queue:work --queue=messages  --max-jobs=50 --max-time=3600
php artisan queue:work --queue=wpp
php artisan queue:work --queue=whatsapp
```

## Modelo de dominio

### Dos sistemas de referidos independientes (no los mezcles)

| | Sistema "Referrer" | Sistema "Pregoneros" |
|---|---|---|
| Referidor | `Referrer` (campo `code`) | `ReferidorPregonero` (campo `id_unico`, UUID auto) |
| Referido | `PublicRegistration` | `ReferidoPregonero` |
| Link público | `/registro?ref=CODIGO` | `/pregoneros/{id_unico}/referir` |
| Livewire | `Public\RegistrationForm` | `Public\ReferirPregonero` |
| Permisos | `referidores.*`, `registros.*` | `pregoneros_referidores.*` |
| Extra | FK a `municipios`/`departamentos`, alimenta el dashboard | `monto_pagar`, `pago_realizado`, `imagen_pago` |

Ojo con las carpetas: `app/Livewire/Admin/Referrers/` es el primer sistema y `app/Livewire/Admin/Referidores/` es el de pregoneros.

### Otros modelos

- `ImportedPerson` — padrón importado desde Excel (`imported_people`).
- `Municipio` / `Departamento` — catálogo DANE; `PublicRegistration` guarda tanto FK (`*_municipality_id`) como texto legacy (`*_municipality`). El dashboard **une ambos** y el legacy solo cuenta cuando la FK es nula.
- `CallResult` — resultados de llamadas del proveedor Retell (`POST /api/retell/call-result`).

## WhatsApp: tres pilas coexisten

Esto es la mayor fuente de confusión del repo. Antes de tocar cualquier cosa de WhatsApp, identifica en cuál estás.

### 1. Twilio — arquitectura nueva (`Campaign*`)
`Campaign` → `CampaignMessage` → `CampaignBatch` → `CampaignMetrics` → `WhatsAppCampaignResponse`.
Flujo: `CreateCampaignAction` → `CampaignRateLimiter::createBatchesForCampaign` (lotes de 50, ~20 rps) → `ProcessCampaignBatchJob` (cola `campaigns`) → `SendCampaignMessageJob` (cola `messages`) → Twilio.
API REST en [campaigns-api.php](routes/campaigns-api.php) bajo `auth:api`.

### 2. Twilio — pila legacy (`Whatsapp*`), la que usa la UI
`WhatsappCampaign` + `WhatsappMessage` → `SendWhatsappCampaignMessage`, disparada desde `Livewire\Whatsapp\CampaignComposer` (la pantalla real de "WhatsApp Masivo").

> **Crítico:** `WhatsappCampaign` apunta a la tabla `campaigns` y `WhatsappMessage` a `campaign_messages` — **los mismos registros que los modelos nuevos**. Son dos capas Eloquent sobre las mismas tablas, con `$fillable` distintos. Un cambio de esquema o de estados obliga a revisar los dos juegos de modelos.

La migración [`2026_03_16_refactor_campaign_architecture.php`](database/migrations/2026_03_16_refactor_campaign_architecture.php) renombró `whatsapp_campaigns`→`campaigns` y `whatsapp_messages`→`campaign_messages`.

### 3. WPPConnect — WhatsApp sin Twilio
`WppCampaign` / `WppContact` / `WppMessage`, `WppCampaignService` + `SendWppMessage` (cola `wpp`, delay aleatorio 10–25 s por mensaje). UI en `Livewire\Wpp\CampaignUploader`.

> El bloque `services.wppconnect` ya existe en [config/services.php](config/services.php), pero `WPPCONNECT_BASE_URL` y `WPPCONNECT_TOKEN` están vacíos en `.env`: sin ellos la pila no envía nada. Su link del sidebar está comentado.

### 4. Outbox (worker externo)
`WhatsAppOutbox` + [`WhatsAppWorkerController`](app/Http/Controllers/WhatsAppWorkerController.php): un cliente externo hace `GET /api/whatsapp/pull?token=…` para reservar filas y `POST /api/whatsapp/report` para confirmar. Reservas vencidas (`WHATSAPP_RESERVE_SECONDS`) vuelven a `PENDING`. UI: `Livewire\Whatsapp\WhatsappOutboxManager` (importa XLSX).

## Webhooks de Twilio

Hay tres endpoints, y los tres **validan la firma** con el middleware `twilio.signature` ([VerifyTwilioSignature](app/Http/Middleware/VerifyTwilioSignature.php)) y **delegan en la misma acción**, [`ProcessTwilioWebhookAction`](app/Actions/WhatsApp/ProcessTwilioWebhookAction.php), así que da igual cuál configure Twilio:

| Ruta | Controller |
|---|---|
| `POST /api/twilio/whatsapp/status` | `TwilioWhatsappStatusController` — **el configurado en la consola** (ver [WEBHOOK_SETUP.md](WEBHOOK_SETUP.md)) |
| `POST /api/twilio/whatsapp/incoming` | `TwilioWhatsappIncomingController` — mensajes entrantes |
| `POST /api/whatsapp/webhook/status` | `WhatsApp\WebhookController` — arquitectura nueva |

El `statusCallback` que registran los jobs sale de `config('services.twilio.status_callback_path')` (`TWILIO_STATUS_CALLBACK_PATH`), no está escrito a mano en cada job. Debe coincidir con lo configurado en Twilio.

`TWILIO_VERIFY_WEBHOOK_SIGNATURE=false` desactiva la validación: solo para local.

### Detección de botones
Twilio **no manda `ButtonPayload`** en estas plantillas: llega el texto del botón en `Body`. `ButtonIdEnum::fromText()` lo reconoce ignorando mayúsculas, tildes y emojis. Para cambiar los botones de una plantilla, se toca **solo** [ButtonIdEnum](app/Enums/ButtonIdEnum.php): ahí viven el mapa de textos, la etiqueta, el campo de conteo en `campaign_metrics` y el mensaje de agradecimiento.

### Cadena de eventos
`CaptureButtonResponseAction` (deduplica por campaign+message+phone+button) → `ButtonResponseCaptured` → tres listeners: `LogButtonResponse`, `UpdateCampaignMetricsOnResponse`, `SendThankYouMessageOnButtonResponse` (envía el agradecimiento **directo a Twilio**, no vía job).

> `EventServiceProvider` **no está registrado** en [bootstrap/providers.php](bootstrap/providers.php); los listeners funcionan por auto-discovery de Laravel 11+. Si agregas un listener, ponlo en `app/Listeners` con `handle()` tipado, o registra el provider.

## Autorización

`spatie/laravel-permission` con roles **Administrador / Operador / Analista / Referidor** definidos en [RolesYPermisosSeeder.php](database/seeders/RolesYPermisosSeeder.php). Los aliases `role`, `permission`, `role_or_permission` se registran en [bootstrap/app.php](bootstrap/app.php).

Patrón vigente — **doble verificación**: middleware `permission:x.y` en la ruta **y** `abort_unless(auth()->user()->can('x.y'), 403)` dentro del componente Livewire (en `mount()` o `render()`). Mantenlo en componentes nuevos; el middleware solo no protege peticiones Livewire posteriores.

Al agregar un permiso: añádelo al array del seeder, asígnalo a los roles, protege la ruta, verifica dentro del componente y envuelve el link del sidebar en `@can` ([app-sidebar-layout.blade.php](resources/views/components/app-sidebar-layout.blade.php)).

## Convenciones

- **Livewire por página**: la ruta apunta al componente (`Route::get('/dashboard', Stats::class)`), no a un controller. Los controllers quedan solo para exports, webhooks y APIs.
- **Actions / DTOs / Services** en la pila nueva de campañas; el resto del código pone la lógica directo en el componente Livewire. Sigue el patrón del módulo que estés tocando en vez de uniformar.
- Listados: `WithPagination` + `$queryString` + `updatingSearch()` que llama `resetPage()` (ver `Admin\Referidores\Index`).
- Teléfonos: **siempre** vía [`App\Support\PhoneNumber`](app/Support/PhoneNumber.php) — `digits()` para almacenar, `toWhatsApp()` para enviar (E.164 con prefijo `whatsapp:`), `fromWhatsApp()` para guardar en `whatsapp_campaign_responses.phone`, que es `VARCHAR(20)` y no admite el prefijo. No reimplementes la normalización.
- Exports: `maatwebsite/excel` con hojas en [app/Exports/Sheets/](app/Exports/Sheets/), o `streamDownload` + PhpSpreadsheet a mano para los masivos de pregoneros.
- Mensajes de usuario en español; `session()->flash('ok'|'success'|'error', …)`.

## Trampas conocidas

- **`QUEUE_CONNECTION=sync` en local** → los jobs corren inline y no verás el comportamiento real de lotes ni de rate limiting. En producción debe ser `database` o `redis`, con workers levantados.
- **Las migraciones deben correr en MySQL y en sqlite.** Los tests usan sqlite `:memory:` ([phpunit.xml](phpunit.xml)). Nada de `ALTER TABLE … ADD INDEX IF NOT EXISTS`, `MODIFY COLUMN … ENUM` ni `UPDATE … JOIN` sin proteger con `DB::connection()->getDriverName()`.
- **El ENUM de `campaigns.status` se deriva de `CampaignStatusEnum`.** Si agregas un caso al enum, hace falta una migración que amplíe la columna: si no, MySQL rechaza el valor. Ya pasó una vez con `draft`, `batched` y `sending`.
- `IMPLEMENTATION_GUIDE.md` es una nota de trabajo con TODOs; varios pasos ya se hicieron y otros no. No lo tomes como verdad, verifica contra el código.
- **No declares `$queue` (ni `$connection`, `$delay`) como propiedad tipada en un Job.** Ya vienen del trait `Queueable` y redeclararlas tipadas es un **fatal error** de PHP que deja la clase sin cargar. Usa `$this->onQueue('...')` en el constructor.
- `WhatsappCampaign`/`WhatsappMessage` y `Campaign`/`CampaignMessage` comparten tablas (ver arriba): es la deuda técnica más grande que queda.
