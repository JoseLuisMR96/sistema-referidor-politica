# Configuración de Webhook para Respuestas de Botones

## 🔧 Paso 1: Verificar la URL del Webhook en Twilio

Debes configurar Twilio para que envíe las respuestas de botones a tu aplicación.

**URL del Webhook (CÓPIALA EXACTAMENTE):**
```
https://metatankpolitic.com/api/twilio/whatsapp/status
```

**Pasos en Twilio Console:**
1. Ir a: **Messaging > Services > [Tu Messaging Service]**
2. Expandir **Webhooks**
3. En **Status Callbacks**, configurar:
   - **URL**: `https://metatankpolitic.com/api/twilio/whatsapp/status`
   - **Method**: `POST`
4. Guardar cambios

## ✅ Paso 2: Verificar bases de datos

Ejecutar en tu servidor:
```bash
php artisan migrate:status
```

Deberías ver en la salida:
- ✅ `migrate  2026_03_16_refactor_campaign_architecture` - **Ran**

Si no está ejecutada, correr:
```bash
php artisan migrate
```

Verificar que existen las tablas nuevo:
```bash
mysql> SHOW TABLES LIKE 'campaign%';
```

Debería mostrar:
- `campaigns` (renombrada de `whatsapp_campaigns`)
- `campaign_messages` (renombrada de `whatsapp_messages`)
- `campaign_batches` (nueva)
- `whatsapp_campaign_responses` (nueva)
- `campaign_metrics` (nueva)

## 🚀 Paso 3: Iniciar Queue Workers

**IMPORTANTE**: Sin workers, los mensajes de agradecimiento NO se enviarán.

En tu servidor de producción, ejecutar en screen/tmux:

```bash
# Terminal 1: Procesar mensajes de WhatsApp
php artisan queue:work --queue=whatsapp --max-jobs=50 --max-time=3600

# Terminal 2 (opcional): Procesar otros jobs
php artisan queue:work --queue=default --max-jobs=20 --max-time=3600
```

O configurar un supervisor para que inicie automáticamente (recomendado para producción).

## 🧪 Paso 4: Probar el Flujo Completo

1. **Enviar template en Livewire:**
   - Ir a campaña composer
   - Aplicar template (content_sid HX...)
   - Enviar a un teléfono de prueba

2. **Verificar que llegó:**
   - El mensaje debe llegar con botones
   - Checar en `campaign_messages` que se creó el registro

3. **Clickear un botón:**
   - Pulsar "Paloma Valencia", "Cepeda" u "Otro Candidato"
   - Esperar 3-5 segundos

4. **Verificar que se capturó:**
   ```sql
   SELECT * FROM whatsapp_campaign_responses WHERE phone = '+1234567890' LIMIT 1;
   ```
   Debería haber un registro con:
   - `button_id`: 'palom', 'cepeda', u 'otro_candidato'
   - `response_timestamp`: tiempo de click
   - `processing_status`: 'pending' (se procesa pronto)

5. **Verificar que llegó el agradecimiento:**
   - El teléfono debe recibir un mensaje como:
   - ✅ ¡Gracias por tu voto! Tu preferencia por Paloma Valencia ha sido registrada. 🕊️
   - Debería haber otro registro en `campaign_messages` con status 'sent'

## 📊 Paso 5: Monitorear en Base de Datos

**Ver respuestas capturadas:**
```sql
SELECT * FROM whatsapp_campaign_responses 
ORDER BY created_at DESC LIMIT 10;
```

**Ver métricas de campaña:**
```sql
SELECT * FROM campaign_metrics 
WHERE campaign_id = 1;
```

**Ver logs de queue:**
```bash
tail -f /path/to/storage/logs/laravel.log | grep -i "thank you\|button\|webhook"
```

## 🐛 Troubleshooting

**Problema: No llega el mensaje de agradecimiento**

Checklist:
1. ✅ ¿Está el worker de queue corriendo? `ps aux | grep queue:work`
2. ✅ ¿Está la respuesta en `whatsapp_campaign_responses`? 
3. ✅ ¿Hay errores en el log de Laravel?
   ```bash
   tail -100 /path/to/storage/logs/laravel.log
   ```
4. ✅ ¿Está configurada la URL de webhook en Twilio?

**Problema: "Tabla whatsapp_campaigns no existe"**

Significa que la migración no se ejecutó correctamente. Ejecutar:
```bash
php artisan migrate
```

Si sigue fallando, revisar el error exacto de la migración en los logs.

**Problema: Las respuestas no se guardan en BD**

Checklist:
1. ✅ ¿La URL del webhook es correcta en Twilio?
2. ✅ ¿Se está llamando al webhook? (Revisar logs)
3. ✅ ¿El mensaje original está en `campaign_messages` con el ID correcto?

## 📝 Notas Importantes

- El webhook se llamará automáticamente después de que presiones un botón
- El mensaje de agradecimiento se envía **vía listener**, no vía job tradicional
- Todas las respuestas van a `whatsapp_campaign_responses` y se deduplicarán por (campaign_id, campaign_message_id, phone, button_id)
- Los metrics se actualizan automáticamente cuando se captura una respuesta
