#pragma once
#include <stdint.h>

namespace Tanque {
static const int PIN_25 = 23;
static const int PIN_50 = 27;
static const int PIN_75 = 34;
static const int PIN_100 = 35;

static bool reservado(int gpio) { return gpio == PIN_25 || gpio == PIN_50 || gpio == PIN_75 || gpio == PIN_100; }
static unsigned porcentaje(bool nivel25, bool nivel50, bool nivel75, bool nivel100) {
  return nivel100 ? 100 : (nivel75 ? 75 : (nivel50 ? 50 : (nivel25 ? 25 : 0)));
}

// Misma votación que clavel.ino: 15 muestras, empate a favor del nivel menor.
class Filtro {
  unsigned cuentas[5] = {};
  unsigned cantidad = 0;
public:
  void reiniciar() { for (unsigned i=0;i<5;++i) cuentas[i]=0; cantidad=0; }
  bool agregar(unsigned nivel, unsigned& resultado) {
    if (nivel > 100 || nivel % 25) { reiniciar(); return false; }
    ++cuentas[nivel/25];
    if (++cantidad < 15) return false;
    unsigned mejor=0;
    for (unsigned i=1;i<5;++i) if (cuentas[i]>cuentas[mejor]) mejor=i;
    resultado=mejor*25;
    reiniciar(); return true;
  }
};

#ifdef ARDUINO
struct Lectura { bool valida=false; unsigned nivel=0; uint32_t tomadaMs=0; };
static portMUX_TYPE cerrojo=portMUX_INITIALIZER_UNLOCKED;
static bool habilitado=false;
static bool reserva=false;
static uint32_t generacion=0;
static Lectura lectura;
static TaskHandle_t tarea=nullptr;

static void configurar(bool solicitado, bool activo) {
  portENTER_CRITICAL(&cerrojo);
  if (activo != habilitado) { ++generacion; lectura=Lectura(); }
  habilitado=activo;
  reserva=solicitado;
  portEXIT_CRITICAL(&cerrojo);
}
static bool ocupado(int gpio) {
  portENTER_CRITICAL(&cerrojo); bool r=reserva; portEXIT_CRITICAL(&cerrojo); return r && reservado(gpio);
}
static bool activo() {
  portENTER_CRITICAL(&cerrojo); bool r=habilitado; portEXIT_CRITICAL(&cerrojo); return r;
}
static Lectura obtener() {
  portENTER_CRITICAL(&cerrojo); Lectura r=lectura; bool activo=habilitado; portEXIT_CRITICAL(&cerrojo);
  if (!activo || !tarea || (uint32_t)(millis()-r.tomadaMs)>2000) r.valida=false;
  return r;
}
static void adquirir(void*) {
  Filtro filtro;
  uint32_t anterior=0;
  for (;;) {
    portENTER_CRITICAL(&cerrojo); bool seguir=habilitado; uint32_t version=generacion; portEXIT_CRITICAL(&cerrojo);
    if (!seguir) { filtro.reiniciar(); vTaskDelay(pdMS_TO_TICKS(10)); continue; }
    if (version!=anterior) { filtro.reiniciar(); anterior=version; }
    unsigned nivel=porcentaje(digitalRead(PIN_25)==HIGH,digitalRead(PIN_50)==HIGH,
                             digitalRead(PIN_75)==HIGH,digitalRead(PIN_100)==HIGH);
    unsigned estable=0;
    if (filtro.agregar(nivel,estable)) {
      portENTER_CRITICAL(&cerrojo);
      if (habilitado && generacion==version) { lectura.valida=true; lectura.nivel=estable; lectura.tomadaMs=millis(); }
      portEXIT_CRITICAL(&cerrojo);
    }
    vTaskDelay(pdMS_TO_TICKS(10));
  }
}
static void iniciar() {
  if (!tarea && xTaskCreate(adquirir,"nivel_agua",3072,nullptr,1,&tarea)!=pdPASS) {
    tarea=nullptr; Serial.println("ERROR: no se pudo iniciar la lectura del tanque.");
  }
}
#endif
} // namespace Tanque
