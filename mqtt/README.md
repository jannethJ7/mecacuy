# MecaCuy - Broker MQTT EMQX

Esta carpeta levanta el broker usado por la Fase 2 híbrida REST + MQTT.

## 1. Arranque local

```bash
cp .env.example .env
# Cambiar EMQX_DASHBOARD_PASSWORD
docker compose up -d
```

Dashboard: `http://IP_DEL_SERVIDOR:18083`

La imagen fijada es EMQX Enterprise 6.3.0. Para pruebas locales se expone 1883; en producción usar TLS/8883, autenticación y ACL.

## 2. Autenticación de dispositivos

En EMQX Dashboard:

1. `Access Control -> Authentication`.
2. Crear autenticación `Password-Based -> Built-in Database`.
3. Usar `username` como UserID Type.
4. Crear un usuario para la jaula piloto, por ejemplo `mod001`.
5. Colocar las mismas credenciales en `firmware_mqtt_hibrido.ino`.

Después conviene crear un usuario distinto por módulo: `mod001`, `mod002`, etc., y limitar con ACL cada usuario a su propio prefijo.

## 3. API de EMQX para Laravel

Laravel NO necesita mantener una conexión MQTT residente. Para publicar comandos usa la API de administración de EMQX.

Crea un API Key/Secret en EMQX y configura en Laravel:

```env
MQTT_ENABLED=true
MQTT_TOPIC_PREFIX=mecacuy
EMQX_API_BASE=http://IP_EMQX:18083/api/v5
EMQX_API_KEY=...
EMQX_API_SECRET=...
MQTT_WEBHOOK_SECRET=UN_SECRETO_LARGO_ALEATORIO
```

Si Laravel y EMQX están dentro de la misma red Docker, usa el nombre interno del servicio en lugar de exponer la API de administración a Internet.

## 4. EMQX -> Laravel por Webhook

Crear un Webhook/HTTP Server Sink que envíe los mensajes de `mecacuy/+/+` a:

```text
https://TU_LARAVEL/api/mqtt/v1/webhook
```

Header obligatorio:

```text
X-MECACUY-MQTT-SECRET: <mismo valor de MQTT_WEBHOOK_SECRET>
```

Body recomendado:

```json
{
  "topic": "${topic}",
  "payload": ${payload},
  "clientid": "${clientid}",
  "qos": ${qos},
  "timestamp": ${timestamp}
}
```

El endpoint acepta `telemetry`, `ack` y `status`. Si recibe `command` por el mismo Webhook, simplemente lo ignora.

## 5. Topics de MOD-001

```text
mecacuy/MOD-001/telemetry   ESP32 -> EMQX -> Laravel
mecacuy/MOD-001/command     Laravel -> EMQX -> ESP32
mecacuy/MOD-001/ack         ESP32 -> EMQX -> Laravel
mecacuy/MOD-001/status      ESP32 -> EMQX -> Laravel (retained + LWT)
```

El `status` usa Last Will and Testament: si el ESP32 pierde la conexión inesperadamente, EMQX publica `offline`.

## 6. Fallback REST

El firmware mantiene:

```text
/api/iot/v1/sync
/api/iot/v1/lecturas
/api/iot/v1/ack
```

Comportamiento:

- MQTT conectado: telemetría y ACK se envían por MQTT.
- MQTT no conectado: se usan los endpoints REST actuales.
- Si Laravel intenta publicar un comando y EMQX informa que no hay suscriptor, el comando queda pendiente y `/sync` lo entrega por REST.
- El `nonce` del comando se guarda en NVS para evitar ejecutar dos veces la misma orden si llega por ambos canales.

## 7. Arduino IDE

Instalar la librería MQTT `espMqttClient` de Bert Melis (la versión revisada para esta fase es 1.7.3).

Después abrir `firmware_mqtt_hibrido.ino`, completar Wi-Fi, `DEVICE_KEY`, host MQTT y credenciales. Cuando el broker ya funcione, cambiar:

```cpp
const bool MQTT_ENABLED = true;
```

Para la primera prueba usar MQTT TCP/1883 dentro de una LAN controlada. La siguiente fase será MQTTS/8883.

> Durante esta fase, el ACK se envía por ambos canales (MQTT y REST). El backend lo procesa de forma idempotente por `nonce`; esto permite confirmar un comando aunque el Webhook MQTT esté mal configurado temporalmente.
