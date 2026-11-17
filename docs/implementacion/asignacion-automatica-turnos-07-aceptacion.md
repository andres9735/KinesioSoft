# Subpaso 7: aceptación y traslado atómico

Rama esperada y verificada: `feature/turnos-asignacion-automatica`. HEAD: `6d8afd7a54a20b5b25de3592c96f842c22b0a390`. Se conservan los cambios previos sin staging ni commit, las cuatro fuentes aprobadas y los informes 5/6. `docs/archivo/` permanece sin inspección ni modificación.

## Contrato de la aceptación

GET/HEAD del enlace siguen siendo consultas. La pantalla de una oferta vigente agrega **Aceptar adelanto**, por POST `/turnos/adelanto/aceptar`. Reutiliza la cookie cifrada de propósito `adelanto-navegador-v1`, CSRF, HTTPS, Origin y Sec-Fetch-Site del subpaso 6. Cada endpoint exige su acción correspondiente: enviar `aceptar` al endpoint de rechazo no registra un rechazo. No se agrega login ni credencial del correo al formulario.

`AceptarOfertaAdelantoAction` recibe exclusivamente la autorización autenticada por el middleware. Dentro de los locks revalida referencias de despacho/oferta/paciente, vencimiento asociado y expiración de la autorización del navegador. Una credencial inválida no habilita detalles ni escrituras. Un original que cambió de recursos sólo devuelve el cierre informativo al destinatario de la oferta; no muestra los datos de su nuevo propietario/profesional.

## Transacción y coordinación

`TransaccionOfertaAdelantoService` aplica el mismo prefijo Kinesióloga → Paciente → Turno, seguido de oferta y despacho, sobre las referencias inmutables de la oferta. Esta especialización es necesaria para cerrar también un original que cambió de kinesióloga o paciente: no toma un segundo mutex ni modifica ese turno cambiado. Se reutiliza en aceptación y cierre/rechazo/vencimiento. `TransaccionTurnoService` histórico permanece intacto. Los flujos vigentes que afectan el mismo destino coordinan por el mutex K existente.

Tras adquirir los locks se recupera primero el resultado UNIQUE de la oferta. Si existe, se devuelve sin otro traslado ni auditoría. Para una aceptación nueva se comprueban estado activo, vigencia estricta, programación original, profesional y paciente, estado Pendiente/Confirmado, intención y evaluación originales y reserva efectiva de la propia oferta. Se leen con locks los turnos y otras reservas que intersecten el cupo completo; la propia reserva se excluye de incompatibilidades.

Se reutiliza `ValidarDestinoAdelantoService` bloqueante para inicio exacto, duración registrada, encaje, configuración, franja, bloqueos y cruces de profesional y paciente. No se usa la grilla de altas ni se recalcula la duración estándar. La franja debe contener el intervalo del candidato; la reserva se refiere al cupo completo. Una restricción no verificable, incluida configuración ambigua, no autoriza el traslado. Los errores de acceso a la base propagan fallo y rollback; no se transforman en disponibilidad ficticia.

La autorización del navegador y el reloj se vuelven a comprobar después de las lecturas bloqueantes. No se reaplica el margen de cuatro horas propio de la activación: una oferta vigente puede aceptarse hasta su vencimiento estricto. Las cuatro horas sí vuelven a exigirse si una salida necesita activar otra oferta.

## Resultado atómico y conservación

Se modifica únicamente inicio y fin del mismo `Turno`; conserva ID, paciente, kinesióloga, tipo, motivo, duración registrada, estado vigente al aceptar, clave de idempotencia y asociaciones por ID. El resultado se registra en `salidas_oferta_adelanto`, con la oferta aceptada, reserva resuelta, auditoría del resultado y auditoría administrativa del traslado en la misma transacción. La migración aditiva `2026_11_16_000004_add_estado_aceptado_to_salidas_oferta_adelanto.php` incorpora el estado al aceptar para conservar el resultado histórico, aun si el turno evoluciona posteriormente. No se altera ninguna migración histórica.

La aceptación tiene continuación `no_corresponde`, sin oferta siguiente. Ni repetir el POST ni entregar después un Job de ciclo vuelve a evaluar el cupo. El intervalo anterior deja de estar ocupado por ese turno mediante las consultas habituales de disponibilidad; no se llama a la Action de cancelación ni se registra otra intención. No se crea otro turno ni otra oferta por esa liberación.

Los triggers históricos de revisión de asistencia incrementan la revisión por el cambio de horario dentro de la misma transacción. La Action comprueba el incremento y falla con rollback si no puede garantizarlo. Enlaces y autorizaciones de asistencia anteriores pierden validez sin borrar su evidencia ni emitir otro enlace. No se cambia automáticamente el estado a Confirmado: aceptar adelanto y confirmar asistencia siguen siendo operaciones distintas.

## Salidas sin traslado y repetición

- Oferta vencida: resultado vencida, sin aceptar desde el instante exacto del límite.
- Original cancelado, reprogramado o con recursos/estado incompatibles: original modificado, conservando su programación actual.
- Reserva perdida, ocupación o reserva incompatible: cupo no disponible.
- Destino incompatible o restricción aplicable no verificable: destino incompatible.

Estas salidas usan el cierre durable del subpaso 6 y su continuación pendiente, sobre la misma evaluación de cancelación. El controlador encola ciclo después del commit; el sondeo lo recupera si la publicación se interrumpe. La continuación decide si el cupo sigue utilizable, conserva exclusiones por paciente y revalida el plazo. Un destino que permanece inutilizable no genera otra oferta.

Aceptación y rechazo recuperan el mismo resultado terminal: prevalece la primera respuesta válida registrada bajo el mutex y la fila de oferta. Si un bloqueo invalidante se completa primero, su cierre impide aceptar. Si la aceptación se completa primero, la oferta aceptada ya no se trata como reserva activa; la revisión y registro del bloqueo encuentran al turno trasladado y conservan el tratamiento habitual de un turno existente, incluidas sus advertencias, sin moverlo ni cancelarlo automáticamente.

La pantalla informa aceptación, resultado previo, vencimiento, cambio del original o incompatibilidad. Un fallo de la transacción informa que no se pudo completar la operación y permite consultar nuevamente mediante el enlace, sin divulgar excepciones ni credenciales. Reflejo explícito en Mis turnos/agenda, presentación final del nuevo horario y correo de confirmación corresponden al subpaso 8 y no se certifican aquí.

## Archivos del subpaso

Nuevos: `app/Actions/Turnos/AceptarOfertaAdelantoAction.php`, `app/Services/Turnos/TransaccionOfertaAdelantoService.php`, la migración aditiva 000004, `tests/Feature/Turnos/OfertaAdelantoAceptacionTest.php` y este informe.

Extendidos: `CerrarOfertaAdelantoService`, `ConsultarOfertaAdelantoService`, modelos `OfertaAdelanto`/`SalidaOfertaAdelanto`, `OfertaAdelantoController`, `ProtegerAccesoOfertaAdelanto`, `routes/web.php`, `resources/views/turnos/adelanto/show.blade.php`, seguimiento de `AGENTS.md` y una aserción HTTP del subpaso 6 para reflejar la acción nueva conservando su prueba de lectura sin efectos. No se cambian las fuentes aprobadas ni los informes históricos 5/6.

## Límites de evidencia

SQLite en memoria acredita comportamiento funcional y rollback, con correo y cola simulados. La compilación MySQL de las consultas declara FOR UPDATE y orden K → P → T; no abre una conexión MySQL y no prueba locking físico.

MariaDB aislada queda pendiente para carreras con procesos independientes: aceptación/rechazo, aceptación/vencimiento, dos aceptaciones, aceptación/alta, aceptación/otra reserva y aceptación/configuración/bloqueo. Incluye ambos órdenes de CA-R11, REPEATABLE READ, espera de locks y vencimiento durante esa espera, deadlocks/timeouts, rollback real, triggers de revisión y DDL de la columna nueva. La precisión registrada de los intervalos en el esquema actual de Turnos es de segundos; los límites de vigencia usan precisión de microsegundos en sus tablas correspondientes.

También queda pendiente el recorrido visual de navegador con HTTPS/cookies reales y revisión de logs HTTP/proxy. No se certifican producción, SMTP externo, rendimiento CA-P03 ni el subpaso 8.

## Trazabilidad funcional

Pruebas nuevas: `tests/Feature/Turnos/OfertaAdelantoAceptacionTest.php`. Cada variante de los proveedores se cuenta por separado.

| Criterio | Evidencia local |
| --- | --- |
| CA-R04 | POST explícito, cookie/CSRF/origen/acción, asociación revalidada bajo locks, autorización que vence durante la adquisición, límite exacto de oferta y vencimiento durante lecturas; cuatro estados no elegibles incluido Cancelado, reprogramación y cambio de recursos. |
| CA-R05 | Reserva perdida, ocupación en la porción sobrante del cupo y otra reserva incompatible; no mueve el original. La otra reserva es un dato defensivo insertado fuera del protocolo, no una activación permitida por el sistema. |
| CA-R06 | Franja incompleta, bloqueo, cruce del paciente con otra profesional, configuración anulada o ambigua; conserva el original y deja continuación durable. |
| CA-R07 | Pendiente y Confirmado: mismo ID, paciente, profesional, tipo, motivo, duración de treinta minutos, estado vigente y clave de idempotencia; cupo de cuarenta y cinco minutos e inicio fuera de grilla. |
| CA-R08 | Repetición de aceptación y Jobs tardíos sin escrituras, una salida/auditoría; aceptación primero y rechazo primero. El estado histórico al aceptar permanece aunque el turno luego se cancele. |
| CA-R09 | Fallo forzado al crear la auditoría del traslado, después de comprobar que ya se escribieron turno, oferta y salida; rollback de programación, revisión, reserva, resultado, material cifrado y auditorías. El mismo POST puede recuperarse después. |
| CA-R10, parte autorizada | Disponibilidad del intervalo anterior y una sola intención/oferta; continuación `no_corresponde`, sin encolado ni correo nuevo tras éxito. No certifica Mis turnos, agenda visual ni confirmación por correo. |
| CA-R11 | Ambos órdenes secuenciales mediante las Actions reales de revisión y registro de bloqueo. Si gana aceptación, aparece el turno trasladado entre los existentes afectados y se conserva. Carreras independientes pendientes de MariaDB. |
| DEP-08 | Enlace de asistencia previo pasa de válido a programación modificada; su autorización de POST anterior tampoco puede confirmar el turno trasladado. |

La prueba de locks sólo inspecciona su declaración y compilación: prefijo K → P → T, lecturas actuales dentro de transacción y FOR UPDATE para MySQL. No constituye una carrera de procesos.

## Verificación definitiva — 16/11/2026

Arnés ignorado: `storage/framework/testing/asignacion-aceptacion-20261116-07/verificar.ps1`. Comandos:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-aceptacion-20261116-07/verificar.ps1 -Fase focalizadas
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-aceptacion-20261116-07/verificar.ps1 -Fase regresion
```

Focalizadas: **36 pruebas / 284 aserciones PASS**, 56,887 segundos. Regresión: **652 pruebas / 3943 aserciones PASS**, 183,370 segundos; incluye las focalizadas, sin sumar corridas. Cero errores/fallos/omisiones, salidas **0**, PHP 8.2.12 / PHPUnit 11.5.55. El antecedente de 30/206 anterior a los refuerzos no se suma ni representa el conjunto definitivo.

Corpus de regresión: aceptación nueva; `OfertaAdelantoSalidasTest`; `PrimeraEmisionAdelantoTest`; `OfertaAdelantoTest`; selector y validador; disponibilidad individual/agregada y alta; Actions de Agenda; `ResolverAsistenciaEnlaceHttpTest`, `EnlaceAsistenciaTurnoHttpTest`, emisión/validación/resolución de asistencia por servicio, `TransaccionTurnoServiceTest` y `CancelarTurnoActionTest`. Las evidencias JUnit definitivas son `{focalizadas,regresion}.junit.xml` dentro del arnés.

Preflight: testing, sin configuración cacheada, SQLite `:memory:` efectivo por PDO/PRAGMA sin archivo, sólo driver sqlite y PDO MySQL ausente también en hijos. Correo/cache/sesión array, cola predeterminada sync con Queue fake, NullHandler, clave efímera no impresa y storage/vistas/cachés/documentos exclusivos. La migración aditiva y las demás migraciones sólo se ejecutaron internamente sobre SQLite efímero.

Sintaxis PHP y Pint `--test` de los doce PHP del subpaso: PASS, salidas 0. `npm run build`: **PASS, 96 módulos**, Vite 7.3.1, 4,87 segundos, salida 0; se repitió con autorización fuera del sandbox después de spawn EPERM. Aviso existente de Browserslist desactualizado, sin instalar dependencias. `git diff --check`: sin salida, código 0; sin staging ni commit. Arnés y build ignorados.

Se registra cierre local del subpaso 7 en `AGENTS.md`, con MariaDB/navegador pendientes explícitos. No se inicia el subpaso 8 ni se certifica rendimiento o producción. Las cuatro fuentes aprobadas conservaron sus SHA-256 y se preservaron informes 5/6 y cambios previos.
