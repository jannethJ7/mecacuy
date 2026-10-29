
#include <WiFi.h>
#include <HTTPClient.h>
#include <espMqttClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <DHT.h>
#include <time.h>
#include <math.h>
#include "SupervisionCalefaccion.h"
#include "NivelAgua.h"

const char* NOMBRE_RED_WIFI = "TU_WIFI";
const char* CLAVE_RED_WIFI = "TU_CLAVE_WIFI";

const char* URL_BASE_SERVIDOR      = "https://claveles.gytelcom.com";
const char* MODULO_UID    = "ESP32-MOD-001";
const char* MODULO_CODIGO = "MOD-001";
const char* CLAVE_DISPOSITIVO    = "TU_CLAVE_DISPOSITIVO_REGENERADA";
const bool DEPURAR_CALIBRACION_CALEFACCION = false;
const char* VERSION_FIRMWARE    = "mecacuy-esp32-hibrido-2.3.0";

const bool MQTT_HABILITADO = true;
const char* SERVIDOR_MQTT = "ddb1331f.ala.eu-central-1.emqxsl.com";
const uint16_t PUERTO_MQTT = 8883;
const char* USUARIO_MQTT = "esp32_mod001";
const char* CLAVE_MQTT = "CAMBIAR_CLAVE_MQTT";
const char* PREFIJO_TEMAS_MQTT = "mecacuy";

String URL_SINCRONIZACION     = String(URL_BASE_SERVIDOR) + "/api/iot/v1/sync";
String URL_LECTURAS = String(URL_BASE_SERVIDOR) + "/api/iot/v1/lecturas";
String URL_CONFIRMACION      = String(URL_BASE_SERVIDOR) + "/api/iot/v1/ack";

// CA publica descargada del despliegue EMQX Cloud.
const char CERTIFICADO_RAIZ_MQTT[] = R"PEM(
-----BEGIN CERTIFICATE-----
MIIDjjCCAnagAwIBAgIQAzrx5qcRqaC7KGSxHQn65TANBgkqhkiG9w0BAQsFADBh
MQswCQYDVQQGEwJVUzEVMBMGA1UEChMMRGlnaUNlcnQgSW5jMRkwFwYDVQQLExB3
d3cuZGlnaWNlcnQuY29tMSAwHgYDVQQDExdEaWdpQ2VydCBHbG9iYWwgUm9vdCBH
MjAeFw0xMzA4MDExMjAwMDBaFw0zODAxMTUxMjAwMDBaMGExCzAJBgNVBAYTAlVT
MRUwEwYDVQQKEwxEaWdpQ2VydCBJbmMxGTAXBgNVBAsTEHd3dy5kaWdpY2VydC5j
b20xIDAeBgNVBAMTF0RpZ2lDZXJ0IEdsb2JhbCBSb290IEcyMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuzfNNNx7a8myaJCtSnX/RrohCgiN9RlUyfuI
2/Ou8jqJkTx65qsGGmvPrC3oXgkkRLpimn7Wo6h+4FR1IAWsULecYxpsMNzaHxmx
1x7e/dfgy5SDN67sH0NO3Xss0r0upS/kqbitOtSZpLYl6ZtrAGCSYP9PIUkY92eQ
q2EGnI/yuum06ZIya7XzV+hdG82MHauVBJVJ8zUtluNJbd134/tJS7SsVQepj5Wz
tCO7TG1F8PapspUwtP1MVYwnSlcUfIKdzXOS0xZKBgyMUNGPHgm+F6HmIcr9g+UQ
vIOlCsRnKPZzFBQ9RnbDhxSJITRNrw9FDKZJobq7nMWxM4MphQIDAQABo0IwQDAP
BgNVHRMBAf8EBTADAQH/MA4GA1UdDwEB/wQEAwIBhjAdBgNVHQ4EFgQUTiJUIBiV
5uNu5g/6+rkS7QYXjzkwDQYJKoZIhvcNAQELBQADggEBAGBnKJRvDkhj6zHd6mcY
1Yl9PMWLSn/pvtsrF9+wX3N3KjITOYFnQoQj8kVnNeyIv/iPsGEMNKSuIEyExtv4
NeF22d+mQrvHRAiGfzZ0JFrabA0UWTW98kndth/Jsw1HKj2ZL7tcu7XUIOGZX1NG
Fdtom/DzMNU+MeKNhJ7jitralj41E6Vf8PlwUHBHQRFXGU7Aj64GxJUTFy8bJZ91
8rGOmaFvE7FBcf6IKshPECBV1/MUReXgRPTqh5Uykw7+U0b6LJ3/iyK5S9kJRaTe
pLiaWN0bfVKfjllDiIGknibVb63dDcY3fe0Dkhvld1927jyNxF1WW6LZZm6zNTfl
MrY=
-----END CERTIFICATE-----
)PEM";

espMqttClientSecure clienteMqtt;
String identificadorClienteMqtt = "";
String mensajeDesconexionMqtt = "";
String bufferRecepcionMqtt = "";
String temaRecibidoMqtt = "";
size_t tamanoMensajeMqtt = 0;
String TEMA_TELEMETRIA = String(PREFIJO_TEMAS_MQTT) + "/" + MODULO_CODIGO + "/telemetry";
String TEMA_COMANDO   = String(PREFIJO_TEMAS_MQTT) + "/" + MODULO_CODIGO + "/command";
String TEMA_CONFIRMACION       = String(PREFIJO_TEMAS_MQTT) + "/" + MODULO_CODIGO + "/ack";
String TEMA_ESTADO    = String(PREFIJO_TEMAS_MQTT) + "/" + MODULO_CODIGO + "/status";
unsigned long ultimoIntentoMqttMs = 0;
const unsigned long REINTENTO_MQTT_MS = 5000;
const int CAPACIDAD_HISTORIAL_COMANDOS = 8;
String historialComandos[CAPACIDAD_HISTORIAL_COMANDOS];
int cantidadComandosGuardados = 0;

const char* SERVIDOR_NTP_PRINCIPAL = "pool.ntp.org";
const char* SERVIDOR_NTP_SECUNDARIO = "time.google.com";
const long DESFASE_HORARIO_SEG = -4 * 3600;
const int AJUSTE_HORARIO_VERANO_SEG = 0;
bool horaSincronizada = false;

#define MAX_SENSORES 16
#define MAX_ACTUADORES 16
const size_t CAPACIDAD_JSON_SINCRONIZACION = 32768;

const unsigned long REINTENTO_WIFI_MS      = 5000;
const unsigned long INTERVALO_SINCRONIZACION_MS   = 2500;
const unsigned long INTERVALO_ENVIO_MS   = 10000;
const unsigned long INTERVALO_LECTURA_MS = 2000;

unsigned long ultimoIntentoWifiMs = 0;
unsigned long ultimaSincronizacionMs = 0;
unsigned long ultimoEnvioMs = 0;
unsigned long ultimaLecturaMs = 0;

struct ConfiguracionSensor {
  String codigo;
  String tipo;
  String unidad;
  String controlador;
  int gpio;
  bool activo;
  int tipoDht;
  float adcMinimo;
  float adcMaximo;
  float valorMinimo;
  float valorMaximo;
  float valor;
  int lecturaOriginal;
};

struct ConfiguracionActuador {
  String codigo;
  String tipo;
  int gpio;
  bool activo;
  bool invertido;
  int estadoActual;
};

ConfiguracionSensor sensores[MAX_SENSORES];
int cantidadSensores = 0;

ConfiguracionActuador actuadores[MAX_ACTUADORES];
int cantidadActuadores = 0;

String modoGlobal = "manual";
Preferences preferencias;

DHT* sensorDht = nullptr;
int pinDht = -1;
int tipoDht = DHT11;
bool dhtPreparado = false;
float ultimaTemperaturaDht = NAN;
float ultimaHumedadDht = NAN;

struct EstadoAlimentacion {
  bool enCurso = false;
  bool temporizado = true;   // verdadero = temporizado; falso = encendido continuo
  String identificadorComando = "";
  String codigoActuadorEnCurso = "";
  unsigned long vencimientoMs = 0;
  unsigned long ultimoPulsoUs = 0;
  bool nivelPulso = false;
  bool modoMotorPaso = false;
};

struct EstadoLlenadoAgua {
  bool enCurso = false;
  String identificadorComando = "";
  String codigoActuadorEnCurso = "";
  int porcentajeObjetivo = 0;
  unsigned long vencimientoMs = 0;
};

EstadoAlimentacion accionAlimentacion;
EstadoLlenadoAgua llenadoAgua;

void ejecutarComando(JsonObject comando);

const unsigned long SEMIPERIODO_MOTOR_PASO_US = 3000;

float limitarValor(float x, float a, float b) {
  if (x < a) return a;
  if (x > b) return b;
  return x;
}

float escalarValor(float x, float entradaMinima, float entradaMaxima, float salidaMinima, float salidaMaxima) {
  if (fabs(entradaMaxima - entradaMinima) < 0.0001f) return salidaMinima;
  return (x - entradaMinima) * (salidaMaxima - salidaMinima) / (entradaMaxima - entradaMinima) + salidaMinima;
}

float obtenerNumeroJson(JsonVariant dato, float valorPredeterminado) {
  if (dato.isNull()) return valorPredeterminado;
  if (dato.is<float>() || dato.is<double>() || dato.is<int>() || dato.is<long>()) return dato.as<float>();
  String s = dato.as<String>();
  if (s.length() == 0) return valorPredeterminado;
  return s.toFloat();
}

String convertirMinusculas(String s) {
  s.toLowerCase();
  return s;
}

bool esGpioValido(int gpio) {
  return gpio >= 0 && gpio <= 39;
}

bool admiteSalida(int gpio) {
  if (!esGpioValido(gpio) || Tanque::ocupado(gpio)) return false;
  if (gpio >= 34 && gpio <= 39) return false;
  if (gpio >= 6 && gpio <= 11) return false;
  return true;
}

String obtenerFechaHora() {
  if (!horaSincronizada) return "";
  struct tm datosHora;
  if (!getLocalTime(&datosHora, 50)) return "";
  char bufferFecha[32];
  strftime(bufferFecha, sizeof(bufferFecha), "%Y-%m-%d %H:%M:%S", &datosHora);
  return String(bufferFecha);
}

ConfiguracionActuador* buscarActuador(const String& codigo) {
  for (int indice = 0; indice < cantidadActuadores; indice++) {
    if (actuadores[indice].codigo == codigo) return &actuadores[indice];
  }
  return nullptr;
}

ConfiguracionSensor* buscarSensor(const String& codigo) {
  for (int indice = 0; indice < cantidadSensores; indice++) {
    if (sensores[indice].codigo == codigo) return &sensores[indice];
  }
  return nullptr;
}

void sincronizarHora() {
  configTime(DESFASE_HORARIO_SEG, AJUSTE_HORARIO_VERANO_SEG, SERVIDOR_NTP_PRINCIPAL, SERVIDOR_NTP_SECUNDARIO);

  struct tm datosHora;
  for (int indice = 0; indice < 20; indice++) {
    if (getLocalTime(&datosHora, 200)) {
      horaSincronizada = true;
      Serial.println("Hora sincronizada por NTP");
      return;
    }
    delay(200);
  }

  horaSincronizada = false;
  Serial.println("NTP no disponible");
}

void mantenerConexionWifi() {
  if (WiFi.status() == WL_CONNECTED) return;

  unsigned long ahoraMs = millis();
  if (ahoraMs - ultimoIntentoWifiMs < REINTENTO_WIFI_MS) return;
  ultimoIntentoWifiMs = ahoraMs;

  Serial.println("Reintentando WiFi...");
  WiFi.disconnect();
  WiFi.begin(NOMBRE_RED_WIFI, CLAVE_RED_WIFI);
}

void agregarCabecerasIot(HTTPClient& solicitudHttp) {
  solicitudHttp.addHeader("Accept", "application/json");
  solicitudHttp.addHeader("X-MODULO-UID", MODULO_UID);
  solicitudHttp.addHeader("X-DEVICE-KEY", CLAVE_DISPOSITIVO);
  solicitudHttp.addHeader("X-FW", VERSION_FIRMWARE);

  if (WiFi.status() == WL_CONNECTED) {
    solicitudHttp.addHeader("X-RSSI", String(WiFi.RSSI()));
  }
}

bool consultarHttp(const String& direccion, String& respuesta) {
  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient solicitudHttp;
  solicitudHttp.setTimeout(5000);
  solicitudHttp.useHTTP10(true);
  solicitudHttp.begin(direccion);
  agregarCabecerasIot(solicitudHttp);

  int codigoRespuesta = solicitudHttp.GET();
  if (codigoRespuesta > 0) respuesta = solicitudHttp.getString();

  Serial.print("Consulta HTTP GET ");
  Serial.print(direccion);
  Serial.print(" -> ");
  Serial.println(codigoRespuesta);

  if (codigoRespuesta > 0) Serial.println(respuesta);

  solicitudHttp.end();
  return codigoRespuesta == 200;
}

bool enviarJsonHttp(const String& direccion, const String& cuerpoMensaje, String& respuesta) {
  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient solicitudHttp;
  solicitudHttp.setTimeout(5000);
  solicitudHttp.useHTTP10(true);
  solicitudHttp.begin(direccion);
  solicitudHttp.addHeader("Content-Type", "application/json");
  agregarCabecerasIot(solicitudHttp);

  int codigoRespuesta = solicitudHttp.POST(cuerpoMensaje);
  if (codigoRespuesta > 0) respuesta = solicitudHttp.getString();

  Serial.print("Envio HTTP POST ");
  Serial.print(direccion);
  Serial.print(" -> ");
  Serial.println(codigoRespuesta);

  if (codigoRespuesta > 0) Serial.println(respuesta);

  solicitudHttp.end();
  return codigoRespuesta == 200 || codigoRespuesta == 201;
}

bool guardarSincronizacionJson(const String& textoJson) {
  preferencias.begin("mecacuy", false);
  bool correcto = preferencias.putString("sync_json", textoJson) > 0;
  preferencias.end();
  return correcto;
}

String cargarSincronizacionJson() {
  preferencias.begin("mecacuy", true);
  String s = preferencias.getString("sync_json", "");
  preferencias.end();
  return s;
}

void cargarHistorialComandos() {
  preferencias.begin("mecacuy", true);
  String lecturaOriginal = preferencias.getString("nonce_hist", "");
  preferencias.end();

  cantidadComandosGuardados = 0;
  int inicio = 0;

  while (inicio < lecturaOriginal.length() && cantidadComandosGuardados < CAPACIDAD_HISTORIAL_COMANDOS) {
    int separador = lecturaOriginal.indexOf('|', inicio);
    String elemento = separador >= 0 ? lecturaOriginal.substring(inicio, separador) : lecturaOriginal.substring(inicio);
    elemento.trim();

    if (elemento.length() > 0) {
      historialComandos[cantidadComandosGuardados++] = elemento;
    }

    if (separador < 0) break;
    inicio = separador + 1;
  }
}

bool comandoYaEjecutado(const String& identificadorComando) {
  for (int indice = 0; indice < cantidadComandosGuardados; indice++) {
    if (historialComandos[indice] == identificadorComando) return true;
  }
  return false;
}

void guardarHistorialComandos() {
  String lecturaOriginal = "";
  for (int indice = 0; indice < cantidadComandosGuardados; indice++) {
    if (indice > 0) lecturaOriginal += "|";
    lecturaOriginal += historialComandos[indice];
  }

  preferencias.begin("mecacuy", false);
  preferencias.putString("nonce_hist", lecturaOriginal);
  preferencias.end();
}

void registrarComandoEjecutado(const String& identificadorComando) {
  if (comandoYaEjecutado(identificadorComando)) return;

  if (cantidadComandosGuardados < CAPACIDAD_HISTORIAL_COMANDOS) {
    historialComandos[cantidadComandosGuardados++] = identificadorComando;
  } else {
    for (int indice = 1; indice < CAPACIDAD_HISTORIAL_COMANDOS; indice++) {
      historialComandos[indice - 1] = historialComandos[indice];
    }
    historialComandos[CAPACIDAD_HISTORIAL_COMANDOS - 1] = identificadorComando;
  }

  guardarHistorialComandos();
}

bool guardarConfiguracionSinComandos(const String& textoJson) {
  DynamicJsonDocument documentoOrigen(CAPACIDAD_JSON_SINCRONIZACION);
  DeserializationError errorJson = deserializeJson(documentoOrigen, textoJson);

  if (errorJson) {
    Serial.print("No se pudo preparar la configuracion guardada: ");
    Serial.println(errorJson.c_str());
    return false;
  }

  DynamicJsonDocument configuracionGuardada(CAPACIDAD_JSON_SINCRONIZACION);
  configuracionGuardada["ok"] = true;
  configuracionGuardada["config"] = documentoOrigen["config"];
  configuracionGuardada["sensores"] = documentoOrigen["sensores"];
  configuracionGuardada["actuadores"] = documentoOrigen["actuadores"];

  String jsonCompacto;
  serializeJson(configuracionGuardada, jsonCompacto);

  Serial.print("Tamano de configuracion guardada=");
  Serial.println(jsonCompacto.length());

  return guardarSincronizacionJson(jsonCompacto);
}

String identificarControlador(const String& codigo, const String& tipo, JsonObject meta) {
  if (!meta.isNull() && meta["driver"].is<const char*>()) {
    return convertirMinusculas(String((const char*)meta["driver"]));
  }

  String textoCodigo = convertirMinusculas(codigo);
  String textoTipo = convertirMinusculas(tipo);

  if (textoTipo == "nivel_agua" && textoCodigo == "s_nivel") return "nivel_agua_fijo";
  if (textoCodigo == "s_temp" || textoCodigo == "s_hr") return "dht";
  if (textoTipo == "verificacion_calefaccion" || textoCodigo == "s_conf_calef") return "acs712_ac";
  if (textoCodigo == "s_air" || textoTipo.indexOf("aire") >= 0) return "adc_ppm";

  if (textoCodigo.indexOf("s_nivel_") >= 0 ||
      textoCodigo.indexOf("nivel_25") >= 0 ||
      textoCodigo.indexOf("nivel_50") >= 0 ||
      textoCodigo.indexOf("nivel_75") >= 0 ||
      textoCodigo.indexOf("nivel_100") >= 0) {
    return "digital_level";
  }

  if (textoTipo.indexOf("nivel") >= 0) return "digital_level";

  return "digital";
}

int interpretarTipoDht(JsonObject meta) {
  String valorTexto = "DHT11";

  if (!meta.isNull()) {
    if (meta["dht_type"].is<const char*>()) valorTexto = String((const char*)meta["dht_type"]);
    if (meta["dht_tipo"].is<const char*>()) valorTexto = String((const char*)meta["dht_tipo"]);
  }

  valorTexto.toUpperCase();

  if (valorTexto == "DHT11") return DHT11;
  return DHT11;
}

void configurarDhtDesdeSensores() {
  int pinSeleccionado = -1;
  int tipoSeleccionado = DHT11;

  for (int indice = 0; indice < cantidadSensores; indice++) {
    if (!sensores[indice].activo) continue;
    if (sensores[indice].controlador != "dht") continue;
    if (!esGpioValido(sensores[indice].gpio)) continue;

    if (pinSeleccionado < 0) {
      pinSeleccionado = sensores[indice].gpio;
      tipoSeleccionado = sensores[indice].tipoDht;
    } else if (pinSeleccionado != sensores[indice].gpio) {
      Serial.println("ADVERTENCIA: S_TEMP y S_HR usan GPIO diferente.");
    }
  }

  if (pinSeleccionado < 0) {
    if (sensorDht != nullptr) {
      delete sensorDht;
      sensorDht = nullptr;
    }
    pinDht = -1;
    dhtPreparado = false;
    return;
  }

  if (sensorDht == nullptr || pinDht != pinSeleccionado || tipoDht != tipoSeleccionado) {
    if (sensorDht != nullptr) {
      delete sensorDht;
      sensorDht = nullptr;
    }

    pinDht = pinSeleccionado;
    tipoDht = tipoSeleccionado;
    sensorDht = new DHT(pinDht, tipoDht);
    sensorDht->begin();
    dhtPreparado = true;
    delay(1200);

    Serial.print("DHT inicializado en GPIO ");
    Serial.print(pinDht);
    Serial.print(" tipo ");
    Serial.println(tipoDht == DHT11 ? "DHT11" : "DHT11");
  }
}

bool interpretarSincronizacionJson(const String& respuestaServidor) {
  DynamicJsonDocument documento(CAPACIDAD_JSON_SINCRONIZACION);
  DeserializationError errorJson = deserializeJson(documento, respuestaServidor);

  if (errorJson) {
    Serial.print("Error al interpretar la sincronizacion: ");
    Serial.println(errorJson.c_str());
    return false;
  }

  JsonObject configuracion = documento["config"];
  modoGlobal = configuracion["modo_global"] | "manual";

  // Perfil probado en clavel.ino: cuatro pines fijos, una sola lectura S_NIVEL.
  bool solicitaTanque = false, perfilTanqueValido = true;
  unsigned cantidadTanques = 0;
  for (JsonObject sensorJson : documento["sensores"].as<JsonArray>()) {
    String tipoSensor = sensorJson["tipo"] | "";
    if (tipoSensor != "nivel_agua" || !(sensorJson["activo"] | true)) continue;
    solicitaTanque = true; ++cantidadTanques;
    String codigoSensor = sensorJson["codigo"] | "";
    String perfilSensor = sensorJson["meta"]["perfil"] | "";
    if (codigoSensor != "S_NIVEL" || perfilSensor != "clavel_4_niveles") perfilTanqueValido = false;
  }
  if (solicitaTanque) {
    for (JsonObject sensorJson : documento["sensores"].as<JsonArray>()) {
      String tipoSensor = sensorJson["tipo"] | "";
      int pinSensor = sensorJson["gpio_pin"].isNull() ? -1 : (int)sensorJson["gpio_pin"];
      if (tipoSensor != "nivel_agua" && (sensorJson["activo"] | true) && Tanque::reservado(pinSensor)) perfilTanqueValido = false;
    }
    for (JsonObject actuadorJson : documento["actuadores"].as<JsonArray>()) {
      int pinActuador = actuadorJson["gpio_pin"].isNull() ? -1 : (int)actuadorJson["gpio_pin"];
      if (Tanque::reservado(pinActuador)) perfilTanqueValido = false;
    }
  }
  Tanque::configurar(solicitaTanque, solicitaTanque && perfilTanqueValido && cantidadTanques == 1);
  if (solicitaTanque) {
    pinMode(Tanque::PIN_25, INPUT); pinMode(Tanque::PIN_50, INPUT);
    pinMode(Tanque::PIN_75, INPUT); pinMode(Tanque::PIN_100, INPUT);
    if (!perfilTanqueValido || cantidadTanques != 1) Serial.println("ERROR: conflicto en el conjunto del tanque; nivel sin verificar.");
  }

  ConfiguracionActuador actuadoresAnteriores[MAX_ACTUADORES];
  int cantidadAnterior = cantidadActuadores;
  for (int indice = 0; indice < cantidadAnterior; indice++) actuadoresAnteriores[indice] = actuadores[indice];

  cantidadSensores = 0;

  if (documento["sensores"].is<JsonArray>()) {
    for (JsonObject s : documento["sensores"].as<JsonArray>()) {
      if (cantidadSensores >= MAX_SENSORES) break;

      StaticJsonDocument<512> documentoMetadatos;
       
      JsonObject meta;

        if (s["meta"].is<JsonObject>()) {
        meta = s["meta"].as<JsonObject>();
        } else if (s["meta"].is<const char*>()) {
        DeserializationError errorMetadatos = deserializeJson(documentoMetadatos, s["meta"].as<const char*>());

        if (!errorMetadatos) {
            meta = documentoMetadatos.as<JsonObject>();
        } else {
            meta = JsonObject();
        }
        } else if (s["meta"].is<String>()) {
        DeserializationError errorMetadatos = deserializeJson(documentoMetadatos, s["meta"].as<String>());

        if (!errorMetadatos) {
            meta = documentoMetadatos.as<JsonObject>();
        } else {
            meta = JsonObject();
        }
        } else {
        meta = JsonObject();
        }

        ConfiguracionSensor& S = sensores[cantidadSensores];
        S.codigo = s["codigo"] | "";
        S.tipo = s["tipo"] | "";
        S.unidad = s["unidad"] | "";
        S.gpio = s["gpio_pin"].isNull() ? -1 : (int)s["gpio_pin"];
        S.activo = s["activo"] | true;
        S.controlador = identificarControlador(S.codigo, S.tipo, meta);
        S.tipoDht = interpretarTipoDht(meta);
        S.valor = NAN;
        S.lecturaOriginal = -1;
        if (S.controlador == "nivel_agua_fijo") S.gpio = -1;
        else if (Tanque::ocupado(S.gpio)) S.activo = false;

      if (S.controlador == "adc_ppm") {
        S.adcMinimo = obtenerNumeroJson(meta["adc_min"], 800);
        S.adcMaximo = obtenerNumeroJson(meta["adc_max"], 3000);
        S.valorMinimo = obtenerNumeroJson(meta["val_min"], 400);
        S.valorMaximo = obtenerNumeroJson(meta["val_max"], 2000);
      } else {
        S.adcMinimo = obtenerNumeroJson(meta["adc_min"], 3500);
        S.adcMaximo = obtenerNumeroJson(meta["adc_max"], 800);
        S.valorMinimo = obtenerNumeroJson(meta["val_min"], 0);
        S.valorMaximo = obtenerNumeroJson(meta["val_max"], 100);
      }

      if (S.activo && esGpioValido(S.gpio) && S.controlador != "dht") {
        pinMode(S.gpio, INPUT);
      }

      Serial.print("Sensor ");
      Serial.print(S.codigo);
      Serial.print(" controlador=");
      Serial.print(S.controlador);
      Serial.print(" gpio=");
      Serial.println(S.gpio);

      cantidadSensores++;
    }
  }

  cantidadActuadores = 0;

  if (documento["actuadores"].is<JsonArray>()) {
    for (JsonObject a : documento["actuadores"].as<JsonArray>()) {
      if (cantidadActuadores >= MAX_ACTUADORES) break;

      String codigo = a["codigo"] | "";
      int gpio = a["gpio_pin"].isNull() ? -1 : (int)a["gpio_pin"];
      bool invertido = a["invertido"] | false;
      if (Tanque::ocupado(gpio)) gpio = -1;

      ConfiguracionActuador& A = actuadores[cantidadActuadores];
      A.codigo = codigo;
      A.tipo = a["tipo"] | "";
      A.gpio = gpio;
      A.activo = true;
      A.invertido = invertido;
      A.estadoActual = -1;

      for (int indiceAnterior = 0; indiceAnterior < cantidadAnterior; indiceAnterior++) {
        if (actuadoresAnteriores[indiceAnterior].codigo == codigo &&
            actuadoresAnteriores[indiceAnterior].gpio == gpio &&
            actuadoresAnteriores[indiceAnterior].invertido == invertido) {
          A.estadoActual = actuadoresAnteriores[indiceAnterior].estadoActual;
          break;
        }
      }

      if (admiteSalida(A.gpio)) {
        pinMode(A.gpio, OUTPUT);


        if (A.estadoActual == -1) {
          int offElectrical = A.invertido ? HIGH : LOW;
          digitalWrite(A.gpio, offElectrical);
        }
      } else {
        Serial.print("ADVERTENCIA: actuador ");
        Serial.print(A.codigo);
        Serial.print(" GPIO no válido para salida: ");
        Serial.println(A.gpio);
      }

      Serial.print("Actuador ");
      Serial.print(A.codigo);
      Serial.print(" gpio=");
      Serial.print(A.gpio);
      Serial.print(" invertido=");
      Serial.println(A.invertido ? "SI" : "NO");

      cantidadActuadores++;
    }
  }

 
  Calefaccion::Configuracion configuracionCalef;
  unsigned cantidadSupervisiones = 0;
  for (JsonObject sensorJson : documento["sensores"].as<JsonArray>()) {
    String tipoSensor = sensorJson["tipo"] | "";
    String codigoSensor = sensorJson["codigo"] | "";
    if (tipoSensor != "verificacion_calefaccion" && codigoSensor != "S_CONF_CALEF") continue;
    if (!(sensorJson["activo"] | true)) continue;
    ++cantidadSupervisiones;
    JsonObject datos = sensorJson["meta"].as<JsonObject>();
    // /sync entrega meta como objeto. No se acepta configuración ambigua.
    configuracionCalef.presente = true;
    configuracionCalef.gpio = sensorJson["gpio_pin"].isNull() ? -1 : (int)sensorJson["gpio_pin"];
    configuracionCalef.calibrado = datos["calibrado"] | false;
    configuracionCalef.revision = datos["revision"] | 0UL;
    configuracionCalef.umbralEncendidoMv = obtenerNumeroJson(datos["umbral_encendido_mv"], 50);
    configuracionCalef.umbralApagadoMv = obtenerNumeroJson(datos["umbral_apagado_mv"], 25);
    configuracionCalef.ceroMv = obtenerNumeroJson(datos["cero_mv"], 1667);
    configuracionCalef.ceroMinMv = obtenerNumeroJson(datos["cero_min_mv"], 1300);
    configuracionCalef.ceroMaxMv = obtenerNumeroJson(datos["cero_max_mv"], 2000);
    configuracionCalef.esperaMs = (uint32_t)(obtenerNumeroJson(datos["espera_seg"], 3) * 1000);
    strlcpy(configuracionCalef.codigoSensor, codigoSensor.c_str(), sizeof(configuracionCalef.codigoSensor));
    strlcpy(configuracionCalef.codigoActuador, datos["actuador_codigo"] | "", sizeof(configuracionCalef.codigoActuador));
    int pin = configuracionCalef.gpio;
    configuracionCalef.pinDisponible = (pin == 32 || pin == 33 || pin == 34 || pin == 35 || pin == 36 || pin == 39) && !Tanque::ocupado(pin);
    for (int indiceAnterior = 0; indiceAnterior < cantidadSensores; ++indiceAnterior) {
      if (sensores[indiceAnterior].codigo != codigoSensor && sensores[indiceAnterior].gpio == pin) configuracionCalef.pinDisponible = false;
    }
    bool actuadorEncontrado = false;
    for (int indiceAnterior = 0; indiceAnterior < cantidadActuadores; ++indiceAnterior) {
      if (actuadores[indiceAnterior].gpio == pin) configuracionCalef.pinDisponible = false;
      if (actuadores[indiceAnterior].codigo == configuracionCalef.codigoActuador) actuadorEncontrado = true;
    }
    if (!actuadorEncontrado) configuracionCalef.pinDisponible = false;
  }
  if (cantidadSupervisiones > 1) configuracionCalef.pinDisponible = false;
  Calefaccion::configurar(configuracionCalef);
  for (int indiceAnterior = 0; indiceAnterior < cantidadActuadores; ++indiceAnterior) {
    if (actuadores[indiceAnterior].estadoActual >= 0) Calefaccion::registrarOrden(actuadores[indiceAnterior].codigo, actuadores[indiceAnterior].estadoActual == 1);
  }
  configurarDhtDesdeSensores();

  Serial.print("Configuración aplicada: sensores=");
  Serial.print(cantidadSensores);
  Serial.print(" actuadores=");
  Serial.print(cantidadActuadores);
  Serial.print(" modo=");
  Serial.println(modoGlobal);

  return true;
}

bool mqttDisponible() {
  return MQTT_HABILITADO && WiFi.status() == WL_CONNECTED && clienteMqtt.connected();
}

bool publicarJsonMqtt(const String& tema, const String& cuerpoMensaje, bool retenido = false) {
  if (!mqttDisponible()) return false;

  uint16_t identificadorPaquete = clienteMqtt.publish(tema.c_str(), 1, retenido, cuerpoMensaje.c_str());
  bool correcto = identificadorPaquete != 0;

  Serial.print("Publicacion MQTT con QoS 1 ");
  Serial.print(tema);
  Serial.print(" -> ");
  Serial.println(correcto ? "ENCOLADO" : "ERROR");
  return correcto;
}

void publicarEstadoMqtt(const char* estado) {
  if (!mqttDisponible()) return;

  DynamicJsonDocument documento(512);
  documento["status"] = estado;
  documento["uid"] = MODULO_UID;
  documento["codigo"] = MODULO_CODIGO;
  documento["fw"] = VERSION_FIRMWARE;
  documento["rssi"] = WiFi.RSSI();
  documento["ip"] = WiFi.localIP().toString();

  String cuerpoMensaje;
  serializeJson(documento, cuerpoMensaje);
  publicarJsonMqtt(TEMA_ESTADO, cuerpoMensaje, true);
}

void procesarComandoMqtt(const String& cuerpoMensaje) {
  DynamicJsonDocument documento(4096);
  DeserializationError errorJson = deserializeJson(documento, cuerpoMensaje);

  if (errorJson) {
    Serial.print("MQTT comando JSON inválido: ");
    Serial.println(errorJson.c_str());
    return;
  }

  if (!documento.is<JsonObject>()) return;

  Serial.print("MQTT comando recibido: ");
  Serial.println(cuerpoMensaje);
  ejecutarComando(documento.as<JsonObject>());
}

void alRecibirMensajeMqtt(
  const espMqttClientTypes::MessageProperties& propiedades,
  const char* tema,
  const uint8_t* contenido,
  size_t longitudFragmento,
  size_t posicionFragmento,
  size_t longitudTotal
) {
  String temaTexto(tema);
  if (temaTexto != TEMA_COMANDO) return;

  if (posicionFragmento == 0) {
    bufferRecepcionMqtt = "";
    temaRecibidoMqtt = temaTexto;
    tamanoMensajeMqtt = longitudTotal;
    bufferRecepcionMqtt.reserve(longitudTotal + 1);
  }

  for (size_t indice = 0; indice < longitudFragmento; indice++) {
    bufferRecepcionMqtt += (char) contenido[indice];
  }

  if (posicionFragmento + longitudFragmento >= longitudTotal) {
    String completo = bufferRecepcionMqtt;
    bufferRecepcionMqtt = "";
    tamanoMensajeMqtt = 0;
    procesarComandoMqtt(completo);
  }
}

void alConectarMqtt(bool sesionExistente) {
  Serial.print("MQTT conectado. Sesion existente=");
  Serial.println(sesionExistente ? "SI" : "NO");

  uint16_t identificadorSuscripcion = clienteMqtt.subscribe(TEMA_COMANDO.c_str(), 1);
  Serial.print("Suscripcion al tema de comandos. Identificador=");
  Serial.println(identificadorSuscripcion);

  publicarEstadoMqtt("online");
}

void alDesconectarMqtt(espMqttClientTypes::DisconnectReason motivo) {
  Serial.print("MQTT desconectado. Motivo=");
  Serial.println((int) motivo);
}

void mantenerConexionMqtt() {
  if (!MQTT_HABILITADO || WiFi.status() != WL_CONNECTED || !horaSincronizada) return;
  if (clienteMqtt.connected()) return;
  if (!clienteMqtt.disconnected()) return;

  unsigned long ahora = millis();
  if (ahora - ultimoIntentoMqttMs < REINTENTO_MQTT_MS) return;
  ultimoIntentoMqttMs = ahora;

  Serial.print("Conectando MQTT a ");
  Serial.print(SERVIDOR_MQTT);
  Serial.print(":");
  Serial.println(PUERTO_MQTT);

  if (!clienteMqtt.connect()) {
    Serial.println("No se pudo iniciar la conexión MQTT. REST sigue activo.");
  }
}

void aplicarEstadoActuador(ConfiguracionActuador& a, bool encendido) {
  if (!admiteSalida(a.gpio)) {
    Serial.print("No se puede accionar ");
    Serial.print(a.codigo);
    Serial.print(". GPIO inválido: ");
    Serial.println(a.gpio);
    return;
  }

  int estadoLogico = encendido ? 1 : 0;
  int nivelElectrico = a.invertido ? !estadoLogico : estadoLogico;

  digitalWrite(a.gpio, nivelElectrico);
  Calefaccion::registrarOrden(a.codigo, encendido);
  a.estadoActual = estadoLogico;

  Serial.print("ACTUADOR ");
  Serial.print(a.codigo);
  Serial.print(" -> ");
  Serial.print(encendido ? "ON" : "OFF");
  Serial.print(" GPIO=");
  Serial.print(a.gpio);
  Serial.print(" write=");
  Serial.println(nivelElectrico);
}

void agregarEstadosReportados(JsonArray reportados) {
  for (int indice = 0; indice < cantidadActuadores; indice++) {
    if (actuadores[indice].estadoActual < 0) continue;

    JsonObject reporte = reportados.createNestedObject();
    reporte["actuador"] = actuadores[indice].codigo;

    JsonObject estado = reporte.createNestedObject("estado");
    estado["on"] = actuadores[indice].estadoActual == 1;
    estado["gpio"] = actuadores[indice].gpio;
  }
}

void enviarConfirmacion(const String& identificadorComando, bool correcto, const String& detalleError = "") {
  DynamicJsonDocument documento(2048);

  documento["nonce"] = identificadorComando;
  documento["ok"] = correcto;

  if (!correcto && detalleError.length() > 0) {
    documento["error"] = detalleError;
  }

  JsonArray reportados = documento.createNestedArray("reportados");
  agregarEstadosReportados(reportados);

  String cuerpoMensaje;
  serializeJson(documento, cuerpoMensaje);

  Serial.println("JSON de confirmacion:");
  Serial.println(cuerpoMensaje);

  // confirmacion ACK es crítico y de muy bajo volumen: durante la fase híbrida se envía
  // por ambos canales. Laravel trata el mismo identificador de comando de forma idempotente.
  bool enviadoMqtt = publicarJsonMqtt(TEMA_CONFIRMACION, cuerpoMensaje, false);
  String respuestaServidor;
  bool enviadoRest = enviarJsonHttp(URL_CONFIRMACION, cuerpoMensaje, respuestaServidor);

  if (!enviadoMqtt && !enviadoRest) {
    Serial.println("La confirmacion no pudo enviarse por MQTT ni por REST.");
  }
}

void aplicarEstadosDeseados(JsonArray lista) {
  for (JsonObject a : lista) {
    String codigo = a["codigo"] | "";
    JsonObject estado = a["estado_deseado"];

    if (codigo.length() == 0 || estado.isNull() || !estado.containsKey("on")) continue;

    ConfiguracionActuador* local = buscarActuador(codigo);
    if (!local) continue;

    if (local->estadoActual == -1) {
      aplicarEstadoActuador(*local, (bool)(estado["on"] | false));
    }
  }
}

int nivelDesdeCodigoSensor(const String& codigo) {
  String textoCodigo = convertirMinusculas(codigo);

  if (textoCodigo.indexOf("100") >= 0) return 100;
  if (textoCodigo.indexOf("75") >= 0) return 75;
  if (textoCodigo.indexOf("50") >= 0) return 50;
  if (textoCodigo.indexOf("25") >= 0) return 25;

  return -1;
}

float obtenerPorcentajeAgua() {
  for (int indice=0; indice<cantidadSensores; ++indice) {
    if (sensores[indice].controlador == "nivel_agua_fijo") {
      Tanque::Lectura lecturaTanque = Tanque::obtener();
      return sensores[indice].activo && lecturaTanque.valida ? lecturaTanque.nivel : NAN;
    }
  }
  float mayorNivel = NAN;

  for (int indice = 0; indice < cantidadSensores; indice++) {
    ConfiguracionSensor& s = sensores[indice];
    String textoCodigo = convertirMinusculas(s.codigo);
    String textoTipo = convertirMinusculas(s.tipo);

    if (textoCodigo == "s_nivel" || textoCodigo == "s_water" || textoCodigo == "s_agua") {
      if (!isnan(s.valor)) mayorNivel = isnan(mayorNivel) ? s.valor : max(mayorNivel, s.valor);
      continue;
    }

    if (textoCodigo.indexOf("nivel") >= 0 || textoTipo.indexOf("nivel") >= 0) {
      int nivel = nivelDesdeCodigoSensor(s.codigo);

      if (nivel > 0 && !isnan(s.valor) && s.valor > 0.5f) {
        mayorNivel = isnan(mayorNivel) ? (float)nivel : max(mayorNivel, (float)nivel);
      }
    }
  }

  if (isnan(mayorNivel)) return 0;
  return limitarValor(mayorNivel, 0, 100);
}

bool esActuadorAlimentacion(const ConfiguracionActuador& a) {
  String textoCodigo = convertirMinusculas(a.codigo);
  String textoTipo = convertirMinusculas(a.tipo);

  return textoCodigo.indexOf("alimento") >= 0 ||
         textoCodigo.indexOf("feed") >= 0 ||
         textoCodigo.indexOf("croqueta") >= 0 ||
         textoTipo.indexOf("stepper") >= 0 ||
         textoTipo.indexOf("dosificador") >= 0;
}

bool esActuadorAgua(const ConfiguracionActuador& a) {
  String textoCodigo = convertirMinusculas(a.codigo);
  String textoTipo = convertirMinusculas(a.tipo);

  return textoCodigo.indexOf("agua") >= 0 ||
         textoCodigo.indexOf("water") >= 0 ||
         textoCodigo.indexOf("valvula") >= 0 ||
         textoTipo.indexOf("valvula") >= 0;
}

void iniciarAlimentacion(ConfiguracionActuador& a, const String& identificadorComando, int duracionSeg, bool temporizado = true) {
  duracionSeg = constrain(duracionSeg, 1, 300);

  accionAlimentacion.enCurso = true;
  accionAlimentacion.temporizado = temporizado;
  accionAlimentacion.identificadorComando = identificadorComando;
  accionAlimentacion.codigoActuadorEnCurso = a.codigo;

  if (temporizado) {
    accionAlimentacion.vencimientoMs = millis() + ((unsigned long)duracionSeg * 1000UL);
  } else {
    accionAlimentacion.vencimientoMs = 0;
  }

  accionAlimentacion.ultimoPulsoUs = micros();
  accionAlimentacion.nivelPulso = false;
  accionAlimentacion.modoMotorPaso = convertirMinusculas(a.tipo).indexOf("stepper") >= 0 ||
                           convertirMinusculas(a.codigo).indexOf("step") >= 0 ||
                           convertirMinusculas(a.tipo).indexOf("dosificador") >= 0;

  // Si existe dirección configurada, la activa. Si no existe, no pasa nada.
  ConfiguracionActuador* salidaDireccion = buscarActuador("D_ALIMENTO_DIR");
  if (salidaDireccion && admiteSalida(salidaDireccion->gpio)) aplicarEstadoActuador(*salidaDireccion, true);

  ConfiguracionActuador* salidaHabilitacion = buscarActuador("D_ALIMENTO_EN");
  if (salidaHabilitacion && admiteSalida(salidaHabilitacion->gpio)) aplicarEstadoActuador(*salidaHabilitacion, true);

  if (accionAlimentacion.modoMotorPaso) {
    pinMode(a.gpio, OUTPUT);
    digitalWrite(a.gpio, LOW);
    a.estadoActual = 1;
  } else {
    aplicarEstadoActuador(a, true);
  }

  if (temporizado) {
    Serial.print("Alimentación temporizada iniciada por ");
    Serial.print(duracionSeg);
    Serial.println(" s");
  } else {
    Serial.println("Motor de alimentación encendido en modo continuo.");
  }

  enviarConfirmacion(identificadorComando, true);
}

void detenerAlimentacion(bool notificar = true) {
  if (!accionAlimentacion.enCurso) return;

  ConfiguracionActuador* a = buscarActuador(accionAlimentacion.codigoActuadorEnCurso);

  if (a) {
    if (accionAlimentacion.modoMotorPaso && admiteSalida(a->gpio)) {
      digitalWrite(a->gpio, LOW);
      a->estadoActual = 0;
    } else {
      aplicarEstadoActuador(*a, false);
    }
  }

  ConfiguracionActuador* salidaHabilitacion = buscarActuador("D_ALIMENTO_EN");
  if (salidaHabilitacion && admiteSalida(salidaHabilitacion->gpio)) aplicarEstadoActuador(*salidaHabilitacion, false);

  String identificadorComando = accionAlimentacion.identificadorComando;
  accionAlimentacion.enCurso = false;

  Serial.println("Alimentación finalizada.");

  if (notificar && identificadorComando.length() > 0) {
    enviarConfirmacion(identificadorComando, true);
  }
}

void iniciarLlenadoAgua(ConfiguracionActuador& a, const String& identificadorComando, int porcentajeObjetivo, int tiempoMaximoSeg) {
  porcentajeObjetivo = constrain(porcentajeObjetivo, 1, 100);
  tiempoMaximoSeg = constrain(tiempoMaximoSeg, 5, 300);

  float actual = obtenerPorcentajeAgua();

  if (isnan(actual)) {
    aplicarEstadoActuador(a, false);
    enviarConfirmacion(identificadorComando, false, "nivel_agua_sin_verificar");
    return;
  }
  if (actual >= porcentajeObjetivo) {
    aplicarEstadoActuador(a, false);
    Serial.println("Agua omitida: nivel objetivo ya alcanzado.");
    enviarConfirmacion(identificadorComando, true);
    return;
  }

  llenadoAgua.enCurso = true;
  llenadoAgua.identificadorComando = identificadorComando;
  llenadoAgua.codigoActuadorEnCurso = a.codigo;
  llenadoAgua.porcentajeObjetivo = porcentajeObjetivo;
  llenadoAgua.vencimientoMs = millis() + ((unsigned long)tiempoMaximoSeg * 1000UL);

  aplicarEstadoActuador(a, true);

  Serial.print("Electroválvula activada hasta ");
  Serial.print(porcentajeObjetivo);
  Serial.print(" %. Timeout ");
  Serial.print(tiempoMaximoSeg);
  Serial.println(" s");

  enviarConfirmacion(identificadorComando, true);
}

void detenerLlenadoAgua(bool notificar = true, const String& motivo = "fin") {
  if (!llenadoAgua.enCurso) return;

  ConfiguracionActuador* a = buscarActuador(llenadoAgua.codigoActuadorEnCurso);
  if (a) aplicarEstadoActuador(*a, false);

  String identificadorComando = llenadoAgua.identificadorComando;
  llenadoAgua.enCurso = false;

  Serial.print("Electroválvula apagada: ");
  Serial.println(motivo);

  if (notificar && identificadorComando.length() > 0) {
    enviarConfirmacion(identificadorComando, true);
  }
}
void atenderAccionesEnCurso() {
  if (accionAlimentacion.enCurso) {
    ConfiguracionActuador* a = buscarActuador(accionAlimentacion.codigoActuadorEnCurso);

    if (!a || !admiteSalida(a->gpio)) {
      detenerAlimentacion(true);
    } else if (accionAlimentacion.temporizado && (long)(millis() - accionAlimentacion.vencimientoMs) >= 0) {
      detenerAlimentacion(true);
    } else if (accionAlimentacion.modoMotorPaso) {
      unsigned long ahoraUs = micros();

      if (ahoraUs - accionAlimentacion.ultimoPulsoUs >= SEMIPERIODO_MOTOR_PASO_US) {
        accionAlimentacion.ultimoPulsoUs = ahoraUs;
        accionAlimentacion.nivelPulso = !accionAlimentacion.nivelPulso;
        digitalWrite(a->gpio, accionAlimentacion.nivelPulso ? HIGH : LOW);
      }
    }
  }
  if (llenadoAgua.enCurso) {
    float nivel = obtenerPorcentajeAgua();

    if (isnan(nivel)) {
      detenerLlenadoAgua(false, "nivel_agua_sin_verificar");
    } else if (nivel >= llenadoAgua.porcentajeObjetivo) {
      detenerLlenadoAgua(true, "nivel_objetivo_alcanzado");
    } else if ((long)(millis() - llenadoAgua.vencimientoMs) >= 0) {
      detenerLlenadoAgua(true, "timeout");
    }
  }
}
void ejecutarComando(JsonObject comando) {
  if (comando.isNull()) return;

  String identificadorComando = comando["nonce"] | "";
  String tipo = comando["tipo"] | "";
 if (identificadorComando.length() == 0) return;

  // El mismo comando puede aparecer por MQTT y posteriormente por REST /sync.
  // El identificador de comando hace que el accionamiento sea idempotente.
  if (comandoYaEjecutado(identificadorComando)) {
    Serial.println("Comando duplicado ignorado; se reenvia la confirmacion.");
    enviarConfirmacion(identificadorComando, true);
    return;
  }

  if (tipo != "set_estado") {
    enviarConfirmacion(identificadorComando, false, "tipo_no_soportado:" + tipo);
    return;
  }
  JsonObject contenido = comando["payload"];
  String actuador = contenido["actuador"] | "";
  JsonObject estado = contenido["estado"];
  if (actuador.length() == 0 || estado.isNull() || !estado.containsKey("on")) {
    enviarConfirmacion(identificadorComando, false, "payload_invalido");
    return;
  }
  ConfiguracionActuador* a = buscarActuador(actuador);
  if (!a) {
    enviarConfirmacion(identificadorComando, false, "actuador_no_configurado:" + actuador);
    return;
  }
  if (!admiteSalida(a->gpio)) {
    enviarConfirmacion(identificadorComando, false, "gpio_no_valido_para_salida:" + String(a->gpio));
    return;
  }

  bool encendido = estado["on"] | false;
  String accion = contenido["accion"] | estado["accion"] | "set_estado";

  registrarComandoEjecutado(identificadorComando);

  if (!encendido) {
    if (accionAlimentacion.enCurso && accionAlimentacion.codigoActuadorEnCurso == a->codigo) {
      detenerAlimentacion(false);
    }

    if (llenadoAgua.enCurso && llenadoAgua.codigoActuadorEnCurso == a->codigo) {
      detenerLlenadoAgua(false, "apagado_remoto");
    }

    aplicarEstadoActuador(*a, false);
    enviarConfirmacion(identificadorComando, true);
    return;
  }

  String codigoActuador = convertirMinusculas(a->codigo);
String tipoActuador = convertirMinusculas(a->tipo);

bool esMotorPaso = codigoActuador.indexOf("step") >= 0 ||
                   codigoActuador.indexOf("feed") >= 0 ||
                   codigoActuador.indexOf("alimento") >= 0 ||
                   codigoActuador.indexOf("croqueta") >= 0 ||
                   tipoActuador.indexOf("stepper") >= 0 ||
                   tipoActuador.indexOf("dosificador") >= 0;

if (esMotorPaso) {
  if (accion == "pulso") {
    int duracionSeg = contenido["duracion_seg"] | estado["duracion_seg"] | 10;
    iniciarAlimentacion(*a, identificadorComando, duracionSeg, true);
    return;
  }

  if (accion == "set_estado") {
    iniciarAlimentacion(*a, identificadorComando, 10, false);
    return;
  }
}

  if (accion == "llenar_hasta" && esActuadorAgua(*a)) {
    int porcentajeObjetivo = contenido["nivel_objetivo"] | estado["nivel_objetivo"] | 50;
    int tiempoMaximoSeg = contenido["timeout_seg"] | estado["timeout_seg"] | 90;
    iniciarLlenadoAgua(*a, identificadorComando, porcentajeObjetivo, tiempoMaximoSeg);
    return;
  }

  aplicarEstadoActuador(*a, encendido);
  enviarConfirmacion(identificadorComando, true);
}

void sincronizarConServidor() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Sincronizacion omitida: WiFi desconectado.");
    return;
  }

  String respuestaServidor;

  if (!consultarHttp(URL_SINCRONIZACION, respuestaServidor)) {
    Serial.println("Sincronizacion fallida. Se mantiene la configuracion local.");
    return;
  }

  if (!interpretarSincronizacionJson(respuestaServidor)) {
    Serial.println("Se recibio la sincronizacion pero no se pudo interpretar.");
    return;
  }

  guardarConfiguracionSinComandos(respuestaServidor);

  DynamicJsonDocument documento(CAPACIDAD_JSON_SINCRONIZACION);
  DeserializationError errorJson = deserializeJson(documento, respuestaServidor);

  if (errorJson) {
    Serial.print("Error al interpretar el comando de sincronizacion: ");
    Serial.println(errorJson.c_str());
    return;
  }

  if (documento["actuadores"].is<JsonArray>()) {
    aplicarEstadosDeseados(documento["actuadores"].as<JsonArray>());
  }

  if (documento["comando"].is<JsonObject>()) {
    JsonObject comando = documento["comando"].as<JsonObject>();

    Serial.print("Comando recibido con identificador=");
    Serial.print((const char*)comando["nonce"]);
    Serial.print(" tipo=");
    Serial.println((const char*)comando["tipo"]);

    ejecutarComando(comando);
  } else {
    Serial.println("Sincronizacion correcta: sin comandos pendientes.");
  }
}

void leerDhtValidado() {
  if (!dhtPreparado || sensorDht == nullptr) return;

  float textoTipo = sensorDht->readTemperature();
  float h = sensorDht->readHumidity();

  if (!isnan(textoTipo) && textoTipo > -10 && textoTipo < 70) ultimaTemperaturaDht = textoTipo;
  if (!isnan(h) && h >= 0 && h <= 100) ultimaHumedadDht = h;
}

void leerSensores() {
  leerDhtValidado();

  for (int indice = 0; indice < cantidadSensores; indice++) {
    ConfiguracionSensor& s = sensores[indice];

    s.valor = NAN;
    s.lecturaOriginal = -1;

    if (!s.activo) continue;
    if (s.controlador == "ninguno") continue;

    String textoCodigo = convertirMinusculas(s.codigo);

    if (s.controlador == "dht") {
      if (textoCodigo == "s_temp") s.valor = ultimaTemperaturaDht;
      else if (textoCodigo == "s_hr") s.valor = ultimaHumedadDht;
      continue;
    }

    if (s.controlador == "nivel_agua_fijo") {
      Tanque::Lectura lecturaTanque = Tanque::obtener();
      s.valor = lecturaTanque.valida ? lecturaTanque.nivel : -1;
      continue;
    }
    if (!esGpioValido(s.gpio)) {
      Serial.print("Sensor ");
      Serial.print(s.codigo);
      Serial.print(" sin GPIO valido: ");
      Serial.println(s.gpio);
      continue;
    }

    if (s.controlador == "acs712_ac") {
      Calefaccion::Resultado verificacion = Calefaccion::obtenerResultado();
      s.valor = verificacion.valida ? verificacion.estado : -1;
      continue;
    }
    if (s.controlador == "digital" || s.controlador == "digital_level") {
      s.lecturaOriginal = digitalRead(s.gpio);
      s.valor = (float)s.lecturaOriginal;
    } else if (s.controlador == "adc" || s.controlador == "adc_percent" || s.controlador == "adc_ppm") {
      s.lecturaOriginal = analogRead(s.gpio);

      float dato = escalarValor((float)s.lecturaOriginal, s.adcMinimo, s.adcMaximo, s.valorMinimo, s.valorMaximo);

      if (s.controlador == "adc_percent") dato = limitarValor(dato, 0, 100);
      if (s.controlador == "adc_ppm") dato = limitarValor(dato, 0, 5000);

      s.valor = dato;
    }
  }

  Serial.println("=== LECTURAS ===");

  for (int indice = 0; indice < cantidadSensores; indice++) {
    Serial.print(sensores[indice].codigo);
    Serial.print(" GPIO=");
    Serial.print(sensores[indice].gpio);
    Serial.print(" controlador=");
    Serial.print(sensores[indice].controlador);
    Serial.print(" lectura original=");
    Serial.print(sensores[indice].lecturaOriginal);
    Serial.print(" valor=");
    Serial.println(sensores[indice].valor);
  }
}

void enviarLecturas() {
  DynamicJsonDocument documento(6144);
  documento["uid"] = MODULO_UID;
  documento["codigo"] = MODULO_CODIGO;
  documento["fw"] = VERSION_FIRMWARE;
  if (WiFi.status() == WL_CONNECTED) documento["rssi"] = WiFi.RSSI();

  JsonArray lista = documento.createNestedArray("lecturas");
  String fechaMedicion = obtenerFechaHora();

  for (int indice = 0; indice < cantidadSensores; indice++) {
    ConfiguracionSensor& s = sensores[indice];

    Tanque::Lectura lecturaTanque;
    if (s.controlador == "nivel_agua_fijo") {
      lecturaTanque = Tanque::obtener();
      s.valor = lecturaTanque.valida ? lecturaTanque.nivel : -1;
    }
    Calefaccion::Resultado verificacion;
    if (s.controlador == "acs712_ac") {
      verificacion = Calefaccion::obtenerResultado();
      Calefaccion::Configuracion configuracionCalef = Calefaccion::obtenerConfiguracion();
      if (s.codigo != configuracionCalef.codigoSensor) verificacion.valida = false;
      s.valor = verificacion.valida ? verificacion.estado : -1;
    }
    if (!s.activo || isnan(s.valor)) continue;

    JsonObject o = lista.createNestedObject();
    o["codigo"] = s.codigo;
    o["valor"] = s.valor;
    o["calidad"] = "ok";

    if (fechaMedicion.length() > 0) {
      o["medido_en"] = fechaMedicion;
    }

    JsonObject lecturaOriginal = o.createNestedObject("raw");
    lecturaOriginal["gpio"] = s.gpio;
    lecturaOriginal["driver"] = s.controlador;
    if (s.controlador == "nivel_agua_fijo") {
      o["calidad"] = lecturaTanque.valida ? "ok" : "error";
      lecturaOriginal["perfil"] = "clavel_4_niveles";
      lecturaOriginal["nivel_valido"] = lecturaTanque.valida;
      lecturaOriginal["edad_muestra_ms"] = (uint32_t)(millis() - lecturaTanque.tomadaMs);
    }
    if (s.controlador == "acs712_ac") {
      o["calidad"] = verificacion.valida ? "ok" : "error";
      lecturaOriginal["verificacion_valida"] = verificacion.valida;
      lecturaOriginal["revision"] = verificacion.revision;
      lecturaOriginal["edad_muestra_ms"] = (uint32_t)(millis() - verificacion.muestreadoMs);
      lecturaOriginal["motivo"] = verificacion.motivo;
      lecturaOriginal["diagnostico_local"] = verificacion.diagnostico;
      // Solo en monitor serie y habilitado expresamente para calibración.
      if (DEPURAR_CALIBRACION_CALEFACCION) {
        Serial.printf("ACS712 media=%.1f mV eficaz=%.1f mV estado=%d valida=%d\n",
          verificacion.mediaMv, verificacion.eficazMv, verificacion.estado, verificacion.valida);
      }
    }

    if (s.lecturaOriginal >= 0) {
      lecturaOriginal["adc_digital"] = s.lecturaOriginal;
    }
  }

  if (lista.size() == 0) return;

  String cuerpoMensaje;
  serializeJson(documento, cuerpoMensaje);

  Serial.println("JSON lecturas:");
  Serial.println(cuerpoMensaje);

  bool enviadoMqtt = publicarJsonMqtt(TEMA_TELEMETRIA, cuerpoMensaje, false);

  if (!enviadoMqtt) {
    String respuestaServidor;
    enviarJsonHttp(URL_LECTURAS, cuerpoMensaje, respuestaServidor);
  }
}

void setup() {
  Serial.begin(115200);
  delay(300);

  analogSetAttenuation(ADC_11db);
  Calefaccion::iniciar();
  Tanque::iniciar();

  cargarHistorialComandos();

  identificadorClienteMqtt = String("mecacuy-") + MODULO_CODIGO + "-" + String((uint32_t)(ESP.getEfuseMac() & 0xFFFFFFFF), HEX);
  mensajeDesconexionMqtt = String("{\"status\":\"offline\",\"uid\":\"") + MODULO_UID + "\",\"codigo\":\"" + MODULO_CODIGO + "\"}";

  clienteMqtt
    .setCACert(CERTIFICADO_RAIZ_MQTT)
    .setServer(SERVIDOR_MQTT, PUERTO_MQTT)
    .setClientId(identificadorClienteMqtt.c_str())
    .setCredentials(USUARIO_MQTT, CLAVE_MQTT)
    .setKeepAlive(30)
    .setWill(TEMA_ESTADO.c_str(), 1, true, mensajeDesconexionMqtt.c_str())
    .onConnect(alConectarMqtt)
    .onDisconnect(alDesconectarMqtt)
    .onMessage(alRecibirMensajeMqtt);

  WiFi.mode(WIFI_STA);
  WiFi.begin(NOMBRE_RED_WIFI, CLAVE_RED_WIFI);

  unsigned long inicioConexionMs = millis();

  while (WiFi.status() != WL_CONNECTED && millis() - inicioConexionMs < 8000) {
    delay(200);
    Serial.print(".");
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi conectado");
    Serial.println(WiFi.localIP());
    sincronizarHora();
    mantenerConexionMqtt();
  } else {
    Serial.println("WiFi no conectado. Se intentará reconectar.");
  }

  Serial.println("Forzando primera sincronización con Laravel...");

  bool sincronizacionCorrecta = false;

  if (WiFi.status() == WL_CONNECTED) {
    String respuestaServidor;

    if (consultarHttp(URL_SINCRONIZACION, respuestaServidor)) {
      Serial.println("Sincronizacion inicial correcta.");

      if (interpretarSincronizacionJson(respuestaServidor)) {
        guardarConfiguracionSinComandos(respuestaServidor);
        sincronizacionCorrecta = true;
      }
    }
  }

  if (!sincronizacionCorrecta) {
    String configuracionRecuperada = cargarSincronizacionJson();

    if (configuracionRecuperada.length() > 0) {
      Serial.print("Servidor no disponible. Recuperando configuracion de tamano=");
      Serial.println(configuracionRecuperada.length());
      interpretarSincronizacionJson(configuracionRecuperada);
    } else {
      Serial.println("Sin servidor ni configuracion guardada. El ESP32 espera sincronizacion.");
    }
  }

  leerSensores();

  ultimaSincronizacionMs = millis();
  ultimaLecturaMs = millis();
  ultimoEnvioMs = millis();

  Serial.println("Entrando al loop principal.");
}

void loop() {
  mantenerConexionWifi();
  mantenerConexionMqtt();

  if (WiFi.status() == WL_CONNECTED && !horaSincronizada) {
    sincronizarHora();
  }

  unsigned long ahoraMs = millis();

  atenderAccionesEnCurso();

  if (ahoraMs - ultimaLecturaMs >= INTERVALO_LECTURA_MS) {
    ultimaLecturaMs = ahoraMs;
    leerSensores();
  }

  if (ahoraMs - ultimaSincronizacionMs >= INTERVALO_SINCRONIZACION_MS) {
    ultimaSincronizacionMs = ahoraMs;
    sincronizarConServidor();
  }

  atenderAccionesEnCurso();

  if (ahoraMs - ultimoEnvioMs >= INTERVALO_ENVIO_MS) {
    ultimoEnvioMs = ahoraMs;
    enviarLecturas();
  }

  delay(accionAlimentacion.enCurso ? 1 : 100);
}
