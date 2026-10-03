# Video MecaCuy con MediaMTX

## Objetivo
Cada cámara se asocia a un `Modulo` de Laravel mediante un `stream_key`. MediaMTX recibe o lee el video y lo expone en HLS/WebRTC.

## Primera prueba: MOD-001
1. En `mediamtx.yml`, configura el path `mod-001`.
2. Si la cámara entrega RTSP, reemplaza `source: publisher` por su URL RTSP y habilita `sourceOnDemand: yes`.
3. Inicia MediaMTX con `docker compose up -d` dentro de esta carpeta.
4. En Laravel configura:
   - `MEDIAMTX_HLS_PUBLIC_BASE=http://IP_DEL_SERVIDOR:8888`
   - `MEDIAMTX_WEBRTC_PUBLIC_BASE=http://IP_DEL_SERVIDOR:8889`
5. Ejecuta la migración y registra una cámara con `stream_key=mod-001`.

## Separación de responsabilidades
- Laravel: usuarios, permisos, relación cámara-módulo y UI.
- MediaMTX: transporte de video.
- MQTT (siguiente fase): telemetría, comandos, ACK, estado y eventos; no video.
