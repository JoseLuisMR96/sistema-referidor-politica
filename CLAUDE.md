# CLAUDE.md

Guía para trabajar en este repositorio. El código, los nombres de dominio y los comentarios están en **español**; escribe igual (`APP_LOCALE=es`).

## Qué es esto

Panel administrativo ("VoteuSoft", antes "MetaTank Politic") para **recolección de datos de campaña política en Colombia**. Tres capas de funcionalidad:

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
                    # En Windows pail no corre (no hay ext pcntl): avisa y sigue; logs en storage/logs/laravel.log
php artisan schedule:work  # en local reemplaza al cron (sincronización diaria de redes); opcional
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
  - **Eliminar un candidato** ([EliminarTenant](app/Services/EliminarTenant.php)) borra todos sus datos en orden de FK, dentro de una transacción, y exige escribir su nombre para confirmar.
    - **Se niega si tiene usuarios restringidos:** `users.tenant_id` es SET NULL, así que esos usuarios quedarían **globales**.
    - Si agregas una tabla con `tenant_id`, súmala a `EliminarTenant::TABLAS` en el orden correcto.
  - **Demo para presentar:** [DemoCandidatoSeeder](database/seeders/DemoCandidatoSeeder.php) crea para Henry 12 referidores, 640 registros en el Meta y 15 pregoneros con referidos.
    - Todo ficticio y marcado (`origen = demo`, códigos `DEMO…`, cédulas `99…`), sin teléfonos. Se repite sin duplicar.
    - No usa Faker, que en producción no está.
    - Se borra con `php artisan demo:borrar henry-palma`.
  - **Candidatos con seeder:** [HenryPalmaSeeder](database/seeders/HenryPalmaSeeder.php) (Gobernación del Meta 2027). No está en `DatabaseSeeder`; córrelo con `--class`. Sus imágenes opcionales van en `database/seeders/marca/henry-palma/`.
- **Pantallas que crean datos:** usan el trait [RequiereTenant](app/Livewire/Concerns/RequiereTenant.php) (`exigirTenant()` en las acciones que guardan) y ponen `<x-tenant-requerido accion="…" />` al inicio de la vista.
  - En modo "Todos" la pantalla **abre igual** y muestra un selector para elegir el candidato ahí mismo; guardar devuelve el error `tenant`.
  - **No redirijas desde `mount()`:** se hizo así al principio y parecía que el menú no llevaba a la pantalla.
- **Sin usuario logueado** (formularios públicos, webhooks, API, colas, consola) no hay contexto y los modelos no filtran. Por eso el `tenant_id` se pone **explícito**, según el caso:
  - **formularios públicos:** el del referidor del link, buscado con `withoutGlobalScope('tenant')`;
  - **respuestas de botones:** el del mensaje original;
  - **API de WPP:** el campo `tenant` (slug) de la petición;
  - **`referidos:volcar-importados`:** `--tenant=slug`.
- **Escrituras que se saltan el trait:** `DB::table()`, `Model::insert()` y `upsert()` no pasan por el scope ni por el `creating`. Ahí el `tenant_id` va a mano (ver el Outbox, el comando de volcado y `ImportedPeopleSeeder`). Igual con las reglas `exists:`/`unique:` de validación: usa `Rule::exists(...)->where('tenant_id', …)`.
- **Únicos por tenant:** cédula en `public_registrations` y en `imported_people`, y teléfono en `wpp_contacts`. La misma persona puede registrarse con dos candidatos.
- **Twilio:** el tenant puede tener su propio `twilio_messaging_service_sid` (su número); si no, usa el del `.env`. Las respuestas caen solas en el tenant correcto por el mensaje original.
- **Circunscripción:** cada tenant tiene `cargo` ([CargoEnum](app/Enums/CargoEnum.php)), `departamento_id` y `municipio_id`. El ámbito del cargo decide qué es obligatorio:
  - **nacional** (Senado, Presidencia): nada;
  - **departamental** (Gobernación, Asamblea, Cámara): departamento;
  - **municipal** (Alcaldía, Concejo, JAL): departamento y municipio.

  `DashboardStats::circunscripcion()` cuenta los registros por **municipio de votación (FK)** dentro de esa zona: los que pueden votar por el candidato, los que votan fuera y los que no tienen municipio. El formulario público arranca en el lugar del candidato, y el mapa del dashboard en su departamento.
- **Identidad visual (marca):** el tenant tiene `partido`, `slogan`, `color_primario`, `color_secundario`, `logo_path`, `foto_path` y `fondo_path` (imagen decorativa fija detrás del contenido, con opacidad baja). Las URLs de `marca.archivo` son **relativas**: así funcionan en cualquier dominio, aunque `APP_URL` sea otro. Las imágenes van en el disco `public` y se sirven por la ruta `marca.archivo` (`/marca/{slug}/{logo|foto}`), así no depende de `storage:link`. No se aceptan SVG.
  - **Cómo cambia el color de toda la app:** las vistas usan clases `indigo-*` como color de marca. En [tailwind.config.js](tailwind.config.js), `indigo` lee variables CSS `--marca-50…950`, cuyos valores por defecto (el índigo original) están en [app.css](resources/css/app.css). El componente `<x-marca :tenant>` imprime en el `<head>` la paleta del candidato generada por [Marca](app/Support/Marca.php): el color elegido queda en el tono 600 y los demás tonos salen de mezclarlo con blanco o negro.
  - **Color secundario:** clases `secundario` y `secundario-texto`, que también son variables.
  - **Tema completo** (`tenants.tema_completo`): el fondo, las tarjetas y los textos de toda la app toman el color primario.
    - `Marca::tema()` deriva las variables `--tema-*`. El texto sale blanco o casi negro según el contraste, y el acento es el secundario o, si no hay, el color del texto.
    - El layout pone `class="tema-marca"` en `<html>`, y `app.css` reescribe con esa clase los neutros (`bg-white`, `text-slate-*`, `border-gray-*`…). Si una vista usa una clase neutra nueva que no cambia, agrégala en `app.css`.
    - Chart.js: no cambies `Chart.defaults.borderColor`/`backgroundColor`, porque apaga la paleta automática.
  - **Para que algo nuevo tome el color del candidato,** usa `indigo-*` o `secundario`, nunca un hex fijo. Después de tocar `tailwind.config.js` o clases nuevas, corre `npm run build` (o `composer dev`).
  - **Dónde se aplica:**
    - **App:** el layout toma el tenant del contexto. En "Todos" se ve el diseño por defecto.
    - **Formularios públicos:** el tenant del referidor del link llega al layout como `tenantMarca`, y la vista lo usa en `<x-cabecera-candidato>`.
- **Tests:** usa `$this->usarTenant()` de [TestCase](tests/TestCase.php) antes de crear datos. Cada petición HTTP del test vuelve a fijar el contexto desde la sesión. El aislamiento se prueba en [TenantAislamientoTest](tests/Feature/TenantAislamientoTest.php).

## Redes sociales (Meta)

- **Credenciales globales:** viven en `integraciones` (proveedor `meta`), cifradas con el cast `encrypted:array`. Si se rota `APP_KEY`, hay que volver a cargarlas.
  - Se configuran en `/admin/redes`: solo usuarios globales con `redes.configurar`.
  - URL base y versión de la Graph API son configurables. Meta retira versiones viejas: si falla por versión, súbela ahí.
- **Token de Meta:** al guardar un token nuevo en `/admin/redes`, `MetaGraphClient::extenderToken()` lo cambia por uno de **~60 días** (`fb_exchange_token`, con App ID y App Secret) y guarda `token_vence` en las credenciales.
  - El del Graph API Explorer dura **horas**: ya pasó que se venció de un día para otro.
  - El Dashboard de redes avisa cuando faltan 10 días o menos, si ya venció o si Meta rechaza el token.
- **Cuentas del candidato:** [RedCuenta](app/Models/RedCuenta.php), por tenant. Su `access_token`, también cifrado, existe solo cuando el candidato da acceso.
- **Qué mide [RedesSincronizador](app/Services/Redes/RedesSincronizador.php)** según el acceso:
  - **Instagram sin acceso:** Business Discovery con la cuenta IG profesional propia. El token debe tener `instagram_basic`, `instagram_manage_insights`, `pages_show_list`, `pages_read_engagement` y `business_management`; sin `instagram_manage_insights`, Meta responde el error #10. Solo funciona con cuentas profesionales y solo trae datos públicos: seguidores, publicaciones y me gusta/comentarios.
  - **Instagram con token del candidato:** sus datos directos.
  - **Facebook:** **siempre** necesita el token de la página. Meta no da métricas de páginas ajenas.
- **Fotos diarias:** cada sincronización guarda una fila por cuenta y día en `redes_metricas`.
  - El comando es `redes:sincronizar`, programado a las 06:00 en [routes/console.php](routes/console.php). En producción hace falta el cron `* * * * * php artisan schedule:run`.
  - `withoutOverlapping()` usa la caché, así que `schedule:list` en local con MySQL apagado falla. Usa `CACHE_STORE=array`.
- **Dos dashboards separados:**
  - `/dashboard` ([Stats](app/Livewire/Dashboard/Stats.php)): solo registros.
  - `/dashboard/redes` ([Redes](app/Livewire/Dashboard/Redes.php), permiso `redes.ver`): las redes. No vuelvas a mezclar redes en el de registros.

  [RedesStats](app/Services/Redes/RedesStats.php) da al dashboard de redes dos vistas:
  - **`resumen()`** (con candidato elegido): totales y crecimiento semanal;
  - **`comparativo()`** (en "Todos"): recorre cada tenant con `TenantContext::con()` para no mezclar cuentas.
- **Rivales:** `redes_cuentas.tipo` vale `propia` o `rival`.
  - Los rivales son otros candidatos al mismo cargo. **Solo Instagram** (Business Discovery), nunca con token.
  - `RedesStats::resumen()` y los totales **excluyen** rivales.
  - `rivales()` compara el Instagram del candidato con los rivales por crecimiento en 7 días y por engagement (interacciones de la semana por cada 100 seguidores).
- **Tono de los comentarios:** [AnalizadorComentarios](app/Services/Redes/Tono/AnalizadorComentarios.php).
  - **Requisitos:** solo cuentas propias con **token del candidato**, porque Meta no da comentarios ajenos.
  - **Cuándo corre:** en `redes:sincronizar` y con el botón "Analizar comentarios".
  - **Clasificación:** la hace [ClaudeClasificadorTono](app/Services/Redes/Tono/ClaudeClasificadorTono.php) con el SDK oficial `anthropic-ai/sdk`:
    - modelo `claude-opus-5-5` con effort `low`;
    - salida estructurada `json_schema`;
    - `fallbacks: default` (beta `server-side-fallback-2026-07-01`).
  - **Credencial:** la API key va cifrada en `integraciones` (proveedor `anthropic`).
  - **Interfaz:** el resto del código depende de la interfaz `ClasificadorTono`; los tests enlazan un clasificador falso.
  - **Privacidad, no la rompas:** a Meta se le piden solo `id,text` (nunca el autor). El texto vive en memoria. En la base quedan los conteos y temas por día (`redes_tono`) y una huella HMAC del id (`redes_comentarios_vistos`) para no reclasificar.
- **Instalar el SDK en local:** `composer require`/`install` necesita `--ignore-platform-req=php`, porque el PHP local es 8.5 y `phpspreadsheet` declara soporte hasta 8.4. Producción usa 8.2. En Windows, si falla con "Could not delete…", borra `vendor/anthropic-ai` y reintenta: es un bloqueo de archivos del IDE o del antivirus.
- **No hacer scraping ni guardar personas con inclinación política inferida.** Solo cifras agregadas (Ley 1581: la opinión política es dato sensible).

## Histórico electoral

`/dashboard/historico` ([Historico](app/Livewire/Dashboard/Historico.php), permiso `historico.ver`) muestra quién ganó en cada departamento y municipio por año y cargo, con mapa, contrincantes, margen y, con candidato elegido, sus registros por municipio.

- **Datos globales:** la tabla `historico_electoral` **no** lleva `tenant_id`: son resultados públicos de la Registraduría, iguales para todos los candidatos.
- **Una fila** = votos de un candidato o lista en un municipio (o comuna para JAL). El total del departamento se suma; no se guardan filas de total.
- **"Ganador"** = el más votado del lugar. En asamblea, concejo y JAL (listas) es el partido más votado. Los cálculos viven en [HistoricoElectoralStats](app/Services/Historico/HistoricoElectoralStats.php).
- **Importar:** [ImportadorHistorico](app/Services/Historico/ImportadorHistorico.php), desde la misma pantalla. Solo usuarios **globales** con `historico.importar`.
  - CSV (`,` o `;`, UTF-8 o Windows-1252) o Excel. Plantilla en [public/plantillas/historico-electoral.csv](public/plantillas/historico-electoral.csv).
  - Reimportar reemplaza por año + cargo + departamento + municipio.
  - Ignora votos nulos y no marcados. Lo que no reconoce como lugar lo reporta y no lo importa.
- **Mapa por código DANE:** `municipios.codigo_dane` se llena con [CatalogoDane](app/Support/CatalogoDane.php) desde [database/data/municipios_dane.csv](database/data/municipios_dane.csv) (generado cruzando los GeoJSON con el catálogo). También corrige nombres con la Ñ dañada y agrega San José de Uré y Tuchín. El mapa pinta por `COD_MPIO`, no por nombre.
- **Fuente:** [Observatorio de la Registraduría](https://observatorio.registraduria.gov.co/views/electoral/historicos-resultados.php) → "Visor histórico de resultados electorales".
  - **1997–2015:** cada año es un aplicativo para Windows. Al instalarlo deja una SQLite en `C:\Estadisticas\db\Estadisticas.db`. Se carga con `php artisan historico:importar-registraduria "C:\Estadisticas\db\Estadisticas.db"` ([comando](app/Console/Commands/ImportarEstadisticasRegistraduriaCommand.php)). El año lo saca de la tabla `parametros`; `--depto=META` limita a un departamento.
  - **Bases por elección:** territoriales en `Estadisticas.db`; presidencia en `Estadisticas1.db` (1.ª vuelta) y `Estadisticas2.db` (2.ª); Congreso en `EstadisticasCon.db`. El ámbito de cada cargo sale de `corporac.corpinde` (NA, DE, MU, CO). La vuelta se detecta sola: la 2.ª es en junio, o se pasa con `--vuelta`.
  - **Qué se deja fuera:** el Parlamento Andino, las circunscripciones especiales (indígena y afro) y los consulados.
  - **Codificación:** viene en UTF-8, en Latin-1 o con la Ñ codificada dos veces, y `texto()` lo normaliza. Si el nombre del partido viene dañado, se toma de su fila de voto por la lista.
  - **Qué lee:** `x_resum_municipio_XX`, `x_resum_comuna_XX` (JAL) y `elegidos`. Nunca `candidat`, que trae cédulas.
  - **Cómo cruza los lugares:** los códigos de la Registraduría no son DANE, así que el cruce es por nombre, con los alias de `ImportadorHistorico::ALIAS_DANE` y por prefijo único. Los corregimientos departamentales del Vaupés no son municipios y quedan fuera.
  - **Congreso 2022:** 34 CSV "mesa a mesa" en **UTF-16**, uno por departamento más consulados.
    - El importador detecta la marca de orden de bytes (BOM) y convierte al vuelo con `convert.iconv`.
    - Solo entran Senado nacional y Cámara departamental: el filtro de circunscripción descarta indígenas, afro y CITREP.
    - Para cargar varios archivos, usa `--sin-precalcular` y al final corre `historico:precalcular`.
  - **Congreso 2026 (formato "Estructuras Básicas"):** el escrutinio `MMV_9999.csv` (~10 GB, dentro de un zip) trae **solo códigos** separados por `;`, sin encabezado. Los nombres salen de los archivos básicos: DIVIPOL, PARTIDOS, CANDIDATOS y CORPORACION, de ancho fijo y en Latin-1.
    - Se carga con `php -d memory_limit=6G artisan historico:importar-escrutinio <zip o csv> <carpeta básicos> --anio=2026` ([comando](app/Console/Commands/ImportarEscrutinioMmvCommand.php)). Lee directo del zip y suma por municipio mientras lee; tarda unos 5 minutos.
    - Partido `0000` con candidato `996`/`997`/`998` = blanco, nulos y no marcados. Solo entran Senado y Cámara en circunscripción `0` y `1`.
    - De CANDIDATOS **nunca** se lee la cédula, solo nombre y apellido.
    - El preconteo (`mmvPRECONTEO…`) no se usa: el escrutinio es el oficial.
    - **Presidencia 2026** usa el mismo formato y el mismo comando con `--vuelta=1|2`. El `Novedad*.csv` trae datos por mesa (participación, recuentos), no votos, y no se usa.
    - Queda fuera Nuevo Belén de Bajirá (Chocó), un municipio nuevo que no está en el catálogo ni en los mapas.
  - **2018 en adelante:** CSV "mesa a mesa" (`Resultados.csv`, una fila por puesto). Se carga con `php artisan historico:importar-archivo Resultados.csv --anio=2018 --vuelta=1` ([comando](app/Console/Commands/ImportarHistoricoArchivoCommand.php)) o desde la pantalla.
    - El importador suma los puestos por municipio y deja fuera consulados y circunscripciones especiales.
    - "Código Municipio" de la Registraduría **no** es DANE: no lo mapees a `codigo_dane`.
    - 2019 territoriales viene en un solo `MMV_2019_NACIONAL.csv` de ~3 GB, con una fila por mesa (15 M filas). El TXT trae el MD5 para verificar la descarga.
    - El importador lee **en streaming**. En `guardar()` normaliza nombres y lugares una sola vez (memoria por texto) y suma en memoria por lugar. Para archivos así usa el comando con `-d memory_limit=4G`, no la pantalla.
    - Convenciones 2019: voto por la lista = código de candidato `00000`; blanco, nulos y no marcados llegan con partido `N/A` y el nombre en el candidato; la comuna llega como "01COMUNA 1 POPULAR". 2019 no trae elegidos ni curules. Algunos municipios vienen sin la Ñ ("PUERTO CARREO", "PEOL"): el catálogo de búsqueda incluye también los nombres sin Ñ (`sinEnie()`).
    - Congreso 2018 no tiene descarga en el Observatorio.
- **Velocidad:**
  - **Caché permanente:** `HistoricoElectoralStats` guarda cada consulta en caché sin vencimiento. La clave lleva una versión (`historico:version`) que `ImportadorHistorico::guardar()` sube al terminar (`invalidarCache()`).
  - **Precálculo:** los comandos de importación corren al final `historico:precalcular`, que llena todas las vistas (país y cada departamento) en unos 25 s. Si importas desde la pantalla, la caché se llena sola con el uso. Si agregas un método público de consulta, envuélvelo con `recordar()`.
  - **Índice:** `historico_filtros` = (anio, cargo, vuelta, departamento_id, municipio_id, partido).
  - **Mapas web:** se sirven desde `public/data/deptos-web/`, 7 MB en total frente a 286 MB, simplificados a unos 50 m con [database/data/simplificar_geojson.py](database/data/simplificar_geojson.py). Los originales de `public/data/deptos/` se quedan como fuente. Los usan el Histórico y el dashboard de registros.
- **Vuelta:** columna `historico_electoral.vuelta` (1 por defecto). `HistoricoElectoralStats::$vuelta` filtra todas las consultas a través de `tabla()`.
- **Corporaciones (listas):** asamblea, concejo, JAL y Congreso se agrupan por **partido** (lista + voto preferente). Las curules son los elegidos del partido.

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
`Livewire\Whatsapp\CampaignComposer` crea un `WhatsappCampaign` y un `WhatsappMessage` por destinatario, y despacha `SendWhatsappCampaignMessage` en la cola `whatsapp`. **Solo envía por template aprobado** (Content SID `HX…`): el modo de mensaje libre (texto, imagen o ubicación) se quitó de la UI porque WhatsApp solo permite iniciar conversación con template. El job todavía soporta `body`/`media` por si se retoma. No hay lotes ni rate limiting propio: Twilio encola del lado del Messaging Service.

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

- **`public/build` va en el repositorio** (Hostinger no tiene Node). Antes de subir cambios de vistas o CSS, corre **`npm run build`** y súbelo. Con `composer dev` corriendo, Vite genera las clases al vuelo (`public/hot`) y en local todo se ve bien aunque el build esté viejo: ya pasó con la imagen de fondo, que en producción salía opaca y encima del encabezado.

- **`QUEUE_CONNECTION=sync` en local** → los jobs corren inline y se ignoran los `delay` (por ejemplo, los 10–25 s entre mensajes de WPPConnect). En producción debe ser `database` o `redis`, con workers levantados.
- **Las migraciones deben correr en MySQL y en sqlite.** Los tests usan sqlite `:memory:` ([phpunit.xml](phpunit.xml)). Nada de `ALTER TABLE … ADD INDEX IF NOT EXISTS`, `MODIFY COLUMN … ENUM` ni `UPDATE … JOIN` sin proteger con `DB::connection()->getDriverName()`.
- **El ENUM de `campaigns.status` se deriva de `CampaignStatusEnum`.** Si agregas un caso al enum, hace falta una migración que amplíe la columna: si no, MySQL rechaza el valor. Ya pasó una vez con `draft`, `batched` y `sending`.
- **No declares `$queue` (ni `$connection`, `$delay`) como propiedad tipada en un Job.** Ya vienen del trait `Queueable` y redeclararlas tipadas es un **fatal error** de PHP que deja la clase sin cargar. Usa `$this->onQueue('...')` en el constructor.
- `WhatsappCampaign`/`WhatsappMessage` y `Campaign`/`CampaignMessage` comparten tablas (ver arriba): es la deuda técnica más grande que queda.
- **Carbon 3** (`nesbot/carbon` 3.x): `diffIn*()` devuelve un valor **con signo** y float. `$futuro->diffInMilliseconds(now())` es negativo. Usa `now()->diffInMilliseconds($futuro)` o `abs()`.
- `MySQL` no corre en local por defecto y `php artisan migrate:status` falla con "conexión denegada". Los tests usan sqlite: no detectan problemas de ENUM, y lo que es solo-MySQL (`TIMESTAMPDIFF`, agrupar por un alias ambiguo) revienta en sqlite. Escribe SQL portable. La migración [2026_10_11_000002_sqlite_relax_status_checks.php](database/migrations/2026_10_11_000002_sqlite_relax_status_checks.php) quita en sqlite los CHECK viejos de `status`, para que los tests acepten los mismos valores que MySQL.
- `referidos:volcar-importados` ([VolcarImportadosACommand](app/Console/Commands/VolcarImportadosACommand.php)) y [database/sql/volcar-importados.sql](database/sql/volcar-importados.sql) copian el padrón a `public_registrations` con un referidor **al azar**. Esas filas llevan `origen = 'importado'`; las del formulario, `origen = 'formulario'`. Los volcados anteriores a la columna se marcan a mano con el bloque 0 del SQL. El ranking de referidores del dashboard **excluye** los `importado` (la condición va en el `LEFT JOIN`, para no perder a los referidores con 0); los KPIs totales y las gráficas sí los incluyen.
- Los cálculos del dashboard viven en [DashboardStats](app/Services/DashboardStats.php), que comparten la pantalla ([Stats](app/Livewire/Dashboard/Stats.php)) y el export ([DashboardExportController](app/Http/Controllers/DashboardExportController.php)). No dupliques consultas en uno solo.
