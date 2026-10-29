# Supervisión eléctrica de calefacción con ACS712

## Alcance implementado

Firmware 2.2.0 y Laravel distinguen la orden del calefactor y su consumo detectado.
La interfaz muestra estados, no amperios. Se utiliza la misma recepción de telemetría
para MQTT y REST. No se modifica el funcionamiento de los demás sensores o actuadores.

El calefactor indicado por el usuario es CA-200W, 200 W a 220 V AC; su corriente
nominal estimada es 0,91 A. Esta referencia no sustituye la medición del equipo real.

La medición verifica consumo eléctrico detectable. No certifica ausencia de tensión,
temperatura segura ni protección integral. Un sensor desconectado o averiado que
produzca una señal plausible puede ser indistinguible de una carga apagada. Se requiere
validación física y protección térmica independiente.

## Entrada y firmware

- Código de sensor propuesto: `S_CONF_CALEF`.
- Tipo: `verificacion_calefaccion`.
- Driver enviado por sincronización: `acs712_ac`.
- Entrada propuesta: GPIO 33, ADC1_CH5 del ESP32 clásico.
- Asociación explícita al código real del calefactor, por ejemplo `D_CALEF` o `D_HEAT`.
- Un sensor ACS712 de calefacción por nodo en esta versión.

El comando y el formulario rechazan un pin ya asignado en el mismo módulo. La
comprobación de base de datos debe complementarse con el esquema y la PCB reales.
La salida del ACS712 se acondiciona al rango del ADC; no se conecta 220 V al ESP32.
El ACS712 se alimenta a 5 V y su salida analógica no debe asumirse compatible directamente.
No se utiliza la entrada digital con PC817 para esta señal analógica.

La revisión del aislamiento, borneras, gabinete y circuito de red corresponde a una
persona capacitada. La especificación de aislamiento del integrado no certifica la
placa comercial completa.

La adquisición se ejecuta en una tarea FreeRTOS independiente de HTTP, con ventanas
de unos 200 ms. Calcula el valor eficaz de la señal respecto del cero calibrado y
requiere tres ventanas consecutivas para confirmar una transición. La referencia
fija conserva la señal de una posible conducción de media onda; no se recalibra el
cero automáticamente mientras el calefactor podría estar encendido.

Se rechazan saturación, señal fuera del intervalo medio admitido, ventana incompleta,
muestreo irregular, pin compartido, configuración inválida y muestra vencida. El
firmware emite diagnóstico local por serie y publica el estado por MQTT o respaldo
REST. No hay una salida de corte independiente configurada: no se atribuye al código
la capacidad de cortar un SSR físicamente averiado.

## Instalación en la plataforma

1. Subir los cambios de código al repositorio y desplegar en Coolify como habitualmente.
2. No hay migraciones nuevas; se utilizan `sensores`, `lecturas`, `reglas_alerta` y `alertas`.
3. Desde la carpeta de la aplicación ejecutar, usando el código real del calefactor:

```bash
php artisan mecacuy:calefaccion-configurar MOD-001 --actuador=D_CALEF --gpio=33
php artisan optimize:clear
```

El comando crea el sensor pendiente de calibración y no enciende ni apaga el calefactor.
Si el sensor ya existe, conserva sus parámetros. Si el pin está ocupado, no cambia la
configuración. Alternativamente, registrar el sensor desde Sensores con el tipo
Verificación calefacción y seleccionar el actuador del mismo módulo.

4. Abrir `firmware_esp32/firmware_mecacuy_espanol/firmware_mecacuy_espanol.ino` en Arduino IDE.
   Conservar `SupervisionCalefaccion.h` en la misma carpeta. Completar en privado las
   credenciales Wi-Fi, API y MQTT que ya utiliza el dispositivo.
5. Seleccionar la placa ESP32 clásica correspondiente, compilar y cargar. Las pruebas
   de compilación se hicieron con ESP32 Dev Module y núcleo Espressif 3.0.5.
6. Verificar que la sincronización incluya `S_CONF_CALEF`, GPIO, driver y metadatos.

La copia `firmware_mqtt_hibrido.ino` conserva los nombres previos; ambos programas
incluyen la misma función. La versión en español es la recomendada para continuar el
proyecto. No es necesario cargar ambas.

## Calibración antes de habilitar confirmaciones

Por defecto `calibrado=false`: la web no declara encendido o apagado confirmado.
Los valores iniciales de umbral son referencias de puesta en marcha, no resultados
de ensayo. El acondicionamiento y el sensor real determinan los parámetros finales.

1. Revisar el montaje y contrastar la corriente real con un instrumento adecuado.
2. Para observar la señal de baja tensión en el monitor serie, cambiar temporalmente
   `DEPURAR_CALIBRACION_CALEFACCION` a `true` en el archivo con nombres anteriores, o
   la misma constante en la versión en español. No se muestran amperios en la web.
3. Con la carga físicamente sin consumo, registrar el nivel medio en mV y colocarlo
   en Referencia medida sin consumo. No obtener este cero con la resistencia encendida.
4. Registrar ruido y señal con el calefactor apagado y encendido. Ajustar los umbrales
   RMS de consumo y ausencia de consumo, manteniendo encendido mayor que apagado y
   margen respecto del ruido. Ajustar el intervalo de nivel medio válido.
5. Ensayar varias transiciones; comprobar que el sensor reconoce los estados y
   rechaza entradas fuera de rango. Las pruebas de fallas de red deben hacerse con
   un montaje controlado, sin provocar cortocircuitos ni anular protecciones.
6. En Editar sensor marcar Sensor instalado y calibrado = Sí y guardar. Cada guardado
   crea una revisión nueva; las lecturas de una revisión anterior dejan de confirmar.
7. Desactivar la depuración de calibración cuando termine la puesta en marcha.

La confirmación requiere hora de adquisición válida. Si el ESP32 todavía no sincronizó
NTP, la plataforma conserva el estado sin verificar. Revisar hora y conectividad.

## Estados de la web y alertas

| Orden | Consumo medido | Resultado |
|---|---|---|
| ON | Detectado | Encendido eléctrico verificado |
| OFF | No detectable | Apagado eléctrico verificado |
| OFF | Detectado | Falla de apagado y alerta crítica |
| ON | No detectable | Falla de encendido y advertencia |
| Cualquiera | Inválido o vencido | Sin verificación y advertencia |

Después de una orden se espera una medición posterior al plazo configurado, por
defecto 3 s. La telemetría conserva el intervalo general del firmware, de 10 s;
la web consulta la verificación cada 5 s. No es una confirmación instantánea.
La vigencia predeterminada es de 30 s. Con pérdida de red o falla de actualización,
la pantalla deja de mostrar una confirmación antigua.

Las reglas se crean automáticamente cuando se evalúa la telemetría del sensor. Se
evitan alertas duplicadas abiertas. La alerta crítica de falla de apagado solo se
cierra automáticamente con apagado eléctrico verificado; cambiar la orden a ON o
perder el sensor no demuestra que se resolvió.

Debe mantenerse activo `php artisan schedule:run` cada minuto en el servidor para
detectar pérdida de datos aunque deje de llegar telemetría. Se añadió la tarea
`mecacuy:supervision-evaluar`; puede ejecutarse manualmente para comprobarla.
La detección programada de dato vencido depende de ese intervalo de evaluación.

## Pruebas pendientes de hardware

La compilación y los ensayos automatizados no calibran el sensor real. Documentar:
referencia de cero, señal con carga, ruido, tiempos de detección, funcionamiento del
corte independiente y comportamiento ante desconexión de señal y de Internet.
El limitador térmico y el dispositivo de corte ante falla del SSR son independientes
de la web y de esta función de supervisión. No se agregan ni se presuponen instalados.
