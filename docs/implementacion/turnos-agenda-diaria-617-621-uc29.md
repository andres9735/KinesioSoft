# Agenda Diaria Automática — Checkpoint conjunto UC–29 (6.17–6.21)

**Estado: implementado y certificado bajo las condiciones locales ensayadas, con reservas explícitas. No constituye certificación de producción, de concurrencia real MariaDB ni cumplimiento sin reservas de los 19 criterios.**

- Registro documental: **05/11/2026**, referencia local de la sesión del proyecto.
- Rama: `feature/turnos-agenda-diaria-automatica`.
- HEAD de revisión previo al checkpoint: `fc70d31d7faf51c7b50ce694da8fe9fb3b7a6879`, cierre del bloque UC–05.
- El único commit de este checkpoint incorpora este certificado, el seguimiento activo y los archivos enumerados. No se anticipa su hash.
- La revisión de cierre confirmó las huellas SHA-256 de los 19 archivos de implementación y pruebas contra el estado definitivo de 6.21. No hubo cambios funcionales posteriores y no se repitió PHPUnit.

## Alcance implementado

| Subpaso | Implementación y verificación |
| --- | --- |
| 6.17 | Preparación administrativa de una fecha civil completa para una profesional habilitada; lectura compartida, tipos y estados canónicos, orden estable, intervalos reales y resultados vacíos válidos. |
| 6.18 | Registro persistente de ejecuciones y resultados profesionales, relaciones, integridad, cantidades administrativas y códigos controlados de incidencias. |
| 6.19 | Comando interno y programación mediante Laravel Scheduler a las 7:00 locales, sin sesión HTTP y con prevención de superposición. |
| 6.20 | Identidad lógica por fecha/zona, repetición idempotente, recuperación parcial, referencias a éxitos previos y control transaccional de titularidad del reclamo. |
| 6.21 | Contraste de los 19 criterios, regresión focalizada conjunta, integración HTTP de independencia de UC–05, rendimiento sintético y revisión de calidad y privacidad. |

Fuentes utilizadas: especificación extendida de UC–29, matriz consolidada CA-29-01 a CA-29-19, contratos AT pertinentes, decisiones GB-01 a GB-06 y UXP-11/12/14, disponibles en el paquete V1.0 congelado de `docs/_codex_sources/turnos/agenda_diaria_automatica/`. Las decisiones GA de notificaciones no autorizan recordatorios dentro de UC–29. Las fuentes originales y exclusiones permanecen intactas.

## Archivos del bloque

Dieciocho archivos nuevos de implementación/pruebas y una modificación versionada:

| Estado | Archivo | Responsabilidad |
| --- | --- | --- |
| Nuevo | `app/Console/Commands/GenerarAgendaDiariaCommand.php` | Entrada CLI, fecha civil, salida administrativa y errores sin detalles sensibles. |
| Nuevo | `app/Enums/IncidenciaGeneracionAgendaEnum.php` | Códigos de selección, preparación e interrupción. |
| Nuevo | `app/Enums/ResultadoGeneracionAgendaEnum.php` | EN_CURSO, COMPLETADA y FALLIDA, exclusivamente del procesamiento técnico. |
| Nuevo | `app/Models/EjecucionAgendaDiaria.php` | Identidad, contexto temporal y resultado de ejecución; conservación histórica. |
| Nuevo | `app/Models/JornadaGeneracionAgenda.php` | Identidad lógica de jornada, ejecución actual y reclamo. |
| Nuevo | `app/Models/PreparacionAgendaDiaria.php` | Resultado profesional, cantidades y referencia de reutilización. |
| Nuevo | `app/Services/Agenda/GenerarAgendaDiariaService.php` | Orquestación, reclamo, repetición y recuperación. |
| Nuevo | `app/Services/Agenda/JornadaAgendaPreparada.php` | Representación administrativa transitoria y clasificación de cantidades. |
| Nuevo | `app/Services/Agenda/PrepararJornadaAgendaService.php` | Preparación mediante lectura compartida y elegibilidad canónica. |
| Nuevo | `app/Services/Agenda/ReclamoGeneracionPerdidoException.php` | Rechazo controlado de un trabajador sin titularidad vigente. |
| Nuevo | `app/Services/Agenda/RegistroGeneracionAgendaService.php` | Inicio, resultados conocidos, fallos, interrupción y reutilización de registros. |
| Nuevo | `database/migrations/2026_11_05_000001_create_ejecuciones_agenda_diaria_tables.php` | Ejecuciones y preparaciones por profesional. |
| Nuevo | `database/migrations/2026_11_05_000002_add_control_jornada_generacion_agenda.php` | Control lógico de jornada y FK de resultado reutilizado. |
| Nuevo | `tests/Feature/Console/GenerarAgendaDiariaCommandTest.php` | Comando, Scheduler, fecha local, vacíos, validación y errores. |
| Nuevo | `tests/Feature/Services/Agenda/GeneracionAgendaDiariaRendimientoTest.php` | Ensayos sintéticos reproducibles y cota local por profesional. |
| Nuevo | `tests/Feature/Services/Agenda/GenerarAgendaDiariaServiceTest.php` | Repetición, recuperación, titularidad, integridad e independencia de UC–05. |
| Nuevo | `tests/Feature/Services/Agenda/PrepararJornadaAgendaServiceTest.php` | Fecha civil, selección, estados, intervalos, orden y lecturas sin efectos. |
| Nuevo | `tests/Feature/Services/Agenda/RegistroGeneracionAgendaServiceTest.php` | Persistencia, relaciones, errores, conservación y DDL offline. |
| Modificado | `routes/console.php` | Registro de la tarea diaria. |

Se agregan documentalmente `AGENTS.md` y este certificado. El checkpoint comprende **21 archivos: 2 modificados y 19 nuevos**.

## Preparación, ejecución y consulta

`PrepararJornadaAgendaService` reutiliza `ElegibilidadKinesiologaService` y `AgendaAtencionQueryService`; no utiliza la fachada HTTP profesional. La selección emplea el predicado canónico de habilitación, sin exigir disponibilidad ni agregar nuevas exclusiones.

Sin fecha explícita se obtiene hoy mediante `RelojAplicacion`; una fecha explícita conserva el contrato civil del lector compartido. La jornada incluye el inicio local y excluye el comienzo del día siguiente. La pertenencia depende del inicio del turno y conserva completo el intervalo persistido, aunque termine en otra fecha. Incluye turnos anteriores a las 7:00, ambos tipos y los seis estados, ordenados por inicio e identificador.

La representación contiene únicamente identidad y nombre administrativo de paciente/profesional, tipo, estado e intervalo. Pendiente/Confirmado se cuentan como operativos; En atención se distingue de los demás estados. No se serializan modelos completos ni se inventan equipos, planes, sesiones o documentación clínica.

El comando es `agenda:generar-diaria`, con opción `--fecha=YYYY-MM-DD` para la jornada que corresponda. La fecha inválida no inicia procesamiento. La programación única usa `dailyAt('07:00')`, `timezone(config('app.timezone'))` y `withoutOverlapping(5)`. Las pruebas inspeccionaron el registro y `isDue()` antes, durante y después de las 7:00, sin ejecutar `schedule:run` ni tareas ajenas. La instalación/activación del disparador del sistema operativo no se verificó ni se modificó.

## Persistencia, idempotencia y recuperación

- `ejecuciones_agenda_diaria`: UUID única de ejecución, fecha civil, zona horaria, inicio, finalización, resultado e incidencia controlada. Una UUID no sustituye la identidad lógica de jornada.
- `preparaciones_agenda_diaria`: FK restrictivas a ejecución/profesional, unicidad por ese par, resultado, momentos, cantidades administrativas e incidencia. La FK restrictiva `preparacion_origen_id` identifica un éxito previo reutilizado.
- `jornadas_generacion_agenda`: unicidad por fecha civil y zona, FK restrictiva a la ejecución actual y vencimiento del reclamo. La zona se conserva junto con la fecha para identificar el contexto temporal.

Las tablas objetivo usan InnoDB; estados/códigos se almacenan como strings con enums PHP, sin ENUM nativo MySQL. No hay modificaciones del esquema de Turno ni cascadas destructivas de registros históricos. Los modelos preservan sus identidades y resultados finales frente a las operaciones ordinarias implementadas.

Una ejecución completa de la misma jornada se devuelve sin nuevas escrituras. Un resultado parcial se representa mediante ejecución FALLIDA y resultados profesionales individuales, conservando los éxitos. La nueva invocación reutiliza esos éxitos con una referencia explícita y prepara las profesionales pendientes de resultado exitoso. Las cantidades reutilizadas conservan su carácter de evidencia histórica y no alimentan la consulta vigente de UC–05.

El reclamo técnico dura **300 segundos**, se renueva en las operaciones protegidas y se verifica bajo lock del control antes de cada escritura. El orden de los locks auxiliares es jornada → ejecución → preparación. La lectura de turnos ocurre fuera de esas transacciones; no se adquieren locks de Turno para preparar la jornada.

Al recuperar un reclamo vencido, la ejecución anterior y sus preparaciones inconclusas se registran como interrumpidas; se conservan los resultados exitosos. La validación de titularidad impide que el trabajador anterior consolide después de perder el reclamo. Se contempla lectura actual con bloqueo al resolver la colisión UNIQUE de jornada en REPEATABLE-READ; su carrera real MariaDB permanece sin ensayo.

**Los 300 segundos no redefinen la condición funcional de 60 segundos por profesional ni constituyen un timeout clínico.** No se agregó una frecuencia automática de reintentos: la recuperación se activa por una nueva invocación del comando con la fecha correspondiente. Un reclamo vigente ajeno no se fuerza ni se sustituye.

EN_CURSO y cantidades NULL indican resultado aún desconocido. Una preparación vacía exitosa registra cero. Los fallos de lectura o selección no se convierten en vacíos válidos; los errores de persistencia se propagan y el inicio durable permite recuperación cuando corresponde. La bitácora conserva contexto administrativo y códigos de selección/preparación/interrupción, sin SQL, stacks, tokens, secretos ni mensajes arbitrarios de excepciones. El comando tampoco imprime esos detalles.

## Contratos protegidos

Turno y su programación consolidada conservan autoridad. UC–29 no crea, confirma, cancela, reprograma ni inicia atenciones, ni modifica reservas, configuraciones, planes o documentos. Las pruebas compararon las tablas ajenas a la bitácora antes y después del procesamiento.

No se modificaron las Actions, políticas, transacciones, reloj, elegibilidad o motor de disponibilidad de UC–06/UC–07/Configuración de Agenda. La bitácora auxiliar registra procesamiento; no simula una sesión profesional ni auditorías de transiciones que no ocurrieron. No se implementaron recordatorios, correo, enlaces seguros ni interfaces públicas nuevas.

UC–05 conserva su lectura autorizada de los registros vigentes, incluso tras una generación fallida. Se verificaron respuestas HTTP después del fallo y después de cambios de estados ya registrados. Las simulaciones administrativas no implementan ni certifican los flujos clínicos o profesionales responsables de producir esas transiciones.

## Matriz CA-29-01 a CA-29-19

Referencias de pruebas: **P** = `PrepararJornadaAgendaServiceTest`; **R** = `RegistroGeneracionAgendaServiceTest`; **G** = `GenerarAgendaDiariaServiceTest`; **C** = `GenerarAgendaDiariaCommandTest`; **V** = `GeneracionAgendaDiariaRendimientoTest`. Los archivos figuran en el inventario anterior; los dos lectores compartidos también integraron la regresión.

| Criterio | Resultado y evidencia disponible |
| --- | --- |
| CA-29-01 | Verificado localmente: C, `test_scheduler_registra_una_sola_tarea_a_las_siete_locales_sin_disparar_eventos`. Reserva: activación operativa por el sistema operativo no verificada. |
| CA-29-02 | Verificado: P, hoy local y límites por inicio; C, fecha explícita con turnos anteriores a las siete. |
| CA-29-03 | Verificado: P, selección canónica y rechazo de habilitación revocada, separación de profesionales. |
| CA-29-04 | Verificado: C/R/G, ausencia de profesionales con resultado registrado y sin filas profesionales ficticias. |
| CA-29-05 | Verificado: P, ambos tipos/seis estados y cantidades operativas. |
| CA-29-06 | Verificado: P/R, preservación de estados y cantidades diferenciadas; no reclasificación como nuevas citas operativas. |
| CA-29-07 | Verificado en la representación administrativa implementada: P y contratos del lector compartido, con identidad, tipo, estado e intervalos reales. |
| CA-29-08 | Dependencia externa: no se infieren asignaciones; Turno carece de relación de equipos individuales registrados. No se declara implementada la incorporación de equipos vinculados. |
| CA-29-09 | Verificado: P, límites y orden estable por horario/ID, sin recortar intervalos. |
| CA-29-10 | Verificado: P/G, relectura de programación actual y consulta independiente de la bitácora. |
| CA-29-11 | Verificado en las tablas y operaciones disponibles: P/R/G, lecturas sin efectos y escrituras limitadas a bitácora. Los procesos externos de equipos y clínica no se consideran implementados por esta prueba. |
| CA-29-12 | Verificado: P/R/C, jornada vacía válida y cantidades cero sólo después de preparación exitosa. |
| CA-29-13 | Verificado en registros administrativos consolidados: P/G, reprogramación, cambios de estado y nuevas citas visibles al releer; G incluye consulta HTTP tras fallo. Integración real con flujos clínicos/profesionales externos sin certificar. |
| CA-29-14 | Verificado el contrato de no escribir/restaurar Turno y de lectura vigente. Reserva: carreras reales durante generación no ensayadas sobre MariaDB. |
| CA-29-15 | Verificado localmente: G, repetición, segunda instancia, recuperación parcial, vencimiento y rechazo del trabajador anterior. Reserva: simulaciones secuenciales/reentrantes SQLite, sin certificación de carreras MariaDB. |
| CA-29-16 | Verificado: P/R/G/C, fallos propagados o registrados, interrupción durable, cantidades desconocidas y recuperación sin fingir vacío. |
| CA-29-17 | Verificado: G, `test_uc05_http_sigue_disponible_tras_fallo_y_muestra_cambios_posteriores`, junto con lectores compartidos sin dependencia del resultado automático. |
| CA-29-18 | Verificado bajo las condiciones sintéticas locales de V. Reserva: rendimiento, bloqueo real bajo concurrencia, producción y carga fría no certificados. |
| CA-29-19 | Verificado: P, ambos tipos y seis estados preservados; no se añade «No confirmado» al dominio Turno. |

Esta matriz no declara un 19/19 PASS sin reservas. Las dependencias de equipos, clínica, activación operativa y concurrencia real conservan su condición pendiente.

## Evidencia de pruebas y aislamiento

Regresión definitiva de 6.21: **98 tests / 770 assertions PASS, exit code 0**, sin fallos ni errores. PHP **8.2.12**, PHPUnit **11.5.55**, duración **00:19.253**, memoria informada por PHPUnit **66 MiB**.

Archivos ejecutados conjuntamente mediante PHPUnit directo con `phpunit.xml`, `--do-not-cache-result`, `--fail-on-risky` y `--fail-on-warning`:

1. `tests/Feature/Console/GenerarAgendaDiariaCommandTest.php`.
2. `tests/Feature/Services/Agenda/GenerarAgendaDiariaServiceTest.php`.
3. `tests/Feature/Services/Agenda/GeneracionAgendaDiariaRendimientoTest.php`.
4. `tests/Feature/Services/Agenda/PrepararJornadaAgendaServiceTest.php`.
5. `tests/Feature/Services/Agenda/RegistroGeneracionAgendaServiceTest.php`.
6. `tests/Feature/Services/Agenda/AgendaAtencionQueryServiceTest.php`.
7. `tests/Feature/Services/Agenda/AgendaProfesionalQueryServiceTest.php`.

El preflight efectivo verificó Laravel en `testing`, configuración/rutas sin caché, conexión PDO SQLite `:memory:` y ausencia de PDO MySQL. Se utilizó PHP `-n` con extensiones restringidas; correo/cache/sesión en array y cola sync. Storage, discos, vistas y rutas de caché quedaron dentro de una raíz temporal exclusiva. Logging normal y de deprecaciones utilizaron `Monolog\Handler\NullHandler`. Clave y variables de prueba fueron exclusivas del proceso, sin persistir cambios de entorno.

Las pruebas de esquema/integridad se ejecutaron en SQLite `:memory:`. Las dos migraciones se compilaron offline con gramática MariaDB 10.4.32 y conexión real prohibida por el ensayo, sin PDO MySQL. **Compilar DDL no acredita aplicar migraciones, locking ni concurrencia real sobre MariaDB. Ninguna migración se ejecutó sobre una base real.**

Sintaxis PHP y Pint `--test` acotados a los **19 archivos PHP** del bloque: PASS. Diff y whitespace de los dieciocho archivos nuevos: PASS. Las verificaciones Git y documentales de este checkpoint son de lectura/validación; no se repiten estas pruebas funcionales.

## Rendimiento local y condición de 60 segundos

Ensayo Windows/PHP CLI 8.2.12, SQLite en memoria y datos sintéticos, dentro de la regresión de 6.21. Preparación de fixtures fuera del tramo medido; cada profesional tiene treinta pacientes/citas de treinta minutos, desde las 06:00 de la fecha local. No se ejecutaron navegador, red, assets ni bases reales. Los volúmenes son condiciones de ensayo, no nuevos límites funcionales.

Se utilizó una muestra por escenario con `hrtime(true)`, query log habilitado y medición profesional desde el evento `creating` del registro de preparación hasta `updated` con resultado COMPLETADA. El tiempo global se midió alrededor del orquestador e incluye selección y persistencia. No son percentiles, medianas, carga fría ni medidas de producción.

| Profesionales | Turnos totales | Tiempo global | Máximo profesional | Consultas globales | Consultas del tramo profesional |
| --- | --- | --- | --- | --- | --- |
| 1 | 30 | 0,061383 s (0,0614 s) | 0,031372 s | 38 | 18 |
| 5 | 150 | 0,209863 s (0,2099 s) | 0,044725 s | 118 | 18 |
| 20 | 600 | 0,541161 s (0,5412 s) | 0,035553 s | 418 | 18 |

El pico de heap PHP medido fue **58 MiB** en los tres escenarios. El incremento de memoria asignada respecto del comienzo del tramo fue cero; esto refleja memoria ya reservada por el proceso de pruebas y no significa ausencia de consumo. No se midió RSS del sistema operativo.

La condición de **hasta 60 segundos por profesional pasó sólo en estos ensayos locales**. Las pruebas verificaron una lectura de turnos por profesional y consultas constantes por tramo profesional. El ensayo de preparación de 1 y 10 turnos también verificó consultas constantes, sin N+1 por cita observado. Esto no acredita rendimiento universal ni ausencia de bloqueo real en producción; los locks implementados se limitan a registros auxiliares.

El reclamo de **300 segundos** regula titularidad y recuperación técnica. No sustituye la cota de rendimiento ni convierte una preparación de más de 60 segundos en aceptable.

## Reservas y dictamen

Permanecen pendientes:

- Activación y operación real del disparador del Scheduler, disponibilidad de su caché/locks y entorno de despliegue.
- Carreras, UNIQUE bajo competencia real, aislamiento, esperas, deadlocks y recuperación entre procesos sobre MariaDB. Las pruebas SQLite son secuenciales/reentrantes y no las certifican.
- Aplicación real de las migraciones, producción, carga fría, carga concurrente, volúmenes distintos y latencias reales de infraestructura.
- Vinculaciones individuales de equipos y flujos clínicos/profesionales externos; no se inventaron reservas, sesiones ni atenciones para declararlos cumplidos.
- Integración y certificación posteriores del incremento completo. Los cierres históricos UC–06/UC–07 y el baseline 6.05 conservan sus límites; no se recertifican globalmente.

En este checkpoint **no se ejecutan PHPUnit, suite global, JavaScript, Vite, MariaDB real, migraciones ni seeders**. No se modifica código funcional, configuración permanente, fuentes congeladas, exclusiones o artefactos históricos. `docs/archivo/` permanece untracked y fuera de toda inspección o modificación.

**Dictamen: 6.17–6.21 completados y verificados localmente; UC–29 implementado y certificado con las reservas documentadas.** La modalidad acelerada experimental fue aplicada satisfactoriamente en dos ejecuciones (6.17–6.18 y 6.19–6.21), conservando las pruebas de seguridad e integridad y priorizando la cuota semanal de Codex.

El próximo subpaso para preparar es **6.22 — UC–28**. No queda autorizado automáticamente por este checkpoint y no se inicia en esta intervención.
