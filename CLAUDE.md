# CLAUDE.md

Guía para trabajar en este repositorio. El código, los nombres de dominio y los comentarios están en **español**; escribe igual (`APP_LOCALE=es`).

## Qué es esto

Panel administrativo ("MetaTank Politic") para **recolección de datos de campaña política en Colombia**. Tres capas de funcionalidad:

1. **Referidos** — personas se registran vía formularios públicos atribuidos a un referidor.
2. **Dashboard de métricas** — KPIs, gráficas (Chart.js) y mapa de municipios (Leaflet + GeoJSON), con exportación a Excel/CSV.
3. **WhatsApp masivo** — envío de plantillas de Twilio, captura de respuestas de botones vía webhook, y métricas de campaña.

Todo el sistema maneja **PII sensible** (nombre completo, cédula, teléfono, puesto de votación, edad, género). Al agregar logs, exports o endpoints, no expandas la superficie de exposición de esos campos sin que se pida explícitamente.

La autorización de tratamiento de datos (Ley 1581 de 2012) se recoge **en papel, en una hoja firmada**, fuera del sistema; solo se registra a quien ya firmó. Por eso los formularios públicos no tienen checkbox de consentimiento: no es un olvido.

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

Workers en producción (sin ellos NO se envían los mensajes de campaña; los agradecimientos salen síncronos desde el webhook):

```bash
php artisan queue:work --queue=wpp
php artisan queue:work --queue=whatsapp
```

## Multi-tenant (un tenant = un candidato)

Cada **tenant** es un candidato o campaña ("Camilo Cfer — Concejo municipal"). Sus referidores, registros, pregoneros, padrón importado y envíos de WhatsApp llevan `tenant_id` y no se mezclan con los de otro tenant. Municipios y departamentos se comparten; `call_results` (Retell) sigue siendo global porque el webhook no dice de qué tenant es.

- **Modelo de datos:** cada modelo con datos usa el trait [BelongsToTenant](app/Models/Concerns/BelongsToTenant.php), que hace dos cosas:
  - **scope global:** filtra por el tenant del contexto, incluido el route model binding (un id de otro tenant da 404);
  - **al crear:** pone el `tenant_id` del contexto, y si no hay contexto **lanza una excepción**.

  Si agregas un modelo con datos de un candidato, ponle el trait y agrega `tenant_id` en una migración.
- **Contexto:** [TenantContext](app/Support/TenantContext.php) es un singleton `scoped`. Lo llena el middleware [SetTenantContext](app/Http/Middleware/SetTenantContext.php), que va en el grupo `web`, así que también corre en `/livewire/update`.
- **Usuarios:** `users.tenant_id` admite nulo.
  - **Nulo = global:** elige el tenant en el selector del menú; "Todos los tenants" es solo para consultar.
  - **Con valor = restringido:** `TenantContext::id()` siempre devuelve su tenant, sin importar la sesión.
  - Solo los globales con `tenants.gestionar` administran tenants en `/admin/tenants`.
- **Pantallas que crean datos:** usan el trait [RequiereTenant](app/Livewire/Concerns/RequiereTenant.php) (`exigirTenant()`). En modo "Todos" mandan al usuario a elegir un tenant.
- **Sin usuario logueado** (formularios públicos, webhooks, API, colas, consola) no hay contexto y los modelos no filtran. Por eso el `tenant_id` se pone **explícito**, según el caso:
  - **formularios públicos:** el del referidor del link, buscado con `withoutGlobalScope('tenant')`;
  - **respuestas de botones:** el del mensaje original;
  - **API de WPP:** el campo `tenant` (slug) de la petición;
  - **`referidos:volcar-importados`:** `--tenant=slug`.
- **Escrituras que se saltan el trait:** `DB::table()`, `Model::insert()` y `upsert()` no pasan por el scope ni por el `creating`. Ahí el `tenant_id` va a mano (ver el Outbox, el comando de volcado y `ImportedPeopleSeeder`). Igual con las reglas `exists:`/`unique:` de validación: usa `Rule::exists(...)->where('tenant_id', …)`.
- **Únicos por tenant:** cédula en `public_registrations` y en `imported_people`, y teléfono en `wpp_contacts`. La misma persona puede registrarse con dos candidatos.
- **Twilio:** el tenant puede tener su propio `twilio_messaging_service_sid` (su número); si no, usa el del `.env`. Las respuestas caen solas en el tenant correcto por el mensaje original.
- **Tests:** usa `$this->usarTenant()` de [TestCase](tests/TestCase.php) antes de crear datos. Cada petición HTTP del test vuelve a fijar el contexto desde la sesión. El aislamiento se prueba en [TenantAislamientoTest](tests/Feature/TenantAislamientoTest.php).

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

## WhatsApp: tres formas de enviar

Antes de tocar cualquier cosa de WhatsApp, identifica cuál de las tres estás tocando.

### 1. Twilio — la que usa la UI ("WhatsApp Masivo")
`Livewire\Whatsapp\CampaignComposer` crea un `WhatsappCampaign` y un `WhatsappMessage` por destinatario, y despacha `SendWhatsappCampaignMessage` en la cola `whatsapp`. No hay lotes ni rate limiting propio: Twilio encola del lado del Messaging Service.

Los webhooks usan **otros** modelos sobre las mismas tablas: `Campaign`, `CampaignMessage`, `CampaignMetrics` y `WhatsAppCampaignResponse`.

> **Crítico:** `WhatsappCampaign`/`Campaign` apuntan a la tabla `campaigns`, y `WhatsappMessage`/`CampaignMessage` a `campaign_messages`. Son dos capas Eloquent sobre los mismos registros, con `$fillable` distintos. Un cambio de esquema o de estados obliga a revisar los dos juegos de modelos.
>
> Existió una "arquitectura nueva" (`CreateCampaignAction`, lotes en `campaign_batches`, `CampaignRateLimiter`, API `/api/campaigns`). Nunca se conectó y se borró el 2026-10-11. La tabla `campaign_batches` sigue en la base, sin uso.

La migración [`2026_03_16_refactor_campaign_architecture.php`](database/migrations/2026_03_16_refactor_campaign_architecture.php) renombró `whatsapp_campaigns`→`campaigns` y `whatsapp_messages`→`campaign_messages`.

Estados: `campaigns.status` debe ser un caso de `CampaignStatusEnum` (el modelo `Campaign` lo castea y un valor desconocido lanza `ValueError`; el Composer usa `sending`). `campaign_messages.status` en MySQL solo admite `pending, queued, accepted, sent, delivered, undelivered, failed, read` ([2026_03_17_expand_message_status_enum.php](database/migrations/2026_03_17_expand_message_status_enum.php)); cualquier otro valor revienta por `strict => true`. `UpdateMessageStatusAction` ignora los estados de Twilio fuera de esa lista y los callbacks que harían retroceder el estado. `read` cuenta como entregado.

### 2. WPPConnect — WhatsApp sin Twilio
`WppCampaign` / `WppContact` / `WppMessage`, `WppCampaignService` + `SendWppMessage` (cola `wpp`, delay aleatorio 10–25 s por mensaje). UI en `Livewire\Wpp\CampaignUploader`. La API `POST /api/wpp/campaigns/send` exige `Authorization: Bearer` igual a `WPPCONNECT_API_TOKEN`; si está vacío, el endpoint queda cerrado (401).

> El bloque `services.wppconnect` ya existe en [config/services.php](config/services.php), pero `WPPCONNECT_BASE_URL` y `WPPCONNECT_TOKEN` están vacíos en `.env`: sin ellos la pila no envía nada. Su link del sidebar está comentado.

### 3. Outbox (worker externo)
`WhatsAppOutbox` + [`WhatsAppWorkerController`](app/Http/Controllers/WhatsAppWorkerController.php): un cliente externo hace `GET /api/whatsapp/pull?token=…` para reservar filas y `POST /api/whatsapp/report` para confirmar. Reservas vencidas (`WHATSAPP_RESERVE_SECONDS`) vuelven a `PENDING`. UI: `Livewire\Whatsapp\WhatsappOutboxManager` (importa XLSX).

## Webhooks de Twilio

Hay tres endpoints, y los tres **validan la firma** con el middleware `twilio.signature` ([VerifyTwilioSignature](app/Http/Middleware/VerifyTwilioSignature.php)) y **delegan en la misma acción**, [`ProcessTwilioWebhookAction`](app/Actions/WhatsApp/ProcessTwilioWebhookAction.php), así que da igual cuál configure Twilio:

| Ruta | Controller |
|---|---|
| `POST /api/twilio/whatsapp/status` | `TwilioWhatsappStatusController` — **el configurado en la consola** (ver [WEBHOOK_SETUP.md](WEBHOOK_SETUP.md)) |
| `POST /api/twilio/whatsapp/incoming` | `TwilioWhatsappIncomingController` — mensajes entrantes |
| `POST /api/whatsapp/webhook/status` | `WhatsApp\WebhookController` — se conserva por si Twilio quedó apuntando aquí |

El `statusCallback` que registra `SendWhatsappCampaignMessage` sale de `config('services.twilio.status_callback_path')` (`TWILIO_STATUS_CALLBACK_PATH`). Debe coincidir con lo configurado en Twilio.

`TWILIO_VERIFY_WEBHOOK_SIGNATURE=false` desactiva la validación: solo para local.

Detrás de un proxy o balanceador que termina HTTPS, hay que definir `TRUSTED_PROXIES` (lo lee [bootstrap/app.php](bootstrap/app.php)). Sin eso, `fullUrl()` sale con `http://`, la firma nunca valida y todos los webhooks dan 403. Además, `request()->ip()` sería la del proxy para todos, y el límite por IP de los formularios públicos (30 envíos por minuto) se volvería un límite global.

### Detección de botones
Twilio **no manda `ButtonPayload`** en estas plantillas: llega el texto del botón en `Body`. `ButtonIdEnum::fromText()` lo reconoce ignorando mayúsculas, tildes y emojis. Para cambiar los botones de una plantilla, se toca **solo** [ButtonIdEnum](app/Enums/ButtonIdEnum.php): ahí viven el mapa de textos, la etiqueta, el campo de conteo en `campaign_metrics` y el mensaje de agradecimiento.

### Cadena de eventos
`CaptureButtonResponseAction` (una respuesta por campaign+phone) → `ButtonResponseCaptured` → tres listeners: `LogButtonResponse`, `UpdateCampaignMetricsOnResponse`, `SendThankYouMessageOnButtonResponse` (envía el agradecimiento **directo a Twilio**, no vía job, dentro de la misma petición del webhook).

> Una respuesta por persona y campaña: la primera gana. Ante un duplicado (reintento de Twilio, doble toque u otro candidato) `CaptureButtonResponseAction` devuelve la fila **existente** y `ProcessTwilioWebhookAction` solo dispara el evento si `$response->wasRecentlyCreated`. No quites esa condición: sin ella se cuenta el voto otra vez y se reenvía (y cobra) el agradecimiento.
>
> El agradecimiento **no** se guarda como `CampaignMessage` (inflaba los totales); su resultado queda en `whatsapp_campaign_responses.processing_status` / `processing_error`.

> `EventServiceProvider` **no está registrado** en [bootstrap/providers.php](bootstrap/providers.php); los listeners funcionan por auto-discovery de Laravel 11+. Si agregas un listener, ponlo en `app/Listeners` con `handle()` tipado, o registra el provider.

## Autorización

`spatie/laravel-permission` con roles **Administrador / Operador / Analista / Referidor** definidos en [RolesYPermisosSeeder.php](database/seeders/RolesYPermisosSeeder.php). Los aliases `role`, `permission`, `role_or_permission` se registran en [bootstrap/app.php](bootstrap/app.php).

Patrón vigente — **doble verificación**: middleware `permission:x.y` en la ruta **y** `abort_unless(auth()->user()->can('x.y'), 403)` dentro del componente Livewire (en `mount()` o `render()`). Mantenlo en componentes nuevos; el middleware solo no protege peticiones Livewire posteriores.

Varios permisos del seeder no se usan en ningún lado (`registros.ver_propios`, `registros.crear`, `registros.cambiar_estado`, `importados.importar|exportar|eliminar`, `referidores.eliminar|asignar_acceso`, `reportes.ver`): asignarlos no habilita nada. `usuarios.roles` sí se usa: sin él, `UserCreate`/`UserEdit` no cambian roles.

Al agregar un permiso: añádelo al array del seeder, asígnalo a los roles, protege la ruta, verifica dentro del componente y envuelve el link del sidebar en `@can` ([app-sidebar-layout.blade.php](resources/views/components/app-sidebar-layout.blade.php)).

## Convenciones

- **Livewire por página**: la ruta apunta al componente (`Route::get('/dashboard', Stats::class)`), no a un controller. Los controllers quedan solo para exports, webhooks y APIs.
- **Actions / DTOs** en el procesamiento de webhooks de WhatsApp; el resto del código pone la lógica directo en el componente Livewire. Sigue el patrón del módulo que estés tocando en vez de uniformar.
- Listados: `WithPagination` + `$queryString` + `updatingSearch()` que llama `resetPage()` (ver `Admin\Referidores\Index`).
- Teléfonos: **siempre** vía [`App\Support\PhoneNumber`](app/Support/PhoneNumber.php) — `digits()` para almacenar, `toWhatsApp()` para enviar (E.164 con prefijo `whatsapp:`), `fromWhatsApp()` para guardar en `whatsapp_campaign_responses.phone`, que es `VARCHAR(20)` y no admite el prefijo. No reimplementes la normalización.
- Exports: `maatwebsite/excel` con hojas en [app/Exports/Sheets/](app/Exports/Sheets/), o `streamDownload` + PhpSpreadsheet a mano para los masivos de pregoneros.
- Mensajes de usuario en español; `session()->flash('ok'|'success'|'error', …)`.

## Trampas conocidas

- **`QUEUE_CONNECTION=sync` en local** → los jobs corren inline y se ignoran los `delay` (por ejemplo, los 10–25 s entre mensajes de WPPConnect). En producción debe ser `database` o `redis`, con workers levantados.
- **Las migraciones deben correr en MySQL y en sqlite.** Los tests usan sqlite `:memory:` ([phpunit.xml](phpunit.xml)). Nada de `ALTER TABLE … ADD INDEX IF NOT EXISTS`, `MODIFY COLUMN … ENUM` ni `UPDATE … JOIN` sin proteger con `DB::connection()->getDriverName()`.
- **El ENUM de `campaigns.status` se deriva de `CampaignStatusEnum`.** Si agregas un caso al enum, hace falta una migración que amplíe la columna: si no, MySQL rechaza el valor. Ya pasó una vez con `draft`, `batched` y `sending`.
- **No declares `$queue` (ni `$connection`, `$delay`) como propiedad tipada en un Job.** Ya vienen del trait `Queueable` y redeclararlas tipadas es un **fatal error** de PHP que deja la clase sin cargar. Usa `$this->onQueue('...')` en el constructor.
- `WhatsappCampaign`/`WhatsappMessage` y `Campaign`/`CampaignMessage` comparten tablas (ver arriba): es la deuda técnica más grande que queda.
- **Carbon 3** (`nesbot/carbon` 3.x): `diffIn*()` devuelve un valor **con signo** y float. `$futuro->diffInMilliseconds(now())` es negativo. Usa `now()->diffInMilliseconds($futuro)` o `abs()`.
- `MySQL` no corre en local por defecto y `php artisan migrate:status` falla con "conexión denegada". Los tests usan sqlite: no detectan problemas de ENUM, y lo que es solo-MySQL (`TIMESTAMPDIFF`, agrupar por un alias ambiguo) revienta en sqlite. Escribe SQL portable. La migración [2026_10_11_000002_sqlite_relax_status_checks.php](database/migrations/2026_10_11_000002_sqlite_relax_status_checks.php) quita en sqlite los CHECK viejos de `status`, para que los tests acepten los mismos valores que MySQL.
- `referidos:volcar-importados` ([VolcarImportadosACommand](app/Console/Commands/VolcarImportadosACommand.php)) y [database/sql/volcar-importados.sql](database/sql/volcar-importados.sql) copian el padrón a `public_registrations` con un referidor **al azar**. Esas filas llevan `origen = 'importado'`; las del formulario, `origen = 'formulario'`. Los volcados anteriores a la columna se marcan a mano con el bloque 0 del SQL. El ranking de referidores del dashboard **excluye** los `importado` (la condición va en el `LEFT JOIN`, para no perder a los referidores con 0); los KPIs totales y las gráficas sí los incluyen.
- Los cálculos del dashboard viven en [DashboardStats](app/Services/DashboardStats.php), que comparten la pantalla ([Stats](app/Livewire/Dashboard/Stats.php)) y el export ([DashboardExportController](app/Http/Controllers/DashboardExportController.php)). No dupliques consultas en uno solo.
