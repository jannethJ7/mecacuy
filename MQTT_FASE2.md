# Fase 2 - Comunicación híbrida REST + MQTT

## Objetivo

MecaCuy conserva la API REST existente y añade MQTT como canal primario en tiempo real. No se eliminó `/sync`, `/lecturas` ni `/ack`.

## Flujo implementado

```text
ESP32 --telemetry/status/ack--> EMQX --Webhook--> Laravel
ESP32 <--command--------------- EMQX <--REST API-- Laravel

                 si MQTT falla
ESP32 <---------------- REST API ----------------> Laravel
```

## Backend Laravel

Se añadieron:

- `config/mqtt.php`
- `MqttBridgeService`
- `ProcesadorTelemetriaIot`
- `ProcesadorAckIot`
- `MqttWebhookController`
- `POST /api/mqtt/v1/webhook`

REST y MQTT comparten los mismos procesadores de telemetría y ACK, por lo que reglas, alertas y estados siguen la misma lógica.

## Despacho de comandos

Los comandos manuales, de reglas y de programaciones intentan publicarse por MQTT después de quedar confirmados en base de datos. Solo se marca `enviado` si EMQX confirma un suscriptor. Si MQTT no está disponible, permanece `pendiente` y el ESP32 lo obtiene por `/sync`.

## Firmware piloto

Usar `firmware_mqtt_hibrido.ino`.

Incluye:

- conexión/reconexión MQTT con `espMqttClient`;
- suscripción a `command`;
- `telemetry`, `ack` y `status`;
- LWT `offline`;
- status retained `online`;
- fallback REST;
- deduplicación por `nonce` persistida en NVS;
- corrección del NEMA continuo;
- corrección del timeout de llenado de agua;
- inicialización física OFF de salidas nuevas antes de aplicar el estado deseado.

## Primera prueba recomendada

1. Mantener `MQTT_ENABLED=false` y verificar que REST funciona exactamente como antes.
2. Levantar EMQX.
3. Crear usuario MQTT `mod001`.
4. Configurar Webhook y API Key de EMQX.
5. Poner `MQTT_ENABLED=true` en Laravel y firmware.
6. Encender MOD-001 y confirmar `status=online`.
7. Observar `telemetry` en EMQX.
8. Accionar primero `D_FAN` desde el panel.
9. Verificar `command -> ACK -> estado_reportado`.
10. Apagar EMQX y repetir D_FAN: debe seguir funcionando por REST `/sync`.

No probar calefacción, agua ni dosificador hasta confirmar primero el flujo con una salida de bajo riesgo como el ventilador/LED de prueba.

> Durante esta fase, el ACK se envía por ambos canales (MQTT y REST). El backend lo procesa de forma idempotente por `nonce`; esto permite confirmar un comando aunque el Webhook MQTT esté mal configurado temporalmente.
