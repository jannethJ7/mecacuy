# MecaCuy - Fase 1: cámaras

## Implementado
- Tabla `camaras` vinculada a `modulos`.
- Modelo `App\\Models\\Camara` y relación `Modulo::camaras()`.
- CRUD protegido por roles:
  - admin: crear, editar, eliminar y visualizar.
  - operador/lector: visualizar.
- Pantalla de cámaras y reproductor individual HLS.
- Enlace opcional a WebRTC.
- Configuración central en `config/streaming.php`.
- MediaMTX desacoplado de Laravel en `streaming/`.
- RTSP no se almacena en la base de datos de Laravel.

## Para probar MOD-001
1. Ejecutar `php artisan migrate`.
2. Configurar en `.env`:
   - `MEDIAMTX_HLS_PUBLIC_BASE=http://IP_SERVIDOR:8888`
   - `MEDIAMTX_WEBRTC_PUBLIC_BASE=http://IP_SERVIDOR:8889`
3. Editar `streaming/mediamtx.yml` y colocar la URL RTSP en `paths.mod-001.source`, o mantener `source: publisher` si otro equipo publica el stream.
4. Ejecutar `docker compose up -d` dentro de `streaming/`.
5. En MecaCuy: Cámaras > Nueva cámara.
6. Seleccionar MOD-001 y usar `stream_key=mod-001`.

## Siguiente fase
Agregar MQTT en paralelo con REST para telemetría, comandos, ACK y presencia/LWT. El video seguirá en MediaMTX.
