# Nivel de agua como una sola asignación — firmware 2.3.0

La web configura un único sensor por módulo: `S_NIVEL`, tipo **Nivel de agua del tanque**, unidad `%`. No se configura un GPIO por electrodo. El firmware conserva el circuito y la lectura del archivo de referencia `clarivel/clavel/clavel.ino`; ese archivo no se modifica.

## Circuito y lectura

| Nivel | Entrada fija del ESP32 |
|---|---|
| 25 % | GPIO 23 |
| 50 % | GPIO 27 |
| 75 % | GPIO 34 |
| 100 % | GPIO 35 |

El circuito externo entrega HIGH cuando hay agua y LOW cuando está seco. Se utiliza `INPUT`, igual que en el código de referencia; GPIO 34 y 35 requieren la polarización externa del circuito, pues no tienen resistencias internas de pull-up/pull-down. Conservar el acondicionamiento eléctrico del circuito probado.

Se toma el nivel activo más alto. Ninguna entrada activa significa 0 %. Se publican solo 0, 25, 50, 75 o 100 %. El filtro toma 15 muestras separadas por 10 ms y conserva el resultado más frecuente; los empates favorecen el menor porcentaje. Una tarea independiente evita bloquear el motor dosificador durante el muestreo.

Los pines del conjunto quedan reservados y no pueden asignarse a otro sensor o actuador activo. GPIO 33 continúa disponible para la supervisión ACS712 de calefacción. Un conflicto de configuración invalida la lectura; no equivale a tanque vacío. El llenado se rechaza o se detiene cuando no hay una lectura verificada y reciente.

## Configuración en la página

En Sensores, crear un sensor, seleccionar el módulo y el tipo **Nivel de agua del tanque**. Indicar su nombre y guardar. El código, la unidad y el perfil se establecen automáticamente; se ocultan los campos GPIO, código y unidad. Se conserva una sola fila para el nivel del tanque.

Para convertir una instalación con los cuatro sensores antiguos, ejecutar desde la carpeta Laravel:

```sh
php artisan mecacuy:agua-configurar MOD-001
php artisan view:clear
```

El comando guarda un respaldo JSON de la configuración en `storage/app/private/respaldos/` (o en la raíz local configurada), crea el conjunto y archiva los sensores individuales sin borrar sus lecturas. No envía comandos a los actuadores. No requiere migración de esquema.

Las reglas automáticas binarias inequívocas, sin histéresis, se convierten al porcentaje equivalente. Por ejemplo, falta de agua en el electrodo del 50 % pasa a `S_NIVEL < 50`. Las reglas ambiguas y las alertas configuradas sobre entradas antiguas se desactivan para revisión; el comando informa los identificadores de reglas automáticas pendientes. Las alertas históricas se conservan.

## Puesta en marcha

1. Respaldar y actualizar el proyecto Laravel en el servidor. Ejecutar el comando de configuración y revisar su resumen.
2. Abrir `firmware_esp32/firmware_mecacuy_espanol/firmware_mecacuy_espanol.ino` junto con `NivelAgua.h` y `SupervisionCalefaccion.h`. Completar las credenciales privadas y cargar al ESP32.
3. Confirmar la sincronización del sensor `S_NIVEL` y probar físicamente vacío, 25, 50, 75 y 100 %, así como la desconexión del nodo. Verificar llenado y corte con el circuito real.
4. Comprobar las reglas de automatización y crear las alertas requeridas sobre el nuevo sensor en porcentaje.

Actualizar servidor y firmware en la misma puesta en marcha: el firmware anterior no produce la lectura agregada con este perfil. Las pruebas automatizadas y la compilación no sustituyen la validación física del circuito. La supervisión ACS712 existente conserva su configuración y calibración independiente.
