# Subpaso 8: comunicación del resultado

Rama verificada: `feature/turnos-asignacion-automatica`; HEAD `6d8afd7a54a20b5b25de3592c96f842c22b0a390`. Se preservan los cambios previos, las cuatro fuentes aprobadas y el informe del subpaso 7. `docs/archivo/` no se inspecciona ni modifica.

## Pantalla y consultas existentes

`ConsultarOfertaAdelantoService::resultadoDe()` representa la aceptación registrada con el horario de destino inmutable de la oferta y el estado conservado en la salida. Se reutiliza para GET/HEAD y ambas respuestas POST, incluida una repetición que recupera aceptación previa. El mensaje confirma el **cambio de horario**. Si se conservó Pendiente, se muestra Pendiente; no se convierte en Confirmado ni se afirma confirmación de asistencia. La etiqueta identifica el estado conservado al aceptar, de modo que una consulta posterior del hecho no inventa el estado actual si el turno evolucionó después.

GET/HEAD siguen sin escrituras ni despacho. Se conservan la vinculación al destinatario, los controles del enlace, la ausencia de datos clínicos y las cabeceras de privacidad del canal. No se incluyen credenciales en el HTML ni en el correo de confirmación.

Los recorridos existentes de `MisTurnosController`/`MisTurnosQueryService` y `AgendaAtencionController`/`AgendaAtencionQueryService` consultan la programación actual del mismo Turno. No necesitaron cambios funcionales. Las pruebas recorren sus rutas reales antes y después de aceptar, con paciente y profesional autorizados: mismo identificador, nuevo intervalo y estado correspondiente; filtro de la fecha anterior vacío para ese turno. También verifican el detalle por la ruta del mismo identificador. No se añade un campo ID al detalle administrativo para acomodar una prueba.

## Confirmación durable y posterior al commit

La migración aditiva `2026_11_17_000001_create_despachos_confirmacion_adelanto_table.php` agrega un despacho UNIQUE por `salida_id`, con FK restrictiva a la aceptación y estados técnicos separados del turno. `PrepararConfirmacionAdelantoService` participa de la transacción del traslado usando el mismo protocolo de oferta. Captura sólo destinatario y datos de agenda en material cifrado autenticado, más referencias y propósito. El resultado y el despacho se confirman juntos; un rollback del traslado también revierte esa preparación y su auditoría. No se envía correo dentro de esa transacción.

Repetir aceptación o preparación devuelve el despacho ya existente. `turnos:procesar-cupos`, con su Scheduler de un minuto ya implementado, encola `EnviarConfirmacionAdelantoJob` en conexión database sólo para aceptaciones confirmadas sin despacho o con despacho PENDIENTE. Recupera tanto una interrupción antes del encolado como aceptaciones anteriores que aún no tenían esta preparación. El Job contiene únicamente el ID de aceptación. No se agrega transporte a la respuesta HTTP ni se inicia un Scheduler/worker durante este subpaso.

Las referencias duplicadas en cola son posibles. La restricción UNIQUE y el reclamo transaccional hacen que exista un solo despacho lógico por aceptación. Una repetición no reconstruye un despacho enviado, fallido o incierto. La nueva preparación tampoco crea intención, oferta ni turno.

## Transporte, contenido y fallos

`TransporteCorreoAdelantoService` extrae el transporte aislado que ya usaban las ofertas y se comparte con confirmación. Mantiene SMTP explícito, sin mailer log/fallback, dispatcher ni destinatarios/BCC globales; array sólo en testing. El callback revalida después de conectar y antes de entregar el mensaje al transporte. Las reglas de reintentos, vigencia y clasificación de ofertas siguen en `DespacharOfertaAdelantoService`; esta extracción no las sustituye ni amplía.

`DespacharConfirmacionAdelantoService` exige proceso interno sin usuario de portal y nivel transaccional cero. Bajo K → P → T/oferta relee aceptación, despacho, destinatario vigente y programación. Si cambió destinatario, propietario, profesional, programación o el estado dejó de ser Pendiente/Confirmado, omite y audita sin enviar datos obsoletos. El contenido corresponde al hecho registrado: horario nuevo, kinesióloga, zona y estado conservado al aceptar, con indicación de consultar el estado actual en Mis turnos. No incluye motivo, documentación clínica, planes/sesiones ni un enlace de asistencia anterior.

El reclamo se confirma como INCIERTO antes del transporte. El envío ocurre fuera de la transacción. Un retorno satisfactorio registra ENVIADO; no certifica recepción ni lectura del buzón. Un fallo conocido queda FALLIDO y auditado. Un error sin evidencia suficiente de rechazo permanece INCIERTO, conserva evidencia y no se reenvía automáticamente. Si el registro del resultado falla después del transporte, el reclamo anterior impide un segundo envío ciego.

Estos fallos no modifican turno, oferta aceptada, salida ni revisión de asistencia y no revocan el traslado. El paciente sigue viendo su turno reprogramado en Mis turnos. Se omiten textos/debug de excepciones y destinatarios en Auditoría. El material recuperable se elimina al enviar o cerrar con fallo conocido/omisión; el estado incierto lo conserva para análisis. No se promete SMTP exactamente una vez.

RF12.5 de reintentos de ofertas se conserva sin cambios. Este subpaso no define una política adicional de reenvíos automáticos del correo de confirmación: conserva un intento de transporte recuperable hasta el reclamo, con tratamiento conservador de incertidumbre posterior. La persistencia del resultado lógico evita duplicación al reanudar.

## Archivos del subpaso

Nuevos: modelo `DespachoConfirmacionAdelanto`, servicios `PrepararConfirmacionAdelantoService`, `DespacharConfirmacionAdelantoService` y `TransporteCorreoAdelantoService`, Job `EnviarConfirmacionAdelantoJob`, `ConfirmacionAdelantoMail`, vista `emails/turnos/confirmacion-adelanto`, migración aditiva, `tests/Feature/Turnos/ConfirmacionAdelantoTest.php` y este informe.

Extendidos: `AceptarOfertaAdelantoAction`, `SalidaOfertaAdelanto`, `ProcesarCuposLiberadosCommand`, `DespacharOfertaAdelantoService`, `ConsultarOfertaAdelantoService`, `OfertaAdelantoController`, vista de oferta y seguimiento de `AGENTS.md`. Se actualizan dos aserciones del test del subpaso 7: texto del resultado y admisión de locks reentrantes en la preparación anidada, manteniendo el prefijo K → P → T y FOR UPDATE. Las consultas/vistas de Mis turnos y agenda y sus pruebas históricas permanecen intactas.

## Trazabilidad de CA-R10

`ConfirmacionAdelantoTest` usa migraciones sólo sobre SQLite en memoria y correo/cola simulados. Incluye Pendiente/Confirmado en pantalla y recorridos HTTP de paciente/profesional, ausencia del turno en la fecha anterior, contenido del correo, observación del nivel cero al enviar y aceptación consolidada, repetición y referencias de cola duplicadas, recuperación posterior al traslado, fallos temporales/definitivos/inciertos sin reversión, rollback del traslado sin despacho, rechazo de transporte dentro de transacción, GET/HEAD sin envío, destinatario/programación obsoletos y caída al registrar el resultado posterior al transporte. El fallo de configuración log también queda auditado sin enviar ni revertir.

## Pendientes del subpaso 9

Concurrencia real en MariaDB aislada: todas las carreras pendientes de 7, ambos órdenes de CA-R11, dos preparadores/despachadores de confirmación, UNIQUE/FK/DDL efectivos, visibilidad tras commit, REPEATABLE READ, esperas, deadlocks/timeouts y ventanas de interrupción antes/después del transporte y su auditoría. SQLite no certifica estos locks físicos.

Recorrido real por navegador con HTTPS/cookies, revisión visual del resultado y de Mis turnos/agenda, y revisión de logs HTTP/proxy. SMTP externo y entrega/lectura reales no se certifican con correo simulado. Rendimiento y volumen siguen pendientes de la puerta de cierre. No se inicia ni se declara cerrado el subpaso 9.

## Verificación definitiva — 17/11/2026

Arnés ignorado: `storage/framework/testing/asignacion-comunicacion-20261117-08/verificar.ps1`. Comandos:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-comunicacion-20261117-08/verificar.ps1 -Fase focalizadas
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-comunicacion-20261117-08/verificar.ps1 -Fase regresion
```

Focalizadas: **15 pruebas / 179 aserciones PASS**, 28,550 segundos. Regresión: **975 pruebas / 7025 aserciones PASS**, 276,178 segundos, incluyendo las focalizadas sin sumar corridas. Cero errores/fallos/omisiones, salidas **0**, PHP 8.2.12 / PHPUnit 11.5.55. La primera corrida focalizada detectó una suposición incorrecta de la prueba sobre un campo ID del detalle de Mis turnos; se corrigió para verificar identidad en listado y ruta, sin modificar su proyección ni debilitar la comprobación del horario/estado. No se atribuye a esa corrida el PASS definitivo.

Corpus: comunicación, aceptación, rechazo/vencimiento/reintentos, primera emisión, reserva, selector y validador, disponibilidad individual/agregada, alta, Actions de Agenda, Mis turnos y Agenda de atención HTTP, emisión/validación/resolución de asistencia, transacción y cancelación UC–06. El archivo HTTP de agenda incluye pruebas de impresión/PDF y ensayos sintéticos existentes; sólo componen esta regresión y no certifican rendimiento CA-P03 del adelanto.

Preflight: testing sin configuración cacheada, SQLite `:memory:` efectivo por PDO/PRAGMA sin archivo, único driver sqlite y PDO MySQL ausente también en hijos; correo/cache/sesión array, cola predeterminada sync con Queue fake, NullHandler, clave efímera no impresa y storage/vistas/cachés/documentos exclusivos. Las migraciones sólo se ejecutaron internamente sobre SQLite efímero. Evidencia: `{focalizadas,regresion}.junit.xml` dentro del arnés.

Sintaxis PHP y Pint `--test` de 15 PHP: PASS, salidas 0. `npm run build`: **PASS, 96 módulos**, Vite 7.3.1, 6,46 segundos, salida 0. El primer intento falló con spawn EPERM; se repitió con autorización fuera del sandbox. Aviso existente de Browserslist desactualizado, sin instalar dependencias. `git diff --check`: sin salida, código 0; índice sin cambios. Arnés y build ignorados.

Las cuatro fuentes aprobadas y el informe del subpaso 7 conservaron SHA-256. Se preservaron los cambios anteriores y `docs/archivo/`. Se registra cierre local de 8 en `AGENTS.md`, con las reservas indicadas; sin staging, commit, servidores, base persistente, Scheduler/worker ni SMTP real. El subpaso 9 permanece pendiente.
