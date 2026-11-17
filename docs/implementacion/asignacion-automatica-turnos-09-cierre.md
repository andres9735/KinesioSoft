# Subpaso 9 — Verificación y cierre del primer incremento

Fecha: **17/11/2026**, America/Buenos_Aires. Rama comprobada:
`feature/turnos-asignacion-automatica`. HEAD:
`6d8afd7a54a20b5b25de3592c96f842c22b0a390`. El código ensayado incluye los cambios
locales de los subpasos 1–8; no se atribuye la evidencia a un commit nuevo.

**Dictamen: cierre técnico local del primer incremento con reservas explícitas,
pendiente de revisión y versionado.** El usuario completó el recorrido manual
del lote 02. No se declara 30/30, cumplimiento productivo ni candidatos de
Seguimiento implementados. El registro final de evidencia al pie actualiza los
estados históricos de las secciones de preparación y diagnóstico.
Las cuatro fuentes aprobadas permanecen intactas por SHA-256. Se leyeron AGENTS,
esas fuentes, el README de fuentes de Turnos y los informes 5–8. No se inspeccionó
ni modificó `docs/archivo/`. No hay staging ni commit.

## Alcance y fuentes

El candidato es **Primera consulta, Pendiente o Confirmado, con la misma
kinesióloga**. El cupo puede proceder de Primera consulta o Seguimiento cancelado.
La duración registrada se conserva; no aplican equipos, plan ni sesión al
candidato de este incremento. CA-G08/G09 y los fragmentos correspondientes de
CA-G07, CA-R06 y CA-R07 quedan para candidatos de Seguimiento.

Se conserva la regla aprobada: una restricción aplicable que falle o no pueda
verificarse excluye la oferta/traslado y mantiene el horario original. No se
interpreta la falta de verificación como permiso. Las configuraciones ambiguas
se rechazan y la aceptación toma la salida sin traslado correspondiente.

Fuentes autoritativas sin modificaciones:

- `docs/asignacion-automatica-turnos/decisiones-funcionales.md`.
- `docs/asignacion-automatica-turnos/casos-de-uso.md`.
- `docs/asignacion-automatica-turnos/matriz-criterios-aceptacion-dependencias.md`.
- `docs/asignacion-automatica-turnos/plan-implementacion.md`.

## Ejecuciones definitivas y entorno

| ID | Entorno y alcance | Resultado | Duración | Evidencia ignorada |
| --- | --- | --- | --- | --- |
| S | PHP 8.2.12 / PHPUnit 11.5.55; SQLite `:memory:`; regresión pertinente completa de esta puerta | **1190 pruebas / 8512 aserciones PASS**, salida 0, sin errores/fallos/omisiones | 454,857 s | `storage/framework/testing/asignacion-cierre-20261117-09/cierre-final/regresion.junit.xml` y `regresion.log` |
| M | PHP 8.2.12 / PHPUnit 11.5.55; MariaDB 10.11.19/InnoDB real; procesos y conexiones independientes | **20 pruebas / 323 aserciones PASS**, salida 0, sin errores/fallos/omisiones | 40,764 s / 48 MB | mismo directorio `cierre-final/`: `mariadb-8.junit.xml`, `suite-8.log` |
| T | CLI + SMTP real exclusivamente local a Mailpit, SQLite persistente exclusivo del ensayo | Dos ofertas y **una confirmación** capturadas; segundo despacho de confirmación sin segundo mensaje | Sin cota temporal atribuida | `web/resultado-cli-smtp.json`, `web/capturas-mailpit.json`, `web/contenido-confirmacion.json` |
| B | Chrome real, recorrido manual del usuario sobre el lote 02, HTTPS y Mailpit local | **PASS informado por el usuario**: aceptar oferta 3, rechazar oferta 4, resultado, Mis turnos, agenda y correo; corroborado con SQLite y Mailpit | Sin cota temporal atribuida | Mensaje del usuario del 17/11/2026; `cierre-final/estado-web-manual.json`, `cierre-final/correos-finales.json` y log del consumidor |

Los conjuntos S y M son distintos; no se suman con corridas históricas ni entre
sí para declarar una suite global. S cubre cancelación e intención, Mis turnos,
agenda HTTP, disponibilidad individual/agregada, alta, Actions de Agenda,
enlaces de asistencia, selección, reserva, emisión, respuestas, confirmación y
recordatorios (registro, selección, contenido, comando, despacho, transporte,
reintentos y alertas). No se ejecutó la suite global del repositorio ni el SMTP
externo opt-in de UC-27.

Preflight S: `testing`, sin configuración cacheada, PDO efectivo SQLite en memoria
sin archivo por PRAGMA; PDO MySQL ausente también en hijos. Correo/cache/sesión
array, Queue fake en los tests correspondientes, NullHandler, clave efímera y
storage/vistas/cachés/documentos exclusivos. Las migraciones internas de S sólo
afectan esa memoria. El wrapper reutiliza el aislamiento del subpaso 8, amplía
el corpus a recordatorios y conserva sus resultados en un directorio nuevo.

Comandos ejecutados desde la raíz:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-cierre-20261117-09/verificar-version-final.ps1 -Fase regresion
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-cierre-20261117-09/maria-version-final.ps1 -Fase Suite -Intento 8
```

El `maria.ps1 -Fase Preparar` inicial se ejecutó **una sola vez**, después del
preflight de identidad y esquema vacío. La corrida final 8 no migró ni reinicializó
la base: la reutilizó bajo sus guardas. No repetir Preparar sobre esta base poblada. La invocación PHP de M,
el perfil opt-in, las guardas y el protocolo de workers están en
`tests/Integration/AdelantosMariaDb09/README.md`.

## Trazabilidad de los 30 criterios

Referencias abreviadas, siempre dentro de `tests/`:

- **C**: `Feature/Actions/Turnos/CancelarTurnoActionTest.php`.
- **E**: `Feature/Services/Turnos/PrimeraEmisionAdelantoTest.php` (S: 23/140).
- **SCT**: `Feature/Services/Turnos/SeleccionarCandidatoAdelantoServiceTest.php` (S: 38/72).
- **V**: `Feature/Services/Turnos/ValidarDestinoAdelantoServiceTest.php` (S: 58/115).
- **O**: `Feature/Actions/Turnos/OfertaAdelantoTest.php` (S: 47/179).
- **X**: `Feature/Turnos/OfertaAdelantoSalidasTest.php` (S: 36/164).
- **A**: `Feature/Turnos/OfertaAdelantoAceptacionTest.php` (S: 36/300).
- **F**: `Feature/Turnos/ConfirmacionAdelantoTest.php` (S: 15/179).
- **MCT**: `Integration/AdelantosMariaDb09/CicloConcurrenteTest.php` (M: 20/323).

`E:97` significa archivo E, línea 99. Los nombres y resultados individuales
están en los JUnit; las cantidades por clase ya están incluidas en S/M.

| Criterio | Prueba localizada y comportamiento acreditado | Resultado y límite |
| --- | --- | --- |
| CA-G01 | C:106,172,186 (`test_cancels_each_eligible_combination...`, límites de 12 h); E:97,223,234: ambos canales/tipos y cancelación histórica | **S PASS**. Mis turnos y enlace de asistencia implementados; la cancelación profesional citada en el caso de uso no tiene flujo implementado y no se certifica. |
| CA-G02 | E:129 repetición y recuperación; X:216,302 continuación; MCT:80 dos consumidores | **S/M PASS**: una evaluación/oferta/despacho inicial por intención; las sucesivas mantienen la evaluación. |
| CA-G03 | O:90,159,285 margen y reloj posterior a lecturas; E:193; MCT:92,202 incompatibilidad y alta | **S/M PASS**. Exactamente 4 h admisible, debajo no; revalidación de disponibilidad al activar. |
| CA-G04 | SCT:40,65,77 selección de estados y exclusión por tipo/profesional | **S PASS**, exclusivamente candidatos PC; origen de ambos tipos. |
| CA-G05 | SCT:86,104 ventana estricta/inclusiva y cruce de semana | **S PASS**, inicio del cupo +168 h, sin corte semanal. |
| CA-G06 | V:54,82,101,158,248,263: 30 en 45, fuera de grilla, otra configuración, duración, franja, bloqueos y cruces | **S PASS**, sin escrituras; M revalida al activar/aceptar en carreras reales. |
| CA-G07 | V:54 y O:107 cupos PC/Seguimiento con candidato PC | **S PASS en alcance PC**. Habilitación adicional de Seguimiento diferida. |
| CA-G08 | SCT:77 excluye candidatos Seguimiento; no se ensaya disponibilidad de equipos | **NO APLICABLE** al primer incremento. No implementado ni certificado. |
| CA-G09 | SCT:77 excluye candidatos Seguimiento; no se traslada sesión de plan | **NO APLICABLE** al primer incremento. No implementado ni certificado. |
| CA-G10 | SCT:116,123,130 prioridad/desempates; 139,161 correo y exclusión por paciente; X:230 | **S PASS**, incluye varios turnos del mismo paciente y salto al siguiente compatible. |
| CA-G11 | O:70,121,143,185,200,248 cupo completo, unicidad, contigüidad y vencimiento exacto; MCT:92,202 | **S/M PASS**, oferta no mueve el turno original; activaciones de intenciones superpuestas también compiten por K. |
| CA-G12 | E:258 contenido/enlace y transporte fuera de TX; X:108 enlace → HTTP; T captura SMTP local | **S/T/B PASS local**, sin datos clínicos. El usuario abrió los enlaces nuevos desde Mailpit en Chrome. |
| CA-G13 | X:318,343,360,432,468: misma oferta/enlace/plazo y límites RF12.5 | **S PASS**, tres reintentos, 5/10/20 min, una hora desde primer fallo conocido y vigencia estricta. |
| CA-G14 | X:374,388,409: fallo definitivo, incertidumbre, recuperación de cierre y continuación | **S PASS**, auditoría y original intacto; incertidumbre sin reenvío automático. |
| CA-G15 | E:180 sin candidatos; X:216,230,246,264,275,302 continuación, exclusión y agotamiento | **S PASS**, misma evaluación, 4 h, cupo libre y repetición sin nueva oferta duplicada. |
| CA-G16 | O:313,348,368: bloqueos/configuración compatibles, invalidantes y rollback conjunto; MCT:147,167 | **S/M PASS** en carreras y operaciones ensayadas; no recalcula duración registrada. |
| CA-R01 | X:108,139,151,173,199,456: consulta GET/HEAD sin cambios, asociación, cookies/CSRF/origen, credenciales inválidas | **S/B PASS en alcance local**. Recorrido HTTPS manual confirmado; atributos/CSRF comprobados por HTTP. No se atribuye al usuario una inspección de DevTools no informada. |
| CA-R02 | X:159 rechazo/repetición; MCT:123 ambos órdenes aceptación/rechazo | **S/M/B PASS local**; oferta 4 rechazada desde el enlace, resultado visible y turno 7 intacto. |
| CA-R03 | X:205,216: vencimiento exacto y Job sin visita; MCT:123: vencimiento/aceptación | **S/M PASS**, expiración desde el límite exacto y recuperación del resultado previo. |
| CA-R04 | A:189,221,234,250,265,278 programación/estado/credencial y vigencia; MCT:123 | **S/M/B PASS local**; POST protegido por HTTP y aceptación explícita desde Chrome informada por el usuario. |
| CA-R05 | A:329 reserva perdida u ocupación; O:159 cupo completo; MCT:202 reserva/alta | **S/M PASS** en casos localizados; turno original conservado ante imposibilidad. |
| CA-R06 | A:300 destino incompatible/no verificable; V:248,263 configuración ausente/anulada/ambigua | **S PASS en alcance PC**; M ejercita revalidación bajo locks. Equipos/plan/sesión de Seguimiento diferidos. |
| CA-R07 | A:115 identidad/datos/estado/duración; A:382 invalida asistencia; MCT:123,220 triggers y rollback | **S/M PASS en alcance PC**, mismo ID y estado vigente. No certifica traslado de sesiones de Seguimiento. |
| CA-R08 | A:355,373 y MCT:123 primera respuesta, dos aceptaciones, repetición | **S/M PASS**, salida única y un traslado. |
| CA-R09 | A:421 fallo posterior a escrituras; F:229 confirmación también revierte; MCT:220 rollback físico y reintento | **S/M PASS**, turno/oferta/reserva/salida/auditoría y revisión revertidos; confirmación única tras recuperar. |
| CA-R10 | A:115,355 libera origen sin intención; F:103,142,185,207,229,293 pantallas/consultas/correo/fallos; MCT:176,188 y T/B | **S/M/T/B PASS local**. Turno 5 Pendiente en destino, ausente en origen y una confirmación local; no hay nueva intención por ese turno. |
| CA-R11 | A:441 secuencial; MCT:147 dos procesos, órdenes forzados y snapshot profesional actualizado | **S/M PASS** en ambos órdenes, detalle debajo. |
| CA-P01 | E:97 cancelación con búsqueda/correo pendientes; MCT:310 medición de Action y ausencia de evaluación/oferta/envío | **S/M PASS**: 20,328 ms en M, intención pendiente y 0 evaluaciones/ofertas/correos para esa cancelación. |
| CA-P02 | MCT:268, rama búsqueda, diez muestras por volumen | **M PASS bajo el perfil definido**, máximo 133,167 ms; no certifica numerosos candidatos descartados ni carga productiva. |
| CA-P03 | MCT:268, consulta y traslado medidos por separado | **M PASS bajo el perfil definido**: consulta máximo 30,220 ms y traslado máximo 68,265 ms; sin HTTP/SMTP dentro de la medición. |

La tabla tiene **30 entradas**: 28 con comprobaciones locales aplicables y dos
exclusivas de Seguimiento diferidas. Las reservas de canal, navegador y perfil
de rendimiento forman parte del dictamen; no se convierten esos 28 en una
declaración de cierre integral.

## Las ocho dependencias

| Dependencia | Estado y evidencia del primer incremento | Trabajo que permanece fuera o pendiente |
| --- | --- | --- |
| DEP-01 Cancelación/intención | C, E y MCT:80/310: cancelación efectiva, auditoría durable, 12 h históricas, consumo único recuperable; S/M PASS | Cancelación profesional descrita en el UC sin punto de entrada implementado; requerirá integración explícita cuando exista. |
| DEP-02 Agenda/ocupación | V/O y MCT:92/147/167/202: validador compartido, paciente sin filtro por K/tipo, reservas, disponibilidad y alta, bloqueos/configuración; S/M PASS | No habilita equipos/plan de Seguimiento. Ensayos exhaustivos de cargas y todas las combinaciones de fallos siguen fuera de la evidencia. |
| DEP-03 Equipos | SCT:77 impide seleccionar Seguimiento | **Diferida**, CA-G08 no aplicable a PC. No se presume que requisitos desconocidos equivalgan a ausencia de equipos. |
| DEP-04 Planes/sesiones | SCT:77 y conservación de datos PC en A/F | **Diferida**, CA-G09 y fragmentos de R06/R07 para Seguimiento no implementados. |
| DEP-05 Traslado/concurrencia | A/X/F y MCT:123/147/167/202/220: operación única, primera respuesta, rollback, K → P → T y ambos órdenes CA-R11 | No certifica todas las carreras posibles, deadlocks/timeouts, caídas de proceso en toda frontera ni producción. |
| DEP-06 Enlaces/vigencia | X/A por HTTP y regresión de enlaces de asistencia; S PASS, expiración concurrente M, recorrido manual HTTPS B | Atributos/CSRF comprobados por cliente HTTP; sin inspección de DevTools ni revisión exhaustiva de logs de proxy acreditadas. |
| DEP-07 Correo/Auditoría | E/X/F, preparadores/despachadores concurrentes M y captura SMTP local T | SMTP externo, recepción/lectura reales, operación de claves y resolución operativa de resultados inciertos. |
| DEP-08 Mis turnos/agenda/recordatorios | F:103 recorre rutas autorizadas; A:382 invalida enlace anterior; regresión de recordatorios S; reflejo visual manual B confirmado | Flujos profesionales/clínicos externos y operación sostenida conservan sus límites previos. |

## MariaDB aislada: identidad, preparación y concurrencia

Antes de DDL se verificaron mediante TCP explícito **VERSION 10.11.19-MariaDB,
puerto 3308**, directorio
`C:/xampp/htdocs/Proyecto2026/KinesioSoft/storage/framework/testing/agenda-mariadb-639-instancia/data/`,
REPEATABLE READ y esquema nuevo `adelantos_09_sandbox`, **inexistente** antes de
crearlo. No se conectó a `kinesiosoft_db` ni a 3307. Se reutilizó exclusivamente
el servidor descartable detenido de 6.39, con proceso nuevo; no su esquema
histórico ni su wrapper con PID fijado. El aprovisionamiento creó sólo el esquema
nuevo y dos cuentas limitadas a él. Las contraseñas y APP_KEY están en CLIXML
DPAPI CurrentUser ignorado, no en argumentos ni en este informe.

Después de preparar: **58 migraciones**, **59 tablas** contando la marca RUN y
**4 triggers**. Estado acumulado después de la corrida final 8: **4191 turnos**
sintéticos (3366 al terminar la 7); incluye fixtures conservados de todas las
corridas y no representa el volumen de una sola prueba. `cierre-final/resumen-mariadb.json` conserva la identidad del runner,
DDL/filas; `identidad-antes-de-migrar.json` conserva el preflight previo a DDL.
El runner carece de permisos DDL; cada conexión/consulta verifica su destino.
No hay `.env`, cachés ni storage ordinarios compartidos con el ensayo.

Las barreras paran al ganador **después de obtener** el lock K. El segundo
proceso publica que va a ejecutar su SELECT FOR UPDATE, espera al menos 250 ms
y sólo entonces se libera el primero. Las pruebas exigen PID y CONNECTION_ID
distintos y una espera medida mayor de 200 ms. Los JSON `carrera-*.json` y
barreras quedan bajo `storage/framework/testing/adelantos-mariadb-09/<RUN>/<PID>/`.

Cobertura M definitiva:

- Dos consumidores de una intención: misma evaluación, una oferta y un despacho.
- Activaciones incompatibles de la misma intención y de **dos cancelaciones con
  intervalos superpuestos**, con candidatos distintos: sólo una reserva.
- Reserva → alta y alta → reserva con horario antes libre: el perdedor no ocupa
  de nuevo el intervalo; se comprueba el resultado final.
- Aceptar → rechazar, rechazar → aceptar y aceptar → aceptar: una salida y un
  traslado, o ninguno si ganó el rechazo.
- Vencer → aceptar y aceptar → vencer, con el instante exacto de vencimiento en
  el worker de cierre: se conserva el resultado que corresponde.
- Anular configuración → aceptar y aceptar → anular configuración: revalidación
  bajo el mismo mutex, conservando el turno según el primer resultado válido.
- Dos preparadores de confirmación recuperan la misma identidad; dos
  despachadores invocan **una sola vez el transporte simulado** entre ambos.
- Error inyectado en auditoría después de las escrituras del traslado: rollback
  real de programación, revisión, oferta/salida/reserva/auditorías; repetición
  posterior correcta con una confirmación lógica.
- UNIQUE efectivo (error 1062), FK efectivo (1452), precisión temporal real de
  activación/vencimiento a seis decimales y horario de Turno a segundos. Triggers
  de revisión presentes y aumento/reversión comprobados.

**CA-R11, evidencia de la corrida definitiva:**

| Orden forzado | Sesiones independientes | Espera del perdedor | Estado final |
| --- | --- | --- | --- |
| Bloqueo → aceptación | 32 / 33 | 700,66 ms | Oferta `cerrada_agenda`; reserva resuelta; original 23/11 sin mover; cero turnos existentes afectados al revisar el bloqueo. |
| Aceptación → bloqueo | 29 / 30 | 397,89 ms | Oferta `aceptada`; turno trasladado al 16/11, Pendiente; bloqueo advierte **1 turno existente afectado** y conserva su programación. |

En el segundo orden, la revisión preliminar del bloqueo quedó obsoleta por el
traslado. Agenda rechazó el snapshot anterior: se acredita esa señal y el worker
vuelve a revisar/registrar con la advertencia actualizada. No se debilitó la
protección de revisión ni se canceló el turno aceptado.

Historial del arnés nuevo: la primera corrida detectó ese snapshot obsoleto;
se corrigió la secuencia de la prueba. Otra corrida detectó una constante
inexistente en la aserción de estado del despacho; se corrigió a `ENVIADO`.
Los refuerzos de DDL, revisión y cupos superpuestos quedaron incluidos en la
corrida 7, conservada como antecedente. La **corrida 8 definitiva** repite esos
veinte casos sobre la versión final bajo manifiesto. No se modificó código
productivo para hacer pasar estas pruebas y no se suman corridas parciales.

## Rendimiento

Windows 10.0.19045, cuatro procesadores lógicos visibles, PHP 8.2.12 sin opcache
CLI, MariaDB 10.11.19 local, InnoDB, REPEATABLE READ, buffer del arnés de 128 MB.
Una operación por vez, sin tráfico HTTP/SMTP concurrente; fixtures/bootstrap
excluidos. El cron/worker no interviene. Se incluye el coste PHP + DB y las
guardas de identidad por consulta; no sólo la suma de tiempos SQL.

Cada profesional tiene **31, 151 o 601 turnos**: cancelado, un candidato PC y
29/149/599 históricos finalizados fuera de ventana. Hay **un candidato en
ventana por escenario**, identificado expresamente para no confundir volumen
histórico con candidatos examinados. Diez búsquedas y diez consultas por
escenario, primera muestra incluida, y un **traslado real nuevo** por escenario
(no repetición idempotente). Las cotas se comprueban con aserciones `<2000 ms`.

| Turnos por profesional | Búsqueda: media / máximo, ms (n=10) | Consulta: media / máximo, ms (n=10) | Reprogramación, ms (n=1) |
| --- | --- | --- | --- |
| 31 | 8,016 / 10,668 | 6,944 / 22,784 | 68,265 |
| 151 | 8,326 / 12,906 | 5,237 / 7,201 | 48,261 |
| 601 | 25,430 / 133,167 | 8,985 / 30,220 | 50,810 |

CA-P01: cancelación medida separadamente **20,328 ms**; al retornar tiene una
intención pendiente, cero evaluaciones/ofertas y ningún correo/Job enviado por
esa cancelación. La búsqueda y entrega se ejecutan después. S verifica además
ambos canales y el comando que encola referencias pendientes.

Los JSON en `cierre-final/` conservan todas las muestras definitivas; los de la
corrida 7 permanecen en `cierre-final/historico-corrida-7/`. CA-P02/P03
quedan acreditados **sólo bajo este perfil local serial**, sin certificación de
carga fría, múltiples solicitudes concurrentes, muchos candidatos rechazados,
percentiles productivos ni latencia del navegador/SMTP. Esas mediciones más
amplias requieren definir el perfil operativo; no se infieren del PASS local.

## Navegador, HTTPS y SMTP local

Se preparó un entorno **nuevo** bajo `.../asignacion-cierre-20261117-09/web/`,
SQLite exclusivo con PDO MySQL deshabilitado y storage/cachés/sesión propios.
Apache separado, limitado a loopback en `https://localhost:8443`, reutilizando
el certificado local ya existente; no se modificó la confianza del sistema.
Correo dirigido exclusivamente al Mailpit existente en SMTP `127.0.0.1:11025`,
API/UI `127.0.0.1:18025`, sin relay externo. Sólo datos y direcciones ficticias
`@example.test`. Reloj del proceso congelado al **22/11/2026 12:00 -03**, oferta
hasta 14:00, cupo 23/11 10:15–11:00 y candidato 30/11 10:00–10:30.

Computer Use mostró en Chrome las dos ofertas capturadas con horarios,
profesional y vencimiento. No se obtuvo una captura gráfica válida: el helper
falló al capturar ventanas. Finalmente el control detuvo la automatización por
no poder determinar con suficiente confianza la URL actual en Windows. No se
continuó la automatización ni se eludió ese control mediante otro navegador.

**Pendientes al interrumpirse Computer Use:** abrir el enlace de correo bajo HTTPS,
consulta sin efectos, inspeccionar cookie Secure/HttpOnly/SameSite/Path y
formularios POST sin token original, aceptar, comprobar estado Pendiente y mismo
ID en Mis turnos/agenda con origen ausente, ver confirmación capturada, rechazar
otra oferta y revisar logs HTTP/proxy. No se atribuyen esos resultados a los
tests HTTP de Laravel ni al HTML de las pruebas. Hará falta recuperar el acceso
fiable al navegador y preparar dos ofertas ficticias nuevas. El recorrido
funcional se resolvió después por el usuario con el lote 02, como se registra
al final. La inspección de DevTools y la revisión exhaustiva de logs no se
atribuyen a su confirmación.

Para completar **sólo el ensayo de transporte**, se ejecutaron por CLI las
Actions sobre esos datos, fuera de un navegador: aceptación del turno ID 1 a
23/11 10:15–10:45, estado **PENDIENTE**, y rechazo de la otra oferta conservando
30/11 10:00. La confirmación se despachó después del commit y se invocó de nuevo.
Mailpit capturó exactamente **dos ofertas y una confirmación** para esos
destinatarios. La confirmación contiene nuevo horario, profesional y Pendiente,
sin token ni motivo. Estado técnico ENVIADO significa aceptación por este SMTP
local, no recepción/lectura en un buzón externo. Esta ejecución CLI **no acredita
el recorrido B pendiente**.

## Límites del cierre local y verificaciones operativas pendientes

1. **Recorrido funcional manual resuelto por el usuario:** se confirmó aceptación,
   rechazo, resultado, Mis turnos, agenda y correo del lote 02. Se conservan como
   límites la falta de captura gráfica/DevTools y de una revisión exhaustiva de
   logs HTTP/proxy; los atributos de cookie y CSRF se verificaron por HTTP real.
2. **Límite del alcance:** candidatos Seguimiento y equipos/planes/sesiones no
   implementados; deberán incorporarse y verificarse en otro incremento.
3. **Discrepancia documental/integración externa:** el UC menciona cancelación
   profesional; no hay flujo profesional implementado que produzca la intención.
   Sólo se certifican los dos canales implementados y aprobados en el subpaso 5.
4. **Rendimiento operativo:** ampliar el perfil si se pretende certificar carga
   concurrente o muchos candidatos descartados. Las cotas locales de esta puerta
   sí pasaron bajo los volúmenes/carga expresamente registrados.
5. **Operación:** SMTP externo, entrega/lectura, activación sostenida de Scheduler
   y workers, gestión/rotación de claves, logs y resultados inciertos continúan
   pendientes de su ensayo operativo. No hay promesa de entrega exactamente una vez.
6. **Límite de concurrencia:** se acreditan las carreras enumeradas; no una prueba
   exhaustiva de deadlocks/timeouts o caídas en todas las fronteras de transporte.
   Se conservan los tratamientos conservadores y evidencia funcional de S.

## Inventario y controles de revisión

Este subpaso agrega **nueve archivos** del arnés versionable en
`tests/Integration/AdelantosMariaDb09/`: bootstrap, phpunit.xml, preparar,
worker, CicloConcurrenteTest, README y tres clases Support. Modifica únicamente
el seguimiento de `AGENTS.md` y agrega este informe dentro de documentación.
No cambia código productivo ni pruebas de los subpasos anteriores. Los helpers,
perfiles DPAPI, datos, SMTP capturado, logs, JUnit y métricas permanecen ignorados
en storage; no se agregan reglas de exclusión.

Sintaxis de los **siete PHP nuevos**: PASS. Pint `--test` del directorio nuevo:
PASS, salida 0. Build definitivo `npm run build`: **PASS, 96 módulos, 4,09 s**,
salida 0, `cierre-final/build.log`, sobre las fuentes bajo manifiesto. La corrida
anterior de 4,81 s se conserva: su primer intento falló con spawn EPERM en el
sandbox. El build final usó la ejecución autorizada fuera de él, sin repetir ese
fallo conocido. No se instalaron dependencias ni se corrigió deuda ajena.
El build ignorado conserva el aviso previo de Browserslist, sin fallo.

Se detuvo MariaDB descartable al terminar, conservando sus datos y el esquema
histórico de 6.39. **Apache y Mailpit permanecen disponibles para el recorrido
manual**: no se cerrarán hasta que el usuario confirme que terminó. La solicitud
anterior para detener Apache fue rechazada y no se ejecutó. El subpaso 9 continúa
pendiente de la evidencia manual y de revisar las demás reservas; este documento
no constituye su cierre definitivo.

## Preparación del recorrido manual — lote 02

Se agregaron dos ofertas **nuevas**, sin reabrir las ofertas 1/2 resueltas por CLI.
Las ofertas, despachos, salidas, confirmaciones y turnos anteriores se conservaron
por comparación de hashes de sus filas. Se conservaron los correos y logs previos;
Mailpit pasó de seis a ocho mensajes al agregar únicamente los dos correos nuevos.
No se reiniciaron servicios, se limpiaron datos ni se ejecutaron migraciones.

**Estado al entregar este lote: ambas ofertas activas, vigentes y SIN RESPONDER.
El recorrido por Chrome lo realiza el usuario y continúa PENDIENTE.** Esta
preparación no se registra como una aceptación, un rechazo ni una verificación
manual satisfactoria.

Mailpit: `http://127.0.0.1:18025`. Login web: `https://localhost:8443/login`.
Todos los usuarios siguientes son ficticios y tienen la contraseña de factory
`password`. Es necesario cerrar sesión al cambiar entre paciente y profesional.

| Acción manual | Oferta / turno | Destinatario del correo y cuenta paciente | Cuenta de la kinesióloga |
| --- | --- | --- | --- |
| Aceptar | Oferta **3**, turno **5** | `manual-02-aceptar@example.test` | `manual-02-kine-aceptar@example.test` |
| Rechazar | Oferta **4**, turno **7** | `manual-02-rechazar@example.test` | `manual-02-kine-rechazar@example.test` |

Ambos mensajes tienen asunto **Oferta de adelanto de turno — KinesioSoft**.
Se distinguen por sus destinatarios `manual-02-*`, no por los correos anteriores
`paciente-*`. Los enlaces seguros se obtienen dentro de esos correos; no se
copian credenciales ni URLs con token a este informe.

| Recorrido | Antes de la acción, en Mis turnos y agenda | Después de la acción esperada |
| --- | --- | --- |
| Aceptar | Mismo turno **5**, Primera consulta, **30/11/2026 10:00–10:30, Pendiente** | Turno **5**, **23/11/2026 10:15–10:45, Pendiente**. Debe desaparecer del horario anterior. La pantalla confirma el cambio de horario y conserva Pendiente. |
| Rechazar | Mismo turno **7**, Primera consulta, **30/11/2026 10:00–10:30, Confirmado** | Turno **7** conserva **30/11/2026 10:00–10:30, Confirmado**. No aparece trasladado al 23/11. |

El horario propuesto en ambas ofertas es **23/11/2026 10:15–10:45**; el cupo
reservado completo termina a las 11:00. El cupo de la oferta 3 procede de un
Seguimiento cancelado y el de la 4 de una Primera consulta cancelada; ambos
candidatos son Primera consulta con su misma kinesióloga. No confundir en agenda
el turno cancelado que liberó el cupo con el ID del candidato.

El reloj **exclusivo del ensayo** sigue congelado al **22/11/2026 12:00 -03**.
Ambas ofertas vencen el **22/11/2026 14:00 -03** y están vigentes bajo ese reloj;
no se modificó el vencimiento de ofertas existentes ni la duración funcional de
dos horas. La pantalla debe mostrar ese vencimiento exacto.

Se inició un consumidor local de confirmaciones, con sondeo cada **dos segundos**,
limitado a las ofertas 3/4. No acepta ni rechaza, no consume otras intenciones y
no despacha resultados del lote anterior. Al encontrar una salida ACEPTADA
confirmada, ejecuta el `EnviarConfirmacionAdelantoJob` existente, con sus servicios
de preparación y despacho; no agrega SMTP al POST ni envía antes del commit.
La identidad durable de la confirmación evita repetir su despacho lógico.
Se mantiene activo junto con Apache y Mailpit hasta la confirmación del usuario.

Tras aceptar, debe llegar a `manual-02-aceptar@example.test` un mensaje con asunto
**Cambio de horario confirmado — KinesioSoft**, nuevo horario 23/11 10:15–10:45,
su profesional y estado conservado Pendiente. El rechazo no debe generar correo
de confirmación de traslado. Para la inspección visual de Mis turnos y agenda,
consultar las fechas 23/11 y 30/11 con las cuentas indicadas.

Evidencia nueva, ignorada por Git:

- `storage/framework/testing/asignacion-cierre-20261117-09/web/manual-02-preparacion.log`.
- `.../web/manual-02/lote.json`, sin credenciales del enlace.
- `.../web/manual-02/preservacion.json` y `correos-preparados.json`.
- `.../web/manual-02/confirmaciones-listo.json`, PID y logs del consumidor.

Los auxiliares `manual-02-preparar.php` y `manual-02-confirmaciones.php` están
fuera del código productivo y permanecen ignorados. No se repitieron regresiones,
build ni ensayos MariaDB durante esta preparación. Los resultados anteriores
mantienen su evidencia; las cookies, formularios, aceptación/rechazo, reflejo en
pantallas y lectura del correo quedan pendientes de lo que informe el usuario.

## Corrección del login 419 en el entorno web aislado

El usuario reprodujo 419 en Chrome, incluso en incógnito, usando
`https://localhost:8443/login` y la cuenta ficticia del lote 02. Se reprodujo
independientemente con un cliente HTTP sobre **ese Apache HTTPS real**, mediante
GET `/login` → POST `/login` con el mismo almacén de cookies, sin seguir
redirecciones automáticamente. Se verificaron certificado y hostname mediante
la CA local existente, sin deshabilitar TLS. No se realizó ninguna acción sobre
las ofertas ni se atribuye esta comprobación HTTP a un recorrido manual en Chrome.

**Causa comprobada:** el GET contenía el campo CSRF y la cookie de sesión era
válida y reenviable. Laravel recibía la misma identidad de sesión en el POST,
pero `FileSessionHandler::read()` descartaba los datos porque compara el mtime
del archivo con `Carbon::now() - lifetime`. El archivo recién guardado y
escribible tenía fecha real del **17/11/2026**, mientras Carbon compartía el
reloj congelado del **22/11/2026 12:00 -03**. Con 120 minutos de vigencia, el
archivo quedaba fuera de ventana inmediatamente. Se regeneraba el token al
cargar una sesión sin datos y el POST terminaba correctamente rechazado con 419.
`CarbonImmutable::setTestNow()` comparte su reloj con Carbon mutable en la versión
local; por eso también afectaba a las sesiones de Laravel.

No se encontró un fallo de permisos, ausencia del token, cambio de hostname ni
pérdida de cookie por atributos. Configuración efectiva observada: cookie
`kinesiosoft_adelanto_09`, **Secure**, **HttpOnly**, SameSite **Lax**, path `/`,
sin Domain explícito (host-only), lifetime **120 minutos**.

**Corrección aplicada sólo al ensayo:** `web/perfil.php` usa ahora el backend
estándar `database` de sesiones, conexión `sqlite`, tabla `sessions` ya existente
en el archivo aislado. Su `last_activity` y su comparación de vencimiento usan
el mismo reloj Carbon; no dependen del mtime real del archivo. Se fijaron
explícitamente duración, path y HttpOnly y se actualizó la guarda de
`web/bootstrap-aislado.php` para exigir ese destino. No hubo migración,
reemplazo/limpieza de SQLite, modificación de vendor, del código de aplicación,
de `config/session.php` o de `.env`, ni exclusión de CSRF. Los archivos de
sesiones anteriores permanecen conservados. No fue necesario reiniciar Apache.

| Verificación HTTPS real | Resultado |
| --- | --- |
| Antes: GET con formulario CSRF → POST, cookie conservada/reenviada | **200 → 419**; handler no recuperaba los datos recién guardados; mtime fuera de ventana. |
| Después: GET → POST válido → Mis turnos | **200 → 302 a `/dashboard` → 200**; usuario autenticado y horario original visible. |
| Repetición con almacén de cookies nuevo | **200 → 302 → 200**, mismo resultado. |
| POST con CSRF válido y datos de formulario inválidos | Redirección de validación a login; se comprobó **misma identidad de sesión y mismo token recuperado** antes de una autenticación. |
| POST con token incorrecto, cookie válida | **419**, protección CSRF conservada. |
| POST sin cookie, aunque lleva el token del GET | **419**, protección CSRF conservada. |

La autenticación correcta regenera identificador y token de sesión, como exige
el flujo existente; esa rotación posterior al login no se interpreta como pérdida
de la sesión previa. La prueba de validación sin autenticación acredita la
recuperación previa sin esa rotación.

Evidencia ignorada bajo `storage/framework/testing/asignacion-cierre-20261117-09/web/`:

- `diagnostico-login-cliente-{antes,despues,repeticion,validacion,negativo,sin-cookie}.json`.
- `diagnostico-login-servidor.jsonl`: sólo atributos, estados y comparaciones
  booleanas; **sin valores de cookies/tokens, contraseñas ni IDs de sesión**.
- `login-preservacion-{antes,despues}.json` y copias previas del perfil/preflight.
- Cliente `diagnosticar-login.cjs` y observador `login-diagnostico.php`, limitados
  al ensayo; el observador sólo se activa para el encabezado del cliente de diagnóstico.

Se compararon hashes de todas las filas de turnos, ofertas, despachos, salidas,
confirmaciones, intenciones y evaluaciones: **sin cambios**. Sólo se agregaron
sesiones de diagnóstico y la auditoría habitual de los logins satisfactorios.
Las ofertas **3 y 4 continúan activas, vigentes y sin respuestas**, los candidatos
conservan su horario/estado original y los correos/logs anteriores se preservan.
Apache, Mailpit y el consumidor de confirmaciones del lote 02 siguen disponibles.
El reloj funcional no se adelantó ni se extendieron ofertas: se conserva la
congelación ya documentada. El recorrido manual por Chrome y el cierre de 9
**continúan pendientes** hasta recibir el resultado del usuario.

## Registro final del resultado manual y cierre local

**El 17/11/2026 el usuario informó que terminó el recorrido manual en Chrome.**
Este registro actualiza las menciones de pendiente/servicios disponibles de los
apartados históricos anteriores. No es una nueva inspección visual del agente
ni una certificación de producción.

| Caso manual confirmado por el usuario | Resultado observado por el usuario | Corroboración posterior de sólo lectura |
| --- | --- | --- |
| Oferta **3**, aceptar desde el enlace de Mailpit | Pantalla de resultado, Mis turnos, agenda y confirmación muestran turno **5**, **23/11/2026 10:15–10:45, Pendiente**. El turno no figura en su horario anterior al consultar la agenda del **30/11**. | Oferta `aceptada`, salida única con estado conservado `PENDIENTE`, mismo turno 5 y duración de 30 minutos; una confirmación `ENVIADO` y un correo en Mailpit al aceptante. **Cero intenciones por el turno candidato 5**. |
| Oferta **4**, rechazar desde el enlace de Mailpit | Pantalla confirma rechazo. Mis turnos y agenda conservan turno **7**, **30/11/2026 10:00–10:30, Confirmado**. El destinatario `manual-02-rechazar@example.test` no recibió correo de cambio de horario. | Oferta `rechazada`, salida única y turno 7 intacto; sin despacho de confirmación para esa salida y sin mensaje de cambio al rechazante en Mailpit. |

El consumidor exclusivo de confirmaciones registra un único despacho del lote:
`salida_id=3`, `oferta_id=3`, `estado_despacho=ENVIADO`. Se conservaron los nueve
mensajes de Mailpit, incluyendo antecedentes, ofertas nuevas y confirmación.
La evidencia de sólo lectura está en `cierre-final/estado-web-manual.json`,
`cierre-final/correos-finales.json` y `web/manual-02/confirmaciones.stdout.log`.
Los enlaces y valores de cookies/tokens no se incorporan al informe.

**Límite operativo concreto:** el proceso manual sólo atendió confirmaciones.
La salida de rechazo 4 mantiene su continuación durable `pendiente`, destinada
al consumidor general; su oferta ya está cerrada y no reserva. No se ejecutó un
Scheduler/worker general sostenido para este recorrido ni se declara que el
sondeo completo haya agotado esa evaluación en este entorno. La continuación,
el agotamiento y la recuperación están cubiertos por las pruebas automáticas
localizadas en la matriz, dentro de sus entornos respectivos. No se modificó
esa evidencia para simular que el trabajo pendiente hubiera sido ejecutado.

### Correspondencia de la evidencia con la versión final

Las primeras corridas no conservaban un manifiesto completo del código. Para
confirmar esta versión final se hicieron **nuevas corridas pertinentes**, sin
sumarlas a las anteriores ni sobrescribir sus logs:

- **S final: 1190 pruebas / 8512 aserciones PASS**, salida 0, 454,857 s,
  `cierre-final/regresion.log` y `regresion.junit.xml`.
- **M final, corrida 8: 20 pruebas / 323 aserciones PASS**, salida 0, 40,764 s,
  `cierre-final/suite-8.log` y `mariadb-8.junit.xml`. Se verificaron identidad,
  puerto, datadir, base, RUN y permisos; se reutilizó el esquema descartable
  poblado sin migraciones, fresh ni reinicialización.
- **Build final: 96 módulos PASS**, salida 0, 4,09 s,
  `cierre-final/build.log`. Se ejecutó antes de M: las mediciones de M son
  seriales y no se hicieron en paralelo con esta regresión o build.

Se tomaron SHA-256 **antes y después** de **837 archivos** de `app`, `bootstrap`,
`config`, `database`, `resources`, `routes`, `tests`, manifiestos de dependencias
y configuración de pruebas/build: **cero archivos agregados, faltantes o
modificados dentro de ese conjunto**. Incluye las Actions/servicios, vistas,
rutas y migraciones del incremento, sus pruebas focalizadas y el arnés MariaDB
final. Se verificaron además las cuatro huellas de las fuentes aprobadas:
conservadas. Los manifiestos y el resultado están en `cierre-final/fuentes-antes.json`,
`fuentes-despues.json` y `correspondencia-version-final.json`.

Por ello, los PASS finales se atribuyen a las fuentes locales finales bajo
manifiesto y al HEAD base con cambios sin commit, **no a un commit inexistente**.
Después sólo se actualizó documentación. Los perfiles/helpers de ensayo,
SQLite, JUnit, métricas y logs permanecen ignorados. La corrección de sesiones
del entorno web conserva su verificación HTTPS independiente; no se presenta
la suite SQLite en memoria como prueba de ese Apache ni de Chrome.

Los resultados iniciales se conservan. Las métricas y el resumen anteriores a
la repetición están en `cierre-final/historico-corrida-7/`; las métricas finales,
cancelación y resumen real están también copiados en `cierre-final/`. Las cifras
de la tabla de rendimiento de este informe corresponden a la corrida 8 final.

### Servicios y datos conservados al terminar

Por autorización expresa del usuario se detuvieron exclusivamente los servicios
del ensayo: consumidor de confirmaciones PID **22480** por su bandera de cierre
(su log registra detención), Apache separado PIDs **8572/17268**, Mailpit local
PID **21500** y MariaDB descartable PID **23304**, iniciada sólo para la corrida
final y detenida mediante SHUTDOWN tras verificar de nuevo su identidad.
No se detuvieron el Apache del panel de XAMPP ni otros procesos ajenos.

Se conservan:

- SQLite web `storage/framework/testing/asignacion-cierre-20261117-09/web/ensayo.sqlite`,
  sesiones del ensayo, estados finales de ofertas/turnos y todos sus logs.
- Mailpit `storage/framework/testing/agenda-e2e-20261114/mailpit.sqlite`,
  **su WAL y SHM**. No copiar sólo el archivo principal para recuperar el buzón:
  la lectura SQLite posterior al cierre verifica **9 filas en mailbox y mailbox_data**.
- Directorio de datos MariaDB y ambos esquemas históricos del servidor
  descartable, sin borrar fixtures ni credenciales DPAPI conservadas fuera de Git.
- Evidencia previa completa y `cierre-final/`, con manifiestos y resultados nuevos.

**Cierre del subpaso 9: técnico local con reservas, pendiente de revisión y
versionado.** El recorrido funcional manual ya no está pendiente. Permanecen
los límites documentados de candidatos Seguimiento, cancelación profesional,
perfil de rendimiento serial con un candidato en ventana, SMTP externo,
Scheduler/workers sostenidos, operación de claves/logs y fallos/concurrencia no
exhaustivos. No se declara 30/30 sin reservas, entrega SMTP exactamente una vez,
certificación productiva ni autorización para staging/commit u otro incremento.

### Inventario final y estado Git para revisión

En esta última solicitud se actualizaron **AGENTS.md y este informe**. El código
productivo y las pruebas previas se conservaron; la nueva evidencia está ignorada
en `storage/framework/testing/asignacion-cierre-20261117-09/cierre-final/`.

El paquete acumulado desde el commit base tiene **11 archivos seguidos
modificados** (AGENTS, cinco Actions de Agenda, alta de Primera consulta,
AgendaQuery, disponibilidad y dos archivos de rutas). Los **64 archivos no
seguidos del alcance inventariado** son:

| Grupo | Cantidad | Contenido |
| --- | --- | --- |
| app | 30 | Actions de activación/aceptación, comando, controlador/middleware, Jobs, correo, modelos y servicios de adelanto. |
| database | 5 | Migraciones aditivas del incremento. |
| resources | 3 | Pantalla de oferta y dos correos Blade. |
| tests | 17 | Siete pruebas Feature, soporte SMTP y nueve archivos del arnés MariaDB (siete PHP, XML y README). |
| docs | 9 | Cuatro documentos aprobados conservados y cinco informes de implementación 5–9. |

No se contabiliza ni inspecciona el contenido de `docs/archivo/`; se conserva su
entrada sin seguimiento. La lista expandida del alcance autorizado está en
`cierre-final/inventario-no-seguidos.txt`. Que un archivo figure como no seguido
no significa que se haya creado en esta última solicitud.

Comprobaciones finales: sintaxis de los siete PHP del arnés y Pint `--test`
satisfactorios, salida 0. `git diff --check`: **sin salida, código 0**. Índice
sin diferencias (`git diff --cached --stat` sin salida). Sin staging ni commit.
837 fuentes bajo manifiesto y las cuatro fuentes aprobadas vuelven a coincidir
tras las comprobaciones. Ningún proceso del ensayo permanece activo; los puertos
8443, 11025, 18025 y 3308 rechazan conexiones. Estado en `procesos-finales.json`
y `puertos-finales.json`, y datos/correos/logs conservados.

`git diff --stat` (sólo seguidos; no incluye los 64 no seguidos):

```text
 AGENTS.md                                          | 119 +++++++++++++++++++--
 .../ActualizarConfiguracionAgendaFuturaAction.php  |   6 +-
 .../AnularConfiguracionAgendaFuturaAction.php      |   6 +-
 app/Actions/Agenda/CambiarDuracionAgendaAction.php |   6 +-
 .../Agenda/CrearConfiguracionAgendaAction.php      |   6 +-
 .../Agenda/RegistrarBloqueoAgendaAction.php        |   6 +-
 .../Turnos/ConfirmarPrimeraConsultaAction.php      |   4 +-
 app/Services/Agenda/AgendaQueryService.php         |  32 ++++++
 .../DisponibilidadPrimeraConsultaService.php       |  11 +-
 routes/console.php                                 |   6 ++
 routes/web.php                                     |   9 ++
 11 files changed, 197 insertions(+), 14 deletions(-)
```

`git status --short --branch`:

```text
## feature/turnos-asignacion-automatica
 M AGENTS.md
 M app/Actions/Agenda/ActualizarConfiguracionAgendaFuturaAction.php
 M app/Actions/Agenda/AnularConfiguracionAgendaFuturaAction.php
 M app/Actions/Agenda/CambiarDuracionAgendaAction.php
 M app/Actions/Agenda/CrearConfiguracionAgendaAction.php
 M app/Actions/Agenda/RegistrarBloqueoAgendaAction.php
 M app/Actions/Turnos/ConfirmarPrimeraConsultaAction.php
 M app/Services/Agenda/AgendaQueryService.php
 M app/Services/Turnos/DisponibilidadPrimeraConsultaService.php
 M routes/console.php
 M routes/web.php
?? app/Actions/Turnos/AceptarOfertaAdelantoAction.php
?? app/Actions/Turnos/ActivarOfertaAdelantoAction.php
?? app/Console/Commands/ProcesarCuposLiberadosCommand.php
?? app/Http/Controllers/OfertaAdelantoController.php
?? app/Http/Middleware/ProtegerAccesoOfertaAdelanto.php
?? app/Jobs/DespacharOfertaAdelantoJob.php
?? app/Jobs/EnviarConfirmacionAdelantoJob.php
?? app/Jobs/ProcesarIntencionCupoJob.php
?? app/Jobs/RevisarCicloOfertaAdelantoJob.php
?? app/Mail/ConfirmacionAdelantoMail.php
?? app/Mail/OfertaAdelantoMail.php
?? app/Models/DespachoConfirmacionAdelanto.php
?? app/Models/DespachoOfertaAdelanto.php
?? app/Models/EvaluacionAdelanto.php
?? app/Models/IntentoDespachoAdelanto.php
?? app/Models/OfertaAdelanto.php
?? app/Models/SalidaOfertaAdelanto.php
?? app/Services/Turnos/CerrarOfertaAdelantoService.php
?? app/Services/Turnos/ConsultarOfertaAdelantoService.php
?? app/Services/Turnos/ContinuarCupoAdelantoService.php
?? app/Services/Turnos/DespacharConfirmacionAdelantoService.php
?? app/Services/Turnos/DespacharOfertaAdelantoService.php
?? app/Services/Turnos/PrepararConfirmacionAdelantoService.php
?? app/Services/Turnos/PrepararDespachoOfertaAdelantoService.php
?? app/Services/Turnos/ProcesarIntencionCupoService.php
?? app/Services/Turnos/RevalidarOfertasPorCambioAgendaService.php
?? app/Services/Turnos/SeleccionarCandidatoAdelantoService.php
?? app/Services/Turnos/TransaccionOfertaAdelantoService.php
?? app/Services/Turnos/TransporteCorreoAdelantoService.php
?? app/Services/Turnos/ValidarDestinoAdelantoService.php
?? database/migrations/2026_11_16_000001_create_ofertas_adelanto_table.php
?? database/migrations/2026_11_16_000002_create_evaluaciones_y_despachos_adelanto.php
?? database/migrations/2026_11_16_000003_add_salidas_e_intentos_ofertas_adelanto.php
?? database/migrations/2026_11_16_000004_add_estado_aceptado_to_salidas_oferta_adelanto.php
?? database/migrations/2026_11_17_000001_create_despachos_confirmacion_adelanto_table.php
?? docs/archivo/
?? docs/asignacion-automatica-turnos/
?? docs/implementacion/asignacion-automatica-turnos-05-emision.md
?? docs/implementacion/asignacion-automatica-turnos-06-salidas.md
?? docs/implementacion/asignacion-automatica-turnos-07-aceptacion.md
?? docs/implementacion/asignacion-automatica-turnos-08-comunicacion.md
?? docs/implementacion/asignacion-automatica-turnos-09-cierre.md
?? resources/views/emails/turnos/confirmacion-adelanto.blade.php
?? resources/views/emails/turnos/oferta-adelanto.blade.php
?? resources/views/turnos/adelanto/
?? tests/Feature/Actions/Turnos/OfertaAdelantoTest.php
?? tests/Feature/Services/Turnos/PrimeraEmisionAdelantoTest.php
?? tests/Feature/Services/Turnos/SeleccionarCandidatoAdelantoServiceTest.php
?? tests/Feature/Services/Turnos/ValidarDestinoAdelantoServiceTest.php
?? tests/Feature/Turnos/ConfirmacionAdelantoTest.php
?? tests/Feature/Turnos/OfertaAdelantoAceptacionTest.php
?? tests/Feature/Turnos/OfertaAdelantoSalidasTest.php
?? tests/Integration/AdelantosMariaDb09/
?? tests/Support/SimularEnvioOfertaAdelanto.php
```