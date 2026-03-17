# 🚀 IMPLEMENTACIÓN DE ARQUITECTURA REFACTORIZADA

## Status: EN PROGRESO ✅

He creado la estructura base de la nueva arquitectura. Aquí está lo que se ha hecho:

### ✅ COMPLETADO

#### 1. **Migración Principal** (`database/migrations/2026_03_16_refactor_campaign_architecture.php`)
- Renombra `whatsapp_messages` → `campaign_messages`
- Renombra `whatsapp_campaigns` → `campaigns`
- Crea tabla `campaign_batches` (para control de velocidad)
- Crea tabla `whatsapp_campaign_responses` (para capturar botones)
- Crea tabla `campaign_metrics` (métricas denormalizadas)
- Agrega relaciones con referrers y pregoneros

**Ejecutar:**
```bash
php artisan migrate
```

#### 2. **Enums** (Tipado seguro)
- `app/Enums/ButtonIdEnum.php` - Botones: palom, cepeda, otro_candidato
- `app/Enums/CampaignStatusEnum.php` - Estados de campaña: draft, queued, batched, sending, paused, completed, failed, cancelled

#### 3. **DTOs** (Contrato de datos)
- `app/DTOs/CreateCampaignDTO.php` - Para crear campañas
- `app/DTOs/ButtonResponseDTO.php` - Para capturar respuestas

#### 4. **Modelos** (Eloquent refactorizado)
- `app/Models/Campaign.php` - Campaña central (relaciones + scopes + métodos)
- `app/Models/CampaignMessage.php` - Mensaje individual con trazabilidad
- `app/Models/CampaignBatch.php` - Lotes de control de velocidad
- `app/Models/WhatsAppCampaignResponse.php` - Respuestas de botones
- `app/Models/CampaignMetrics.php` - Métricas denormalizadas

#### 5. **Form Requests** (Validación declarativa)
- `app/Http/Requests/CreateCampaignRequest.php` - Validación segura de entrada

#### 6. **Services** (Orquestación)
- `app/Services/CampaignRateLimiter.php` - Control de velocidad (20-40 req/s)

#### 7. **Actions** (Lógica de negocio reutilizable)
- `app/Actions/Campaign/CreateCampaignAction.php` - Crear campaña con todo el flujo
- `app/Actions/WhatsApp/CaptureButtonResponseAction.php` - Capturar respuestas con deduplicación
- `app/Actions/WhatsApp/UpdateMessageStatusAction.php` - Actualizar estado desde webhook

#### 8. **Jobs/Queues** (Procesamiento asincrónico)
- `app/Jobs/ProcessCampaignBatchJob.php` - Procesa lote respetando rate limiting
- `app/Jobs/SendCampaignMessageJob.php` - Envía mensaje individual vía Twilio

#### 9. **Events & Listeners** (Desacoplamiento)
- `app/Events/ButtonResponseCaptured.php` - Evento cuando se captura botón
- `app/Listeners/UpdateCampaignMetricsOnResponse.php` - Listener para actualizar métricas
- `app/Listeners/LogButtonResponse.php` - Listener para auditoría

#### 10. **Controllers** (API refactorizada)
- `app/Http/Controllers/Campaign/CampaignController.php` - Gestión de campañas
- `app/Http/Controllers/WhatsApp/WebhookController.php` - Manejo de webhooks

#### 11. **Event Service Provider**
- `app/Providers/EventServiceProvider.php` - Registro de eventos/listeners

#### 12. **Rutas** (API endpoints)
- `routes/campaigns-api.php` - Endpoints para campañas (nuevos)

---

## 📋 PRÓXIMAS PASOS (TODO List)

### 1. **Validar y actualizar modelos existentes** ⚠️
Necesita:
- Verificar que `Referrer` y `ReferidorPregonero` tengan las FK correctas
- Verificar tabla `whatsapp_campaigns` y actualizar referencias

```bash
# Antes de migrar, verifica que las tablas existan
php artisan tinker
> \DB::select('SHOW TABLES')
```

### 2. **Actualizar rutas** (routes/api.php)
Agregar al final:
```php
require base_path('routes/campaigns-api.php');
```

### 3. **Actualizar rutas web** (routes/web.php)
Los Livewire components antiguos deben refactorizarse gradualmente:
- Mantener `CampaignComposer` pero hacerlo UID que solo usa las nuevas Actions

### 4. **Configurar Queue para Redis** (si quieres velocidad)
En `.env`:
```
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
```

En `config/queue.php`, descomenta y configura Redis.

### 5. **Crear migration para actualizar FK** ⚠️
```php
// database/migrations/2026_03_16_fix_foreign_keys.php
Schema::table('campaigns', function (Blueprint $table) {
    if (Schema::hasColumn('campaigns', 'messaging_service_sid')) {
        // Copiar datos de whatsapp_campaigns si renombraste la tabla
    }
});
```

### 6. **Crear Livewire Component refactorizado**
El `CampaignComposer` existente debe ser actualizado para usar `CreateCampaignAction` en lugar de crear la lógica directamente.

### 7. **Escribir Tests** ✅ (Plantilla lista)
```bash
# Unit Tests
php artisan make:test CampaignRateLimiterTest --unit

# Feature Tests
php artisan make:test CreateCampaignTest --feature
```

### 8. **Configurar Logging**
En `config/logging.php`, agregar canal para campañas:
```php
'channels' => [
    'campaigns' => [
        'driver' => 'single',
        'path' => storage_path('logs/campaigns.log'),
        'level' => 'debug',
    ],
]
```

### 9. **Registrar el Provider** (if using Laravel 8)
En `config/app.php`, providers:
```php
'providers' => [
    // ...
    \App\Providers\EventServiceProvider::class,
]
```

### 10. **Migrations que necesitas ejecutar**

```bash
# 1. Ejecutar la migración principal
php artisan migrate --path=database/migrations/2026_03_16_refactor_campaign_architecture.php

# 2. Verificar que todo se creó
php artisan tinker
> \App\Models\Campaign::count()
> \App\Models\CampaignResponse::count()
```

---

## 🔧 CONFIGURACIÓN NECESARIA

### En `.env`:
```env
# Queue
QUEUE_CONNECTION=redis  # o database si no tienes Redis
REDIS_QUEUE_CONNECTION=default

# Logging
LOG_CHANNEL=stack

# Twilio
TWILIO_ACCOUNT_SID=xxxxx
TWILIO_AUTH_TOKEN=xxxxx
TWILIO_MESSAGING_SERVICE_SID=MGxxxxx
```

### Cola de ejecución recomendada:

```bash
# Terminal 1: Worker para campañas
php artisan queue:work --queue=campaigns --max-jobs=10 --max-time=3600

# Terminal 2: Worker para mensajes
php artisan queue:work --queue=messages --max-jobs=50 --max-time=3600
```

---

## 📊 EJEMPLO DE USO

### Crear campaña vía API:
```bash
curl -X POST http://localhost:8000/api/campaigns \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "name": "Campaña Test",
    "type": "text",
    "body": "Hola {name}, vote por palom",
    "source": "twilio",
    "recipients": [
      {"phone": "573001234567", "name": "Juan"},
      {"phone": "573009876543", "name": "María"}
    ]
  }'
```

### Obtener stats:
```bash
curl http://localhost:8000/api/campaigns/1/stats \
  -H "Authorization: Bearer TOKEN"
```

### Pausar campaña:
```bash
curl -X POST http://localhost:8000/api/campaigns/1/pause \
  -H "Authorization: Bearer TOKEN"
```

---

## 🧪 TESTING

### Test unitario para rate limiting:
```php
test('rate limiter respects 20 rps', function () {
    $limiter = new \App\Services\CampaignRateLimiter(20);
    $campaign = Campaign::factory()->create();
    
    $delay1 = $limiter->recordSend($campaign);
    $delay2 = $limiter->recordSend($campaign);
    
    expect($delay2)->toBeGreaterThan(0);  // Debe haber delay
});
```

### Test de acción:
```php
test('create campaign action creates batches', function () {
    $action = new CreateCampaignAction(new CampaignRateLimiter(20));
    
    $dto = new CreateCampaignDTO(
        name: 'Test Campaign',
        type: 'text',
        body: 'Test',
        recipients: [
            ['phone' => '573001234567', 'name' => 'Test'],
        ],
    );
    
    $campaign = $action->execute($dto);
    
    expect($campaign->batches()->count())->toBeGreaterThan(0);
});
```

---

## ⚠️ CONSIDERACIONES IMPORTANTES

### 1. **Migración de datos existentes**
Las tablas antiguas (`whatsapp_messages`, `whatsapp_campaigns`) serán renombradas. Si tienes datos, necesitarás:

```php
// Después de migrar
Schema::table('campaign_messages', function ($table) {
    if (Schema::hasIndex('campaign_messages', 'old_index_name')) {
        // Reconstruir índices
    }
});
```

### 2. **Compatibilidad con código antiguo**
Los modelos antiguos (`WhatsappCampaign`, `WhatsappMessage`) se reemplazan por `Campaign` y `CampaignMessage`. Necesitarás:
- Actualizar imports en Livewire components
- Crear aliases si quieres mantener compatibilidad temporal

### 3. **Webhooks**
La nueva URL para webhooks es: `/api/whatsapp/webhook/status`

Actualiza en Twilio Dashboard y en tu Job:
```php
'statusCallback' => url('/api/whatsapp/webhook/status'),
```

### 4. **Rate Limiting recomendado**
- **Conservador**: 10 req/s (100ms delay)
- **Recomendado**: 20 req/s (50ms delay) ← DEFAULT
- **Agresivo**: 40 req/s (25ms delay)

Twilio típicamente soporta 100+ req/s, pero mejor ser cauteloso.

---

## 📞 SIGUIENTE PASOS INMEDIATOS

1. ✅ Ejecutar migración
2. ✅ Actualizar rutas (agregar campaigns-api.php a api.php)
3. ✅ Actualizar EventServiceProvider en config/app.php
4. ✅ Refactorizar CampaignComposer para usar CreateCampaignAction
5. ✅ Crear tests básicos
6. ✅ Actualizar webhooks en Twilio

¿Quieres que proceda con alguno de estos pasos?
