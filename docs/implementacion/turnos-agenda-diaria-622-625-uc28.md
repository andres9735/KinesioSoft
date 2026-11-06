# Agenda Diaria Automática — Checkpoint parcial UC–28, 6.22–6.25

Fecha de emisión: 2026-11-06.

## Estado y alcance

**6.22–6.25 completados y verificados localmente. Checkpoint parcial de UC–28 registrado mediante el commit que incorpora este certificado. UC–28 no está completo.**

Rama revisada: `feature/turnos-agenda-diaria-automatica`. HEAD previo al checkpoint: `6eec1cf25e251b8c9d9565f48832696aea038bfe`. No se anticipa el hash del commit de cierre.

Se conserva el contenido definitivo de implementación y pruebas revisado al finalizar 6.25. En este checkpoint sólo se crea este certificado y se actualiza el seguimiento activo de `AGENTS.md`; no se modifica código funcional ni se repiten PHPUnit, builds, migraciones o verificaciones MariaDB.

El preflight encontró exactamente trece archivos nuevos de implementación/pruebas, una modificación versionada en `routes/web.php` y staging vacío. `docs/archivo/` continúa protegido y fuera del alcance, sin inspección ni modificación. Las ocho fuentes congeladas están disponibles; los siete contenidos enumerados coinciden con su manifest de integridad y la exclusión local sigue vigente.

El compendio congelado contiene el orden general UXP-14, sin numeración explícita 6.22–6.25. La delimitación operativa procede de las solicitudes específicas del usuario y fue contrastada con GA-08–GA-12, AT-10, UXP-02–UXP-04 y la especificación de UC–28; no se reconstruyen ni modifican las fuentes aprobadas.

## Alcance implementado

| Subpaso | Resultado local |
| --- | --- |
| 6.22 | Persistencia de enlaces, hashes, asociaciones, unicidad e infraestructura de revisión monotónica separada de los atributos de Turno. |
| 6.23 | Emisión interna transaccional, identidad de ciclo, repetición idempotente, URL HTTPS transitoria y auditoría atómica. Sin correo ni Jobs de notificación. |
| 6.24 | Validación de credencial, alcance, asociación, programación, revisión, vigencia, revocación y permisos temporales de lectura. |
| 6.25 | Consulta HTTPS GET/HEAD sin login general ni sesión, presentación administrativa mínima y controles de privacidad, sin decisiones modificatorias. |

La disponibilidad indicada para confirmar o cancelar es informativa. No existen formularios ni POST de decisión de UC–28 en este checkpoint.

## Archivos del incremento incluidos

Los trece archivos siguientes son nuevos respecto del HEAD previo:

- `app/Http/Controllers/EnlaceAsistenciaTurnoController.php`.
- `app/Http/Middleware/ProtegerAccesoEnlaceAsistencia.php`.
- `app/Models/EnlaceAsistenciaTurno.php`.
- `app/Services/Turnos/AccesoEnlaceAsistencia.php`.
- `app/Services/Turnos/EmitirEnlaceAsistenciaTurnoService.php`.
- `app/Services/Turnos/EnlaceAsistenciaEmitido.php`.
- `app/Services/Turnos/ValidarEnlaceAsistenciaTurnoService.php`.
- `database/migrations/2026_11_05_000003_create_enlaces_asistencia_turno_table.php`.
- `resources/views/turnos/asistencia/show.blade.php`.
- `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/EnlaceAsistenciaTurnoTest.php`.
- `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php`.

Se modifica `routes/web.php` exclusivamente para incorporar la consulta específica de UC–28 y sus imports. El checkpoint agrega este certificado y modifica sólo el seguimiento activo de `AGENTS.md`: total de dieciséis archivos, catorce nuevos y dos modificados.

## Persistencia, revisión y compatibilidad con UC–06

El token se genera con `random_bytes(32)`: 256 bits aleatorios, representados en hexadecimal. Únicamente se persiste SHA-256 en `token_hash`, con unicidad y exclusión de la representación ordinaria del modelo. La comprobación utiliza `hash_equals`. El registro conserva turno, paciente, profesional, tipo, intervalo, zona horaria, revisión, ciclo, alcance, emisión, vencimiento y revocación; no almacena el original, la URL secreta ni contenido clínico.

La revisión reside en `revisiones_enlace_asistencia_turno`; no se agrega una columna a `turnos`. Los triggers inicializan su identidad y la incrementan ante cambios de paciente, profesional, tipo, inicio, fin o una transición a un estado incompatible. Cubren Eloquent, escrituras sin eventos, SQL directo y actualizaciones masivas. Volver a los valores anteriores no rehabilita una credencial obsoleta: se preserva la protección ABA.

La revisión no puede disminuir, eliminarse ni reasignarse. Se conserva si desaparece un turno sin enlaces y se incrementa ante una eventual reutilización del ID; por ello la tabla auxiliar no tiene una FK que bloquee esos escenarios históricos. Las FK de los enlaces emitidos son restrictivas. Una revisión ausente produce rechazo técnico; no se reconstruye con un valor inicial.

La primera aproximación incrementaba un contador dentro de `turnos` al cancelar y provocó cinco fallos históricos: cuatro combinaciones de tipo/estado y el caso de modelo recibido con atributos alterados. Se corrigió dentro de la infraestructura nueva, sin alterar `CancelarTurnoAction`, `ConfirmarAsistenciaTurnoAction` ni sus pruebas. También se preservaron los escenarios históricos de turno inexistente, retirando la restricción auxiliar que impedía su eliminación cuando no había enlaces.

La cancelación mantiene sus postcondiciones: sólo cambia estado y timestamps autorizados del turno, con auditoría e intención durable atómicas; el avance auxiliar se revierte si falla la transacción. Confirmar no consume la credencial ni cambia la revisión. No se alteran reservas, ocupación, reglas clínicas ni otros turnos desde la emisión o consulta.

## Emisión, idempotencia y auditoría

`EmitirEnlaceAsistenciaTurnoService` reutiliza `TransaccionTurnoService` y el orden kinesióloga → paciente → turno, con relectura y reloj posterior a los locks. Emite sólo para turnos futuros Pendientes o Confirmados.

Existe un ciclo ordinario por turno/revisión, independiente del intento de correo. La repetición devuelve el mismo identificador sin generar otra credencial, URL o auditoría. Una credencial revocada no se sustituye silenciosamente.

La emisión y su auditoría se consolidan en la misma transacción. El contexto de auditoría es administrativo y explícito, sin token, URL secreta, referrer ni información clínica. El resultado entrega la URL original sólo transitoriamente, mediante acceso explícito para el correo; excluye el secreto de JSON/debug y bloquea su serialización. No debe despacharse antes del commit efectivo de cualquier transacción exterior.

## Validación, vigencia y consulta HTTP

El servicio de validación localiza exclusivamente por el hash de la credencial opaca. No utiliza identificadores de paciente, turno o profesional enviados por el visitante para ampliar el acceso. Comprueba alcance, autenticidad, asociación, programación, revisión y estado actual; diferencia rechazo inválido, vencido, revocado, programación modificada y estado incompatible, sin datos de la cita en los rechazos.

La vigencia se extiende hasta el inicio del turno, con límite exclusivo; no se impone una caducidad rígida de 24 horas desde la emisión. Confirmar se presenta como permitido sólo en Pendiente y estrictamente antes del inicio. Cancelar se presenta como permitido en Pendiente/Confirmado mientras resten al menos doce horas corridas, incluido el límite exacto. La validez de acceso y la disponibilidad de cada acción se evalúan separadamente; un Confirmado dentro de las doce horas puede conservar acceso válido sin acciones disponibles.

`GET/HEAD /turnos/asistencia` utiliza una frontera específica sin middleware de sesión/autorización del portal. La apertura, recarga y previsualización no escriben estado, revisión, revocación, consumo, auditorías o intenciones. Tampoco se ejecutan las Actions de decisión desde este recorrido.

La vista Blade muestra sólo tipo, fecha, horario, kinesióloga, estado y disponibilidad de acciones. Escapa los datos, incluye estructura semántica y adaptación básica a pantallas pequeñas. No expone nombre del paciente, DNI, email, motivos clínicos, documentos o navegación general del portal.

El middleware elimina la query secreta del Request antes de validación/presentación y de los registros posteriores controlados por este recorrido. La credencial se excluye del HTML. Se verifica HTTPS sin redirigir una URL secreta, ausencia de cookies de sesión, `no-store`, `no-referrer`, directivas de no indexación, CSP sin recursos externos, protección de framing y errores genéricos sin excepción ni credencial en logs. Estas comprobaciones no certifican los logs anteriores al middleware o ajenos a Laravel.

## Evidencia reutilizada

**Regresión definitiva: 258 tests / 2032 assertions PASS, exit code 0**, 24,189 segundos, PHP 8.2.12 / PHPUnit 11.5.55. Sin fallos ni errores en esa ejecución.

Se invocó PHPUnit directamente con `phpunit.xml`, `--do-not-cache-result --colors=never --fail-on-risky --fail-on-warning`, dentro del perfil PHP aislado. El conjunto exacto fue:

- `tests/Feature/Services/Turnos/EnlaceAsistenciaTurnoTest.php`.
- `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php`.
- `tests/Feature/Actions/Turnos/ConfirmarAsistenciaTurnoActionTest.php`.
- `tests/Feature/Actions/Turnos/CancelarTurnoActionTest.php`.
- `tests/Feature/Services/Turnos/TransaccionTurnoServiceTest.php`.

La cobertura pertinente incluye persistencia protegida, unicidad/FK, revisión irreversible, ABA, revocación, expiración, límites temporales, repetición, rollback, conservación de atributos, privacidad y GET/HEAD sin escrituras. La clase HTTP comprende 22 casos; la validación focalizada previa a 6.25 registró 24 tests / 96 assertions PASS, exit 0.

Como antecedentes separados se conservan los cinco casos históricos originalmente fallidos corregidos: **5 tests / 156 assertions PASS, exit 0**, y la regresión de la corrección de 6.22–6.23: **265 tests / 1918 assertions PASS, exit 0**. Esta última también incluía `ConfirmarPrimeraConsultaActionTest.php`, ausente del conjunto definitivo de 258; el definitivo incorpora validación y acceso HTTP nuevos. No son conjuntos idénticos ni cantidades acumulables, y la ejecución definitiva no constituye una nueva recertificación de Primera consulta o de toda UC–07.

El bootstrap efectivo de las pruebas definitivas verificó `testing`, configuración/rutas sin caché, SQLite `:memory:`, PDO SQLite sin PDO MySQL, storage/discos/vistas/cachés aislados, mail/cache/session de prueba y logging mediante `Monolog\Handler\NullHandler`. No se utilizó la base de desarrollo.

Sintaxis PHP, Pint `--test` acotado, `git diff --check` y whitespace: **PASS** al concluir la implementación. La vista se renderizó mediante las pruebas HTTP. En este checkpoint documental se reutiliza esa evidencia y se repiten únicamente las validaciones de diff/whitespace y staging, sin pruebas o builds nuevos.

## Reservas y contratos pendientes

- **MariaDB real pendiente:** se compiló DDL y se generó SQL de triggers fuera de línea, sin ejecutarlos en el motor objetivo. Faltan instalación efectiva, permisos, comportamiento de triggers, locking, carreras y concurrencia real. SQLite no certifica esas garantías.
- Revisión visual manual, producción, suite global y rendimiento del acceso en condiciones reales no certificados por este bloque.
- Verificación operativa de logs HTTP/proxy y de cualquier registro anterior al middleware pendiente; no se modificó configuración de servidores.
- **UC–27 pendiente:** entrega, despacho y reintentos cuando el original sólo existe transitoriamente. La emisión repetida no puede recuperar la URL desde el hash. No se implementó ni improvisó una integración de correo.
- **Contrato pendiente de 6.26:** la credencial se excluye del HTML de 6.25. La ejecución posterior mediante POST deberá conservar una autorización segura del enlace sin exigir login general y sin introducir filtraciones de token por HTML, logs, referrer o almacenamiento persistente en texto plano.
- La validación inicial no habilita por sí sola una transición: 6.26 deberá revalidar credencial, programación, revisión, revocación, estado, actor y plazo de manera coordinada con la ejecución transaccional de las Actions compartidas, conforme al contrato congelado. Ese mecanismo no se resuelve ni implementa en este certificado.
- Ningún POST de decisión está implementado. No se declara UC–28 completo ni se autoriza automáticamente 6.26.

## Huellas del contenido revisado

SHA-256 de los bytes de los catorce archivos de implementación/pruebas/rutas en el working tree revisado antes de este checkpoint documental. Se registran para permitir contrastes posteriores; no representan hashes de tokens ni una ejecución nueva de pruebas.

| Archivo | SHA-256 |
| --- | --- |
| `app/Http/Controllers/EnlaceAsistenciaTurnoController.php` | `d44898b40b455b6e36a7c9285e98d2f0420c6c0092a98a8896336f75e3ef2a59` |
| `app/Http/Middleware/ProtegerAccesoEnlaceAsistencia.php` | `9bc92c9a48b83694faaef072bb6168d546da57c1251798668be462cc51c81d12` |
| `app/Models/EnlaceAsistenciaTurno.php` | `b5891fe07ba8828037fb1f6e1a8e48f21b9ba55fdd1ece82c0ee2bbcded005d4` |
| `app/Services/Turnos/AccesoEnlaceAsistencia.php` | `e135d8693cca25e24ed5294468141b68d2b717865f393e021a0c51d13d05feca` |
| `app/Services/Turnos/EmitirEnlaceAsistenciaTurnoService.php` | `274d3d7394cebaa1d7e4f94a0f420760f6b0b4fb65f70a57b1e2529885e221ac` |
| `app/Services/Turnos/EnlaceAsistenciaEmitido.php` | `65ae25f9b666477164d7b66bafeed4f24f24be37a3e989cd5f1769e6a50f421c` |
| `app/Services/Turnos/ValidarEnlaceAsistenciaTurnoService.php` | `07e1a0c90ff97d664697f5bb4297326b0af44ebf26803ad68e0e96247c019dd3` |
| `database/migrations/2026_11_05_000003_create_enlaces_asistencia_turno_table.php` | `f6d6fb500fba400a20f0af01eeadf642a41dbf9e2f17e9d8bd49cec0ee631c44` |
| `resources/views/turnos/asistencia/show.blade.php` | `16641b1fdf0a2c3642749101989d57fe044a57966b271f33fd83e64e191ae47c` |
| `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php` | `7c5da08721cdf081104c1ae0fbba0ed9f9c13345da926708d7d05bace0424bad` |
| `tests/Feature/Services/Turnos/EnlaceAsistenciaTurnoTest.php` | `fe2395f4d9720288e5b1c20a83e1a4e29128bc8b70f2cc992aeb5f53e0f442aa` |
| `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php` | `7c61dc0a6a7405dbcf7475db610987a94fb48a7925e30b303ecac816673bb07c` |
| `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php` | `b9568c4b0d2dbc7b5cdc6dff7d7c27fcb054f55c30c6d45d21461571a2d6db73` |
| `routes/web.php` | `5fa3008a4addc00bd32e5b48c60d5a495cbbe550bc80263d9598b3cb1e0f35b9` |

## Dictamen

Checkpoint parcial de infraestructura, emisión, validación y acceso de lectura de UC–28 satisfactorio bajo las condiciones locales certificadas y con las reservas anteriores. Último subpaso completado: **6.25**. Próximo subpaso para preparar: **6.26 — Resolución de asistencia mediante Actions de UC–06**, sin autorización automática durante este checkpoint.
