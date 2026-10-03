<?php

return [
    'enabled' => env('MQTT_ENABLED', false),
    'topic_prefix' => trim(env('MQTT_TOPIC_PREFIX', 'mecacuy'), '/'),

    // Credenciales usadas por los ESP32 para conectarse al broker MQTT.
    'broker_host' => env('MQTT_BROKER_HOST', '127.0.0.1'),
    'broker_port' => (int) env('MQTT_BROKER_PORT', 1883),
    'broker_tls' => env('MQTT_BROKER_TLS', false),
    'device_username' => env('MQTT_DEVICE_USERNAME'),
    'device_password' => env('MQTT_DEVICE_PASSWORD'),

    // Laravel publica por la API de administración de EMQX para no requerir
    // un cliente MQTT residente dentro del proceso web de Laravel.
    'emqx_api_base' => rtrim(env('EMQX_API_BASE', 'http://127.0.0.1:18083/api/v5'), '/'),
    'emqx_api_key' => env('EMQX_API_KEY'),
    'emqx_api_secret' => env('EMQX_API_SECRET'),
    'http_timeout' => (int) env('EMQX_HTTP_TIMEOUT', 4),

    // EMQX reenvía telemetry/ack/status a Laravel por Webhook.
    'webhook_secret' => env('MQTT_WEBHOOK_SECRET'),
];
