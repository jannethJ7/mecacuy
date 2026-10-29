#pragma once
#include <math.h>
#include <stdint.h>
#include <string.h>

namespace Calefaccion {
struct Configuracion {
  bool presente = false;
  bool calibrado = false;
  bool pinDisponible = false;
  int gpio = -1;
  uint32_t revision = 0;
  float umbralEncendidoMv = 50;
  float umbralApagadoMv = 25;
  float ceroMv = 1667;
  float ceroMinMv = 1300;
  float ceroMaxMv = 2000;
  uint32_t esperaMs = 3000;
  char codigoSensor[41] = {};
  char codigoActuador[41] = {};
};

struct Resultado {
  bool valida = false;
  int estado = -1; // -1 indeterminado; 0 sin consumo; 1 consumo detectado.
  uint32_t revision = 0;
  uint32_t muestreadoMs = 0;
  float mediaMv = 0;
  float eficazMv = 0;
  const char* motivo = "sin_configuracion";
  const char* diagnostico = "sin_verificar";
};

// Núcleo independiente de Arduino para ensayar señales, histéresis y fallas.
class Analizador {
  int estadoConfirmado = -1;
  int candidato = -1;
  unsigned consecutivas = 0;
public:
  void reiniciar() { estadoConfirmado = candidato = -1; consecutivas = 0; }

  Resultado evaluar(const Configuracion& c, double suma, double cuadrados, unsigned cantidad,
                    bool saturada, bool irregular, uint32_t ahoraMs) {
    Resultado r;
    r.revision = c.revision;
    r.muestreadoMs = ahoraMs;
    if (!c.presente || !c.pinDisponible || !c.revision
        || !(c.umbralEncendidoMv > c.umbralApagadoMv) || c.umbralApagadoMv < 0
        || !(c.ceroMaxMv > c.ceroMinMv)) {
      reiniciar(); r.motivo = c.presente && !c.calibrado ? "sin_calibrar" : "configuracion_invalida"; return r;
    }
    if (cantidad < 100 || irregular) { reiniciar(); r.motivo = "ventana_incompleta"; return r; }
    double media = suma / cantidad;
    r.mediaMv = media;
    // Referencia de cero calibrada, no media de la onda: conserva la componente
    // continua si un SSR averiado deja pasar solo una semionda.
    double energia = cuadrados / cantidad - 2.0 * c.ceroMv * media + (double)c.ceroMv * c.ceroMv;
    r.eficazMv = sqrt(energia > 0 ? energia : 0);
    if (saturada || !isfinite(r.eficazMv) || r.mediaMv < c.ceroMinMv || r.mediaMv > c.ceroMaxMv) {
      reiniciar(); r.motivo = "senal_fuera_de_rango"; return r;
    }
    if (!c.calibrado) { reiniciar(); r.motivo = "sin_calibrar"; return r; }
    int propuesto = r.eficazMv >= c.umbralEncendidoMv ? 1
                  : (r.eficazMv <= c.umbralApagadoMv ? 0 : estadoConfirmado);
    if (propuesto < 0) { candidato = -1; consecutivas = 0; r.motivo = "senal_ambigua"; return r; }
    if (propuesto == candidato) ++consecutivas;
    else { candidato = propuesto; consecutivas = 1; }
    if (consecutivas < 3) { r.motivo = "estabilizando"; return r; }
    estadoConfirmado = propuesto;
    r.valida = true; r.estado = propuesto; r.motivo = "ok";
    return r;
  }
};

#ifdef ARDUINO
// La adquisición no depende de solicitudes HTTP ni de que la página esté abierta.
// No acciona un segundo corte: ese hardware no está declarado en el proyecto.
static portMUX_TYPE cerrojo = portMUX_INITIALIZER_UNLOCKED;
static Configuracion configuracion;
static Resultado resultado;
static int ordenAplicada = -1;
static uint32_t ordenCambiadaMs = 0;
static TaskHandle_t tarea = nullptr;

static Configuracion obtenerConfiguracion() {
  portENTER_CRITICAL(&cerrojo); Configuracion c = configuracion; portEXIT_CRITICAL(&cerrojo); return c;
}

static Resultado obtenerResultado() {
  portENTER_CRITICAL(&cerrojo); Resultado r = resultado; portEXIT_CRITICAL(&cerrojo);
  if (!tarea || (uint32_t)(millis() - r.muestreadoMs) > 1500) {
    r.valida = false; r.estado = -1; r.motivo = "muestra_vencida";
  }
  return r;
}

static void configurar(const Configuracion& c) {
  portENTER_CRITICAL(&cerrojo);
  bool cambio = configuracion.revision != c.revision || configuracion.gpio != c.gpio
      || configuracion.calibrado != c.calibrado || configuracion.pinDisponible != c.pinDisponible
      || configuracion.presente != c.presente || strcmp(configuracion.codigoSensor, c.codigoSensor)
      || strcmp(configuracion.codigoActuador, c.codigoActuador);
  configuracion = c;
  if (cambio) { resultado = Resultado(); ordenAplicada = -1; }
  portEXIT_CRITICAL(&cerrojo);
}

static void registrarOrden(const String& codigo, bool encendido) {
  portENTER_CRITICAL(&cerrojo);
  if (codigo == configuracion.codigoActuador && ordenAplicada != (encendido ? 1 : 0)) {
    ordenAplicada = encendido ? 1 : 0; ordenCambiadaMs = millis();
  }
  portEXIT_CRITICAL(&cerrojo);
}

static void adquirir(void*) {
  Analizador analizador;
  uint32_t ultimaRevision = 0;
  int ultimoPin = -1;
  const char* ultimoDiagnostico = "";
  for (;;) {
    Configuracion c = obtenerConfiguracion();
    if (!c.presente || !c.pinDisponible) {
      analizador.reiniciar();
      Resultado r; r.revision = c.revision; r.muestreadoMs = millis();
      r.motivo = !c.presente ? "sin_configuracion" : (!c.calibrado ? "sin_calibrar" : "gpio_en_conflicto");
      portENTER_CRITICAL(&cerrojo); resultado = r; portEXIT_CRITICAL(&cerrojo);
      vTaskDelay(pdMS_TO_TICKS(100)); continue;
    }
    if (ultimaRevision != c.revision || ultimoPin != c.gpio) {
      analizador.reiniciar(); ultimaRevision = c.revision; ultimoPin = c.gpio;
    }
    double suma = 0, cuadrados = 0;
    unsigned cantidad = 0;
    bool saturada = false, irregular = false;
    uint32_t inicio = millis(), ultimoUs = micros();
    while ((uint32_t)(millis() - inicio) < 200) {
      uint32_t ahoraUs = micros();
      if (cantidad && (uint32_t)(ahoraUs - ultimoUs) > 8000) irregular = true;
      ultimoUs = ahoraUs;
      uint32_t mv = analogReadMilliVolts(c.gpio);
      // Señal acondicionada esperada alrededor de 1,67 V. No se conecta a 5 V.
      if (mv < 100 || mv > 3000) saturada = true;
      suma += mv; cuadrados += (double)mv * mv; ++cantidad;
      vTaskDelay(1);
    }
    Resultado r = analizador.evaluar(c, suma, cuadrados, cantidad, saturada, irregular, millis());
    portENTER_CRITICAL(&cerrojo);
    int orden = ordenAplicada;
    uint32_t cambio = ordenCambiadaMs;
    bool mismaConfiguracion = configuracion.revision == c.revision && configuracion.gpio == c.gpio && configuracion.calibrado == c.calibrado;
    portEXIT_CRITICAL(&cerrojo);
    if (!mismaConfiguracion) { analizador.reiniciar(); continue; }
    if (r.valida && orden >= 0 && (uint32_t)(millis() - cambio) >= c.esperaMs) {
      r.diagnostico = !orden && r.estado == 1 ? "no_apaga"
                    : (orden && r.estado == 0 ? "no_enciende" : "coincide");
    }
    if (strcmp(r.diagnostico, ultimoDiagnostico)) {
      Serial.print("Supervision local de calefaccion: "); Serial.println(r.diagnostico);
      ultimoDiagnostico = r.diagnostico;
    }
    portENTER_CRITICAL(&cerrojo); resultado = r; portEXIT_CRITICAL(&cerrojo);
  }
}

static void iniciar() {
  if (!tarea && xTaskCreate(adquirir, "supervision_calef", 4096, nullptr, 1, &tarea) != pdPASS) {
    tarea = nullptr;
    Serial.println("ERROR: no se pudo iniciar la supervision de calefaccion.");
  }
}
#endif
} // namespace Calefaccion
