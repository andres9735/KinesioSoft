# Etapa 7 — Decisión de cierre local aprobada con reservas

Fecha: **15/11/2026**. **CIERRE LOCAL DE ETAPA 7 APROBADO EXPRESAMENTE POR EL USUARIO, CON LAS RESERVAS Y DEPENDENCIAS DOCUMENTADAS. Versionado pendiente de autorización.** Rama `feature/turnos-agenda-diaria-automatica`, HEAD `cd9295fe2d94a1a883b4f8d8f9f3587a6cc6f329`. El asterisco de un editor no forma parte del nombre de la rama.

Se acepta únicamente el **alcance local implementado y ensayado** de UC-05, UC-27, UC-28 y UC-29, con las reservas y dependencias siguientes como límites expresos del cierre. No se declara 114/114 cumplidos, producción certificada, consumo de cupos ejecutado ni operación permanente activada. **6.39 completado en alcance ensayado; cierre local 6.40 aprobado con reservas, pendiente de versionado autorizado.** Los nombres de archivo se conservan para no alterar el inventario.

## Requisitos de planificación y regresión sobre el árbol final

Se contrastó el DOCX congelado `docs/_codex_sources/turnos/agenda_diaria_automatica/Planificacion Agenda Diaria Automatica(1).docx`, sin modificarlo. Referencias verificadas en su TXT auxiliar: **UXP-12, líneas 3043–3065**, y **UXP-16, líneas 3114–3128**. El original exige compatibilidad de componentes nuevos con UC-06, UC-07, Configuración de Agenda, autorización, auditoría y ocupación; diferencia evidencia actual de certificaciones históricas. UXP-16 ubica integración completa, regresión global, comprobación de criterios y cierre formal en la Etapa 7.

**La planificación exige regresión global en la Etapa 7, pero no contiene una regla literal de repetir toda la suite después de cada cambio ni una prohibición general de reutilizar evidencia.** Tampoco contiene una dispensa que permita presentar una corrida anterior como ejecutada sobre código posterior. No se atribuye a la fuente un mandato textual de repetición que no tiene.

La global **3504/23754**, salida 0 con una omisión SMTP opt-in, es anterior al aviso interno. Las regresiones posteriores **337/3132** y **23/218** se conservan por separado. **A pedido del usuario se ejecutó ahora la global ordinaria sobre el árbol funcional actual: 3514 pruebas / 23876 aserciones, 0 fallos, 0 errores, 1 omisión SMTP opt-in, salida 0.** Incluye el aviso interno y la referencia del turno. No se suman conjuntos.

**Recomendación anterior cumplida:** la nueva global cubre el árbol funcional actual y elimina el pendiente de falta de una global completa posterior al aviso. No hace falta repetirla por las actualizaciones documentales posteriores mientras el contenido ejecutable permanezca idéntico. La aprobación del cierre local proviene de la decisión del usuario registrada abajo; la global no resuelve las dependencias externas.

Se verificaron las **843 huellas de entrada sin cambios durante PHPUnit**; posteriormente sólo se actualizan este documento, 6.40 y la matriz. La evidencia de la corrida actual es propia, no una aceptación del límite de cobertura de la global anterior. Los contratos externos y las reservas operativas permanecen pendientes de resolución, **aceptados como límites del cierre local**. No se ejecutaron scheduler ni workers operativos, SMTP real ni bases habituales.

Perfil efectivo comprobado antes de PHPUnit: SQLite `:memory:` por PDO/PRAGMA, único PDO SQLite y PDO MySQL deshabilitado también en hijos, datos sintéticos, correo `array`, storage/vistas/cachés exclusivos y salida/JUnit nuevos; corpus ordinario `phpunit.xml` sin filtros ni exclusión de grupos. No se reinició MariaDB 6.39 ni se accedió a 3307. La omisión corresponde a `RecordatoriosUc27CertificacionTest::test_smtp_real_mailpit_con_job_persistido_y_sin_credenciales_en_registros`: `:66–67` exige activación explícita `UC27_MAILPIT_SMTP_REAL=1`; se fijó **0** por el alcance autorizado. Se conservan los ensayos previos de Mailpit, incluido **2 → 3 → 3**, sin nuevo envío.

Comando reproducible, desde la raíz, con configuración limitada al proceso:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/agenda-etapa7-global-final-20261115/verificar.ps1 -Fase global
# Invocación efectiva, después de preflight e INI aislado para hijos:
& C:/xampp/php/php.exe -n -d extension_dir=C:/xampp/php/ext -d extension=pdo_sqlite -d extension=mbstring -d extension=fileinfo -d extension=openssl -d variables_order=EGPCS -d memory_limit=512M vendor/bin/phpunit -c phpunit.xml --do-not-cache-result --colors=never --fail-on-risky --fail-on-warning --log-junit storage/framework/testing/agenda-etapa7-global-final-20261115/global.junit.xml
```

**15/11/2026, 16:07:51–16:21:29 hora argentina**; duración del runner **13:35.622**, memoria **474 MB**, PHP 8.2.12 / PHPUnit 11.5.55, **206 clases**. Registros bajo ese directorio nuevo: `global.log`, `global.junit.xml`, `salida.json` y `resultado-verificado.json`. No se sobrescribieron corridas anteriores.

## Evidencia disponible, sin sumar conjuntos

| Evidencia | Resultado y alcance |
| --- | --- |
| **Global PHP actual, posterior al aviso y a la referencia del turno** | **3514/23876**, **0 errores / 0 fallos / 1 omitida**, salida **0**. **3513 aprobadas y una omitida por SMTP opt-in**. `storage/framework/testing/agenda-etapa7-global-final-20261115/global.junit.xml`. |
| Global PHP anterior al aviso | **3504 pruebas / 23754 aserciones**, 0 errores/fallos, 1 omisión SMTP opt-in, salida **0**. `storage/framework/testing/agenda-etapa7-20261115/global.junit.xml`. |
| Regresión de la ampliación del aviso | **337/3132**, 0 errores/fallos/omisiones, salida **0**. Incluye avisos 10/96. `storage/framework/testing/ca27-26-revision-20261115/regresion-afectada.junit.xml`. |
| Referencia del turno y navegación, posteriores | **23/218**, 0 errores/fallos/omisiones, salida **0**; avisos 10/114 dentro del mismo conjunto. `storage/framework/testing/ca27-26-turno-20261115/guardas.junit.xml`. |
| JavaScript / build | **215 PASS** / **96 módulos PASS**, salidas **0**, según 6.40. Los cambios posteriores del aviso no alteraron JavaScript ni CSS; no se declara un nuevo build por esta propuesta. |
| 6.39 completa | **25/321**, 0 errores/fallos/omisiones, salida **0**, MariaDB 10.11.19 descartable 127.0.0.1:3308. `logs/suite-1.junit.xml` bajo `storage/framework/testing/agenda-mariadb-639-instancia/`. |
| Refuerzo EI-08 con worker final | **2/40**, 0 errores/fallos/omisiones, salida **0**, separado de la completa; sólo añadió observación opcional del solapamiento EI-08. `logs/ei08-1.junit.xml` del mismo arnés. No atribuir la completa a la versión posterior del worker. |
| Revisión visual del usuario | UC-05: modal blanco escritorio/angosto y cierres/foco/contexto registrados en 6.40. CA-27-26: escritorio y **360 px**, único aviso Turno #27026 con motivo/fecha legibles, contador **1** observado en escritorio. |
| Ensayo interno del aviso | SQLite nueva, SMTP simulado 550, replay sin duplicado, un intento/un aviso, Turno Pendiente intacto y login/alertas/CSS HTTP 200. Servidor **8091 detenido**, PID verificado; artefactos conservados. `storage/framework/testing/ca27-26-demo-20261115/`. |

Los cinco JUnit anteriores permanecen conservados y se añade el de la nueva ejecución. La comparación con la global anterior explica **+10 pruebas / +122 aserciones**: avisos 10/114 y ocho aserciones adicionales de los recorridos de vistas de `GlobalConfirmationTest` y `GlobalFlashMessageTest`, cuatro por cada uno al incluir la nueva Blade. Los resultados no se suman. Las filas de los **114 criterios, 18 dependencias y 10 escenarios** y su clasificación permanecen: **99 CA locales, 8 con reserva, 7 con dependencia externa**.

La validación documental posterior cotejó **107 referencias de métodos** (104 previas y tres de avisos) contra los JUnit satisfactorios de esta global y de MariaDB conservados; **114/18/10** filas únicas, salida **0**. Registro: `storage/framework/testing/agenda-etapa7-global-final-20261115/validacion-matriz.json`. No ejecutó pruebas ni bases de datos.

## Reservas y dependencias aceptadas como límites del cierre local

| Reserva / criterio afectado | Evidencia disponible | Qué falta | Tratamiento aceptado para cierre local |
| --- | --- | --- | --- |
| R1 — **CA-29-01, DEP-13** | Evento original no debido 06:59, debido 07:00, no debido 07:01; reloj de proceso. Scheduler real con horario temporal en copia aislada, una generación y turnos intactos. | Observación operativa de UC-29 a las **07:00 reales**, con horario original y entorno operativo. | Reserva operativa aceptada expresamente para este cierre local. Necesaria antes de afirmar esa operación real; no invalida las dos evidencias locales existentes. |
| R2 — **CA-28-32, DEP-10, EI-08** | Cancelación, intervalo libre, auditoría e intención durable únicas; portal→enlace y enlace→portal con solapamiento real MariaDB, esperas SQL aproximadas de 455 ms. | Consumidor conectado y procesamiento efectivo e idempotente de la intención; candidatos/ofertas no implementados aquí. | Dependencia de **Asignación automática de turnos**. Corte hasta intención única aceptado; siguen sin acreditarse evaluación efectiva ni EI-08 integral. Regla de cancelación: al menos 12 horas corridas, incluido el límite exacto. |
| R3 — **CA-27-13, CA-29-08, CA-05-09, DEP-09** | Se evita inventar equipos, asignaciones o reservas; consulta/contenido conservan el alcance existente. | Vinculación Turno–equipo efectiva y su autorización/datos en correo, generación y consulta. | Dependencia externa aceptada para este cierre acotado; resolver en el incremento responsable de equipos. |
| R4 — **CA-05-23/24; CA-27-21, CA-28-26, CA-29-13; DEP-05–08** | Estados, programación vigente, exclusión de recordatorios y revisión monotónica/ABA probados; transiciones disponibles y lectura actual verificadas. | Destinos reales UC-08, reprogramación/cancelación profesional y recorridos integrados desde esos flujos. Los cambios sintéticos no los sustituyen. | Los dos primeros CA siguen con dependencia externa y los otros tres con reserva. Límite aceptado para el cierre local; implementar/probar contratos en sus incrementos. |
| R5 — **CA-28-23, DEP-04** | Cancelación de Seguimiento preserva atributos y otros turnos. | Integración con planes/sesiones y comprobación de que su cómputo no cambia. | Dependencia clínica externa aceptada para el cierre local; su implementación no se autoriza por inferencia y sigue pendiente de su incremento. |
| R7 — **CA-05-30/31/32, CA-29-18, UXP-12** | Mediciones sintéticas SQLite/MariaDB: preparación profesional ≤60 s, jornada ≤2 s, filtros ≤1 s, impresión/PDF ≤10 s en condiciones ensayadas. | Navegador/carga fría y representatividad productiva; evaluación de N+1/relecturas UC-27 documentados, sin cota de despacho aprobada. | Alcance medido aceptado expresamente para el cierre local. Antes de afirmar otras condiciones se requiere medirlas; no inventar un umbral ni optimizar fuera de alcance. |
| RT visual — **UXP-08/12/16; CA-05-08/11–16/25–28** | Agenda/modal y PDF sintético con evidencias previas; aviso interno revisado por el usuario en escritorio/360 px. | Revisión representativa adicional de controles nativos, iconos, filtros y descarga interactiva que no esté ya registrada; accesibilidad completa no acreditada por una inspección visual. | Cobertura concreta aceptada como límite local; otras condiciones requieren ampliar la revisión. No se exige por inferencia todos los navegadores ni impresión en papel. La pantalla de alertas ya no es un pendiente visual. |
| RT operación — **AT-10, GA-08/10, DEP-12/14** | HTTPS local, origen con puerto, cookies/CSRF/vigencia, privacidad de Referer y SMTP/Mailpit local acreditados. | Claves/rotación, logs HTTP/proxy, caché compartida, despliegue permanente y entrega/lectura externas según el entorno productivo. | Límite local aceptado, fuera de certificación productiva; verificar antes de puesta en operación. |
| Certificación del árbol funcional actual — **UXP-12/16: RESUELTA LOCALMENTE** | **Global nueva 3514/23876**, 0 errores/fallos, una omisión SMTP declarada, salida 0. Incluye aviso interno, referencia del turno y pruebas transversales. | No falta una global completa para este contenido funcional. SMTP real conserva evidencia independiente y su omisión explícita. | Pendiente resuelto por la global; las demás reservas se aceptan por decisión expresa del usuario, sin declarar producción o 114/114. Revalidar lo afectado si posteriormente cambia código. |
| Formalización — **UXP-16** | Matriz y paquete preparados; rama/HEAD y diff identificados. **Aprobación expresa del cierre local recibida el 15/11/2026.** | Revisión final del paquete y commits identificables de lo aprobado cuando se autoricen. | Esta aprobación no autoriza staging ni commit; se deja el paquete disponible para revisión. |

**R6 / CA-27-26 está resuelta localmente:** canal interno para administradores activos, aviso durable único con Turno #ID y motivo fijo, privacidad/autorización/turno intacto verificados, más revisión visual del usuario. No necesita una excepción por alerta faltante.

## Decisión expresa del usuario — 15/11/2026

Fuente: instrucción expresa en este chat: **«Apruebo el cierre local de la Etapa 7 de Agenda Diaria Automática con las reservas y dependencias documentadas.»**

Queda **aprobado el cierre del alcance local implementado y ensayado**, conservando los siete criterios con dependencia externa y las ocho reservas específicas según la matriz, además de los límites transversales expresos. **99 CA locales / 8 con reserva / 7 con dependencia externa** no se convierten en 114/114 cumplidos. Se reconoce CA-27-26 resuelto y 6.39 completado en sus condiciones ensayadas. Se mantienen pendientes la observación UC-29 a las 07:00 y el consumidor de cupos en **Asignación automática de turnos**, junto con las demás dependencias y límites documentados.

La regresión global **3514/23876**, 0 errores/fallos, 1 omisión SMTP opt-in y salida 0, conserva su alcance y la evidencia previa de Mailpit. La aceptación de reservas permite este cierre local; **no afirma que sus trabajos pendientes hayan sido ejecutados ni certifica producción**. La decisión queda registrada en este documento, 6.40 y la matriz. Falta revisión final del paquete y autorización separada para staging/commit; no se cambia HEAD ni se versiona en esta tarea.

## Inventario candidato para el commit

El siguiente inventario es una propuesta de alcance respecto de HEAD, no una selección en el índice. **M** significa modificado; **N**, nuevo aún sin seguimiento. Se excluyen los dos documentos ajenos de `docs/archivo/`, `.env`, originales congelados, credenciales y todos los artefactos ignorados de ensayos/build. No hay archivos eliminados ni dependencias nuevas.

**45 archivos candidatos: 21 modificados y 24 nuevos.**

Inventario reconfirmado al registrar la aprobación: **las mismas 45 rutas**, sin altas, bajas ni renombres respecto del paquete revisado. Esta tarea cambia el contenido de sólo tres documentos ya incluidos: este documento, 6.40 y la matriz. Paquete actualizado: `storage/framework/testing/agenda-etapa7-cierre-aprobado-20261115/`, con inventario, huellas y diff. La global y su paquete se conservan intactos en `storage/framework/testing/agenda-etapa7-global-final-20261115/`.

| Estado | Archivo |
| --- | --- |
| M | `AGENTS.md` |
| M | `app/Actions/Agenda/ExportarAgendaAtencionPdfAction.php` |
| M | `app/Http/Controllers/AgendaAtencionController.php` |
| M | `app/Http/Controllers/EnlaceAsistenciaTurnoController.php` |
| M | `app/Http/Middleware/ProtegerAccesoEnlaceAsistencia.php` |
| M | `app/Services/Turnos/DespacharRecordatorioTurnoService.php` |
| M | `resources/css/app.css` |
| M | `resources/js/app.js` |
| M | `resources/views/layouts/app.blade.php` |
| M | `resources/views/layouts/navigation.blade.php` |
| M | `resources/views/turnos/agenda-atencion/exportacion.blade.php` |
| M | `resources/views/turnos/agenda-atencion/index.blade.php` |
| M | `resources/views/turnos/agenda-atencion/show.blade.php` |
| M | `resources/views/turnos/asistencia/show.blade.php` |
| M | `routes/web.php` |
| M | `tests/Feature/NavigationTest.php` |
| M | `tests/Feature/Turnos/AgendaAtencionHttpTest.php` |
| M | `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php` |
| M | `tests/Feature/Turnos/ResolverAsistenciaEnlaceHttpTest.php` |
| M | `tests/Feature/Turnos/SolicitarTurnoCreateTest.php` |
| M | `tests/Feature/Turnos/TurnosRoutesTest.php` |
| N | `app/Http/Controllers/AlertasRecordatorioController.php` |
| N | `app/Services/Turnos/ConsultarAlertasRecordatorioService.php` |
| N | `docs/agenda-diaria-automatica/6.40-cierre-provisional.md` |
| N | `docs/agenda-diaria-automatica/etapa-7-decision-cierre-local.md` |
| N | `docs/agenda-diaria-automatica/etapa-7-matriz-trazabilidad.md` |
| N | `docs/agenda-diaria-automatica/uc27-disparo-automatico-20261114.md` |
| N | `docs/agenda-diaria-automatica/uc28-419-origen-20261114.md` |
| N | `resources/js/agenda/agenda-atencion.js` |
| N | `resources/views/turnos/agenda-atencion/detalle.blade.php` |
| N | `resources/views/turnos/agenda-atencion/estado.blade.php` |
| N | `resources/views/turnos/recordatorios/alertas.blade.php` |
| N | `tests/Feature/Turnos/AgendaDiariaAutomaticaIntegracionTest.php` |
| N | `tests/Feature/Turnos/AlertasRecordatorioTest.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/ContratosMotorTest.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/IntegracionYVolumenTest.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/Support/Caso639.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/Support/Entorno639.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/Support/Guardas639.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/bootstrap.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/phpunit.xml` |
| N | `tests/Integration/AgendaDiariaMariaDb639/preparar.php` |
| N | `tests/Integration/AgendaDiariaMariaDb639/worker.php` |
| N | `tests/JavaScript/agenda-atencion.test.mjs` |
| N | `tests/Unit/GuardasAgendaDiariaMariaDb639Test.php` |

Fuera de este commit propuesto, preservados:

- `docs/archivo/codex_sources/paso-7-catalogos-maestros/UC-XX_Gestionar_catalogos_maestros.docx`
- `docs/archivo/codex_sources/paso-7-catalogos-maestros/UC-XX_Gestionar_catalogos_maestros.txt`

No incluir `.env`, `public/hot`, `public/build/`, `storage/framework/testing/`, SQLite, logs, DPAPI, claves, credenciales, capturas/PDF sintéticos ni los originales ignorados de `docs/_codex_sources/`. Los artefactos y fuentes se conservan localmente.
