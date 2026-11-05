# Agenda Diaria Automática — Checkpoint UC–05 (6.10–6.12)

**Estado: IMPLEMENTADO Y VERIFICADO dentro del alcance de los tres subpasos; checkpoint documentado y versionado mediante su commit de cierre. UC–05 no está cerrado en su totalidad.**

- Fecha de revisión documental: **05/11/2026**, referencia local del proyecto.
- Rama: `feature/turnos-agenda-diaria-automatica`.
- HEAD de revisión, previo al checkpoint: `2d3572f6fa8fed4a7914d532676476e27f3c0dfd`.
- El commit que incorpora este documento registra el checkpoint; no se anticipa su hash.

## Alcance implementado y revisado

| Subpaso | Resultado |
| --- | --- |
| 6.10 | Ruta propia `GET /turnos/agenda-atencion`, controlador y Form Request; consulta profesional autorizada con jornada local actual por defecto o fecha explícita. |
| 6.11 | Agenda diaria ordenada, navegación anterior/siguiente y selección de fecha; paciente, profesional, tipo, seis estados canónicos e intervalos reales con duración. Vacíos y controles semánticos accesibles, sin JavaScript nuevo. |
| 6.12 | Búsqueda parcial por nombre/apellido del paciente dentro de los resultados autorizados; filtros combinables por horario de inicio local inclusivo, estado y tipo; períodos con fechas extremas inclusivas y paginación de 25 resultados. Navegación con parámetros validados y limpieza de filtros conservando el contexto temporal. |

Archivos de implementación:

- `routes/web.php`.
- `app/Http/Controllers/AgendaAtencionController.php`.
- `app/Http/Requests/ConsultarAgendaAtencionRequest.php`.
- `resources/views/turnos/agenda-atencion/index.blade.php`.
- `tests/Feature/Turnos/AgendaAtencionHttpTest.php`.

Fuentes de referencia: especificación extendida UC–05 y compendio V1.0 local congelado, especialmente AT-01 a AT-04 y AT-09, GB-01 a GB-03 y GB-07 a GB-12, UXP-05, UXP-06 y UXP-08. El bloque aporta cobertura a la consulta, navegación, representación, filtros y privacidad; no declara cumplidos los 32 criterios de UC–05 ni sus cotas de rendimiento.

## Seguridad, privacidad y contratos preservados

La ruta conserva `auth`, `perfil.kinesiologo`, `verified` y `perfil.completo`, junto con el control global de cuenta activa existente. El controlador deriva la profesional de la relación persistida del usuario web y utiliza `AgendaProfesionalQueryService`; esta frontera revalida identidad, rol y pertenencia antes de entregar información de pacientes. No se aceptan identificadores del cliente para ampliar el ámbito profesional.

La búsqueda y los filtros se aplican exclusivamente a la representación administrativa autorizada. No se consulta un directorio global de pacientes ni se busca en contactos, motivos o documentos clínicos. Los parámetros se validan en servidor; las URLs de navegación y paginación se construyen con una lista explícita de parámetros pertinentes. Blade escapa los valores mostrados. La consulta administrativa no concede acceso documental ni clínico.

Se mantienen ambos tipos y los seis estados cuando no hay filtros restrictivos, el orden por inicio e id y los intervalos persistidos completos, incluso al cruzar medianoche. Los atributos HTML de horarios representan fecha y hora locales sin atribuirles un offset UTC. No se recalculan intervalos con configuraciones posteriores ni se infieren equipos o reservas inexistentes.

Los servicios certificados del bloque B permanecen intactos. La lectura sigue disponible antes de las 7:00 y sin generación UC–29. No se invocan Actions de UC–06 ni se crean, confirman, cancelan, reprograman o modifican turnos, reservas, configuraciones o auditorías desde la consulta. La expulsión transversal de una cuenta inactiva conserva su comportamiento de seguridad histórico; no constituye una escritura funcional de agenda.

## Evidencia reutilizada de 6.12

La última ejecución completa de `tests/Feature/Turnos/AgendaAtencionHttpTest.php` certificó **70 tests / 618 assertions PASS, exit code 0**, sin fallos ni errores. PHP **8.2.12**, PHPUnit **11.5.55**; tiempo informado **00:15.233**, memoria **62 MB**. Se ejecutó PHPUnit directamente con `phpunit.xml`, `--do-not-cache-result`, `--fail-on-risky` y `--fail-on-warning`, bajo el perfil aislado previamente certificado.

Esa ejecución cubrió el código definitivo revisado, sin modificaciones funcionales ni de pruebas posteriores: ruta GET/HEAD, middleware y autorización HTTP; cuenta, correo y perfil; fechas locales, navegación, tipos y estados; privacidad y escape HTML; independencia de UC–29; validación, filtros individuales y combinados, períodos, vacíos, paginación estable y conservación de parámetros. Las pruebas verifican ausencia de cambios en la base y ausencia de consultas por turno en el recorrido de paginación ensayado.

Los 70 casos incluyen la prueba de ruta de UC–05 y todos sus casos HTTP anteriores. No incluyen las suites históricas completas de rutas de Agenda y del paciente. La ejecución inicial de 6.10 comprendió 25 casos HTTP y 15 de esas rutas, con 39 satisfactorios y un fallo de fixture HTTP posteriormente corregido. En 6.11 se ejecutaron 32 casos HTTP; en 6.12 se conservaron y ampliaron hasta 70. No se eliminó cobertura ni se suman ejecuciones diferentes como una única certificación.

El preflight efectivo previo a PHPUnit comprobó:

- Entorno `testing` y configuración sin caché.
- SQLite `:memory:` con apertura de PDO; únicamente driver `sqlite`, sin PDO MySQL.
- Correo, caché y sesiones `array`; cola `sync`.
- Storage, discos, vistas y rutas de caché exclusivos de la ejecución.
- Canales de logging y deprecaciones `null`, con handlers efectivos `Monolog\Handler\NullHandler`.
- Variables sólo del proceso y clave efímera, sin persistir entorno ni exponer secretos.

Sintaxis PHP y Pint `--test` de controlador, Request y prueba: **PASS** en 6.12. Blade se compiló durante las pruebas HTTP. `git diff --check` y whitespace de los archivos nuevos: **PASS**. En este checkpoint se revisa la evidencia y se valida el diff/whitespace final; **no se repite PHPUnit**, al no haber cambios posteriores ni cobertura esencial faltante identificada.

## Incidencias resueltas durante la implementación

- Fixture de correo no verificado: se reemplazó la actualización ignorada por asignación masiva por `forceFill`, con assertion de la precondición.
- Validación cruzada de fechas/horas: se comparan ambos extremos sólo después de validar sus tipos y formatos; los arrays manipulados producen rechazo de validación y no un error 500.
- Assertions de enlaces: se verifican los parámetros pertinentes sin depender de su orden textual.
- Paginación con rótulos accesibles en español y corrección de los atributos HTML de horarios locales.

La ejecución inicial de 6.12 tuvo **69 tests / 580 assertions, tres fallos, exit 1**. Tras corregirlos y añadir una regresión, el archivo completo definitivo obtuvo el resultado **70/618 PASS** indicado arriba. No son cantidades acumulables.

## Límites, integridad y pendientes

**La paginación actual se realiza en memoria**, después de recuperar la representación autorizada del período. Limita los resultados presentados por página, pero no la carga inicial de datos. La verificación contra requisitos aprobados de rendimiento y volumen queda expresamente pendiente para **6.16**, incluyendo carga de jornada habitual en hasta 2 segundos y aplicación de filtros habituales en hasta 1 segundo, bajo condiciones reproducibles. Los tiempos de PHPUnit no certifican esas cotas. No se modifica preventivamente esta estrategia durante el checkpoint.

Permanecen pendientes el detalle administrativo de 6.13, impresión/PDF de 6.14 y refinamiento integral de 6.15, junto con las verificaciones posteriores de UC–05. No se certifican visualmente todos los navegadores/dispositivos ni se implementan UC–29, UC–28 o UC–27 por este bloque.

**Durante este checkpoint no se ejecutaron PHPUnit, regresión PHP global, JavaScript, build ni MariaDB.** MariaDB continúa sin certificación actual del incremento; SQLite no certifica locking real. El baseline de 6.05 y el checkpoint del bloque B conservan sus resultados y límites históricos, sin recertificación ni suma de cantidades.

Se preservan los contratos de UC–06, UC–07 y Configuración de Agenda, las ocho fuentes aprobadas, exclusiones y temporales históricos. `docs/archivo/` permanece sin inspección ni modificación. Sólo se actualiza el seguimiento vigente de `AGENTS.md` y se crea este certificado para incorporar, junto con los cinco archivos revisados, el checkpoint de siete archivos autorizado.

**Dictamen: 6.10, 6.11 y 6.12 completados y verificados dentro del alcance documentado. Próximo subpaso para preparar: 6.13 — Detalle administrativo de turno; no autorizado para ejecución automática.**
