# Agenda Diaria Automática — Checkpoint parcial UC–27, 6.30–6.33

**Fecha:** 06/11/2026.

**Estado:** 6.30–6.33 completados y verificados localmente. Checkpoint parcial registrado mediante el commit que incorpora este certificado. **UC–27 no está completo.**

## Estado revisado y evidencia

Rama: `feature/turnos-agenda-diaria-automatica`. HEAD previo: `951f7f004304cab92f5430efbc8bda112e9fff59`. No se anticipa el hash del commit de cierre.

El preflight encontró exactamente dos archivos versionados modificados, diecisiete nuevos de implementación/pruebas y staging vacío. Las diecinueve huellas coinciden con las registradas tras el último PASS. Durante este checkpoint sólo se crea este certificado y se actualiza el seguimiento activo de `AGENTS.md`; implementación y pruebas permanecen intactas.

Se verificaron las ocho fuentes congeladas de `docs/_codex_sources/turnos/agenda_diaria_automatica/` mediante el manifiesto de 6.02, las exclusiones y la disponibilidad de la decisión transversal UC–28/UC–27. Los certificados históricos permanecen sin modificaciones. `docs/archivo/` continúa protegido, untracked, sin inspección ni modificación.

Autoridad funcional/técnica: D-06/D-07, GA-01–GA-07, UXP-01 y UXP-14 del compendio V1.0; especificación extendida UC–27 y matriz de 29 criterios. El compendio establece el orden general, sin numeración explícita 6.30–6.34; la delimitación de estos subpasos y el título del siguiente proceden de las solicitudes autorizadas. Esta certificación no reconstruye ni modifica esas fuentes.

## Alcance local implementado

| Subpaso | Resultado |
| --- | --- |
| 6.30 | Recordatorio lógico durable por turno/revisión, intentos separados, unicidad/FK, estados y resultados técnicos, idempotencia y auditoría atómica. |
| 6.31 | Selección de turnos elegibles, umbral de 24 horas, correo vigente, recuperación de pendientes, programación/revisión y ausencia de efectos sobre turnos. |
| 6.32 | Comando interno, Scheduler configurable de cinco minutos, franja opcional, exclusión de solapamiento y coordinación de selección/preparación durable. |
| 6.33 | Preparación atómica de referencias de entrega, contenido administrativo y Mailable/Blade transitorios con acciones vigentes y controles de privacidad. |

### Persistencia e identidad

`recordatorios_turno` conserva UUID lógico y unicidad `(turno_id, version_programacion)`; `intentos_recordatorio_turno` conserva identidad y número de intento únicos, destinatario utilizado, fechas, resultado e incidencia técnica. FK restrictivas conservan asociaciones e historia.

La revisión monotónica se reutiliza desde la infraestructura certificada de UC–28, separada de `turnos`. Confirmar Pendiente → Confirmado no crea otro recordatorio. Reprogramar y restaurar valores temporales anteriores no confunde ciclos: la revisión impide ABA. `recordatorio_enviado_at` no se utiliza como mecanismo único de deduplicación.

Los estados técnicos PENDIENTE, PROCESADO, FALLIDO e INCIERTO no representan asistencia. Los resultados distinguen INCIERTO, ACEPTADO, ENTREGA_VERIFICADA, FALLO_RECUPERABLE y FALLO_DEFINITIVO. Aceptación no acredita recepción; la entrega verificada exige una referencia técnica de evidencia que deberá aportar el adaptador futuro.

`RegistrarRecordatorioTurnoService` registra creación, intención de intento y resultados de forma transaccional con auditoría mínima. Un intento comienza incierto y su repetición por identidad devuelve el registro existente; no autoriza repetir transporte. La infraestructura limita a un intento inicial y hasta tres reintentos dentro de la hora posterior al primer fallo recuperable; no los ejecuta ni programa. El consumidor operativo y la verificación real de resultados continúan pendientes.

### Selección y límites temporales

`SeleccionarRecordatoriosTurnoService` contempla Primera consulta y Seguimiento, Pendiente y Confirmado. Excluye los otros cuatro estados y exige inicio estrictamente futuro, con el umbral de referencia alcanzado: `ahora < inicio <= ahora + 24 horas`. Se verificaron bordes y microsegundos.

No agrega anticipación mínima: los turnos creados con menos de 24 horas, incluso menos de doce, pueden seleccionarse mientras sigan siendo futuros y elegibles. No limita la recuperación a `created_at`, `updated_at` o la fecha del último ciclo. El cálculo utiliza `RelojAplicacion`, zona configurada y horas transcurridas; se ensayaron otras zonas y cambios DST.

La selección relee bajo locks canónicos, comprueba programación/revisión, obtiene el correo vigente y registra CORREO_NO_UTILIZABLE sin copiar entradas inválidas. Una corrección de correo puede recuperar el mismo recordatorio sin crear otro. Los registros con intentos iniciados quedan fuera de la selección ordinaria; su tratamiento posterior debe conservar la identidad y los resultados conocidos.

Las referencias ya asociadas revocadas, vencidas o inciertas impiden preparar una entrega incompatible. Selección y preparación no confirman, cancelan, reprograman ni reservan turnos.

### Configuración temporal: cinco minutos y 7:00 locales

Son dos tareas independientes, coherentes con GA-02 y GB-04:

| Proceso | Programación actual | Configuración |
| --- | --- | --- |
| UC–27 | `turnos:preparar-recordatorios`, cron predeterminado `*/5 * * * *`. | `recordatorios.cron`, variable de proceso `TURNOS_RECORDATORIOS_CRON`; zona `app.timezone`. |
| UC–29 | `agenda:generar-diaria`, `dailyAt('07:00')`, conservado. | Hora definida en `routes/console.php`; zona `app.timezone`. |

La referencia de las 7:00 corresponde a la generación de agenda de UC–29. Su hora permanece explícita en rutas; lo configurable en ese evento es la zona. No se atribuye al código un parámetro de hora diaria inexistente.

La franja opcional de UC–27 se configura mediante `recordatorios.horario_inicio` y `recordatorios.horario_fin` (`TURNOS_RECORDATORIOS_HORARIO_INICIO/FIN`), ambos HH:mm locales o ambos null. Por defecto ambos son null, sin una franja adicional de exclusión. Soporta cruces de medianoche y límites inclusivos por minuto. No se modificó `.env` ni se impuso un inicio a las 7:00 a los recordatorios.

Scheduler conserva mutex propios, independientes de UC–29, y `withoutOverlapping(5)`. El orquestador añade un lock de caché de 300 segundos, libera sólo su lock y revalida mediante los servicios compartidos. Esa duración es una concesión técnica de exclusión, no una regla funcional de oportunidad ni una prueba de exclusión universal.

El comando selecciona y prepara referencias durables; no descifra, renderiza o envía correos. Una ejecución sin candidatos termina normalmente sin registros ficticios. Los errores generales se registran con mensaje fijo saneado en el logger y salida fallida; las incidencias particulares permanecen en los registros de recordatorio/intento. No se fabrica un objetivo de auditoría cuando no existe candidato persistido.

### Contenido, doce horas y contratos compartidos

`PrepararContenidoRecordatorioTurnoService` relee turno/programación, estado, tiempo, destinatario y deduplicación bajo la transacción compartida. Reutiliza el predicado de oportunidad extraído del selector y `ValidarEnlaceAsistenciaTurnoService::accionesPara()`, que centraliza las posibilidades informativas existentes. La validación de UC–28 conserva la zona del enlace. No se modifican las Actions históricas.

| Estado y anticipación | Contenido |
| --- | --- |
| Pendiente, al menos doce horas | Confirmar y cancelar. |
| Pendiente, menos de doce horas, antes del inicio | Confirmar y contacto con el consultorio. |
| Confirmado, al menos doce horas | Informativo y cancelar, sin segunda confirmación. |
| Confirmado, menos de doce horas, antes del inicio | Informativo, sin acciones modificatorias. |

El límite de cancelación incluye exactamente doce horas corridas. Confirmar exige inicio estrictamente futuro. Estas posibilidades orientan el correo y no sustituyen la autorización efectiva del enlace ni las verificaciones internas de las Actions de UC–06.

El Mailable y la plantilla muestran únicamente tipo, fecha y horario locales, profesional, estado, acciones y texto operativo. Blade escapa los datos dinámicos. No incorporan motivo clínico, diagnósticos, documentos, DNI o email del paciente al cuerpo. No hay recursos externos, formularios de decisión automática ni navegación clínica. La apertura del acceso conserva el GET de lectura de UC–28.

## Integración con UC–28 y seguridad

Es obligatoria la [decisión transversal R1–R10](turnos-agenda-diaria-decision-transversal-uc28-uc27-sobre-cifrado.md), aprobada y congelada el 06/11/2026. Se preserva el [cierre local de UC–28](turnos-agenda-diaria-626-629-uc28.md) y sus reservas.

La preparación con acciones utiliza la emisión existente y asocia enlace/sobre dentro de la transacción exterior; un fallo de asociación revierte emisión, sobre y auditorías nuevas. Repetir devuelve las mismas referencias. Un aviso exclusivamente informativo puede prepararse sin emitir enlace. Los históricos sin sobre recuperable se rechazan explícitamente, sin reconstruir hashes ni reemitir silenciosamente.

La preparación ordinaria devuelve sólo IDs. Para construir contenido con URL se exige material transitorio y reclamo vigente del callback autorizado de `SobreEntregaEnlaceService`, con verificación de hash del reclamo, asociación, programación, vigencia, revocación, credencial y origen HTTPS canónico. Se rechaza construir dentro de una transacción exterior todavía no consolidada.

`RecordatorioTurnoMail` excluye el contenido de JSON/debug y bloquea serialización. No implementa ShouldQueue. Los futuros Jobs deberán transportar referencias técnicas y construir el correo dentro del despacho autorizado. No se persisten URL, token o HTML con credenciales en recordatorios, intentos o auditorías.

Construir/renderizar no significa aceptación o entrega: el reclamo del sobre conserva incertidumbre hasta recibir un resultado conocido. La coordinación de intento, reclamo, transporte y cierre sigue pendiente; esta infraestructura no autoriza reenvíos automáticos ni garantiza entrega exactamente una vez.

Los contratos históricos UC–06, UC–07 y Agenda permanecen preservados en el alcance revisado. Sólo se refactoriza el predicado informativo del validador de UC–28, con pruebas pertinentes; no se atribuye una recertificación global a este bloque.

## Pruebas y calidad reutilizadas

**Regresión definitiva 6.30–6.33: 228 tests / 1457 assertions PASS, exit code 0**, sin fallos ni errores, PHP 8.2.12 / PHPUnit 11.5.55, tiempo 02:04.428.

Conjunto exacto, mediante PHPUnit directo con `phpunit.xml`, perfil PHP aislado y `--do-not-cache-result --colors=never --fail-on-risky --fail-on-warning`:

- `tests/Feature/Console/PrepararRecordatoriosTurnoCommandTest.php`.
- `tests/Feature/Services/Turnos/PrepararContenidoRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/RegistrarRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/SeleccionarRecordatoriosTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/SobreEntregaEnlaceServiceTest.php`.
- `tests/Feature/Services/Turnos/TransaccionTurnoServiceTest.php`.
- `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php`.
- `tests/Feature/Console/GenerarAgendaDiariaCommandTest.php`.

La evidencia anterior de 6.30–6.31 fue **266 tests / 1821 assertions PASS, exit 0**, 01:16.015. Aquel conjunto incluía `EnlaceAsistenciaTurnoTest.php` y `CancelarTurnoActionTest.php`, ausentes del definitivo de 228. El definitivo incorpora las pruebas nuevas de orquestación/contenido, acceso HTTP del enlace y la prueba puntual del comando/Scheduler de UC–29. Los conjuntos son distintos; no se suman ni sustituyen una suite global. El código histórico de cancelación permanece sin modificaciones.

Las focalizadas de contenido registraron 23 tests / 194 assertions y cuatro tests / ocho assertions PASS, exit 0. La integración del ciclo actualizado pasó un test / trece assertions. Son antecedentes parciales incluidos en la regresión definitiva, sin acumulación de cifras.

Incidencias corregidas antes del PASS definitivo:

- El nuevo test de estado inválido esperaba LogicException; Laravel rechazaba el enum mediante ValueError. Se ajustó la expectativa, manteniendo el rechazo; repetición focalizada: un test / cuatro assertions PASS.
- El registro inicial de error global intentaba usar AuditoriaService sin objetivo persistido. Se corrigió a mensaje fijo en el logger y salida fallida, conservando auditoría/incidencias por recordatorio y liberación del mutex; repetición focalizada: un test / cinco assertions PASS.
- Pint corrigió exclusivamente formato/imports en los tests nuevos. Ninguna corrección debilitó pruebas históricas o cambió contratos funcionales.

Bootstrap efectivo: `testing`, SQLite `:memory:`, PDO SQLite sin PDO MySQL, configuración/rutas sin caché, storage/discos/vistas/cachés aislados, mail/cache/session de prueba y logging `Monolog\Handler\NullHandler`. No se utilizó la base de desarrollo.

Sintaxis y Pint `--test`: PASS sobre el alcance PHP afectado de cada ejecución, diez archivos en 6.32–6.33. Diff y whitespace: PASS. Migración nueva verificada en SQLite aislado, con up/down sólo de sus dos tablas nuevas; DDL MariaDB compilado offline, sin conexión ni ejecución real en ese motor.

Durante este checkpoint documental no se repiten PHPUnit, Pint, migraciones, builds o pruebas de servicios externos. Se validan nuevamente huellas, documentos, diff/whitespace y staging.

## Inventario y huellas definitivas

Estado respecto del HEAD previo: M modificado y A nuevo. Son hashes de archivos, no credenciales. A estas diecinueve rutas se agregan `AGENTS.md` (M) y este certificado (A): **21 archivos, tres modificados y dieciocho nuevos**.

| Estado | Archivo | SHA-256 |
| --- | --- | --- |
| A | `app/Enums/EstadoRecordatorioTurnoEnum.php` | `8d29fc87fa76005b0cf583deef295205c5904ad0adf77c3fd8bc8fd47cdb867e` |
| A | `app/Enums/ResultadoIntentoRecordatorioEnum.php` | `cd516b39d082875e41884c0f80e8d589caf2417ab913db887ea75dc4451471a5` |
| A | `database/migrations/2026_11_06_000002_create_recordatorios_turno_tables.php` | `92718422b02ecd7642de94a20ea9a2d4af1454a2b587e06ddbf1d6903a6398a7` |
| A | `app/Models/RecordatorioTurno.php` | `e2a7ce8c68126b2cebdefc4a30085f943aec3d17320fdf1d82831b0d8d486c27` |
| A | `app/Models/IntentoRecordatorioTurno.php` | `fcfaecf363ccfa79032a15a6087575c6080eab28dda6075901cb27c7bcbf566f` |
| A | `app/Services/Turnos/RegistrarRecordatorioTurnoService.php` | `d850b3d96b28cb3c787996ad55002ccce1c99df10b54329b0044e54935519651` |
| A | `tests/Feature/Services/Turnos/RegistrarRecordatorioTurnoServiceTest.php` | `b9f613587e06317fc112ff3b80bc3040aca5d179b19c614c43f1ad743a71a082` |
| A | `app/Services/Turnos/SeleccionarRecordatoriosTurnoService.php` | `7657baacf5783d059374d46eac27acfc9f03b1645b1087e0aa1d79c021415993` |
| A | `tests/Feature/Services/Turnos/SeleccionarRecordatoriosTurnoServiceTest.php` | `a5573b796d6a0d24130a678757eff95e22cc666ab79df95369d531b7c5f5e600` |
| A | `config/recordatorios.php` | `1003fe68ec2206ff4f5b71496604bcce5eae16d053afc7f2507bf223d3136aa6` |
| A | `app/Services/Turnos/OrquestarRecordatoriosTurnoService.php` | `c479910f0484b6767ae1351585117d5daa7bd5f5c5e5a97a969db61c525edbec` |
| A | `app/Console/Commands/PrepararRecordatoriosTurnoCommand.php` | `0463b4be11c4b055d0618ae48b2cb197153bc58a0cf06d49bd014833965be025` |
| A | `tests/Feature/Console/PrepararRecordatoriosTurnoCommandTest.php` | `038ab7585b5f11d92c1d5b0109f71fea932f0893ce988af6d06124c092d7fb86` |
| A | `app/Mail/RecordatorioTurnoMail.php` | `1504ac2b0b9aaa4281e29a66f7ff5ab4b6f71c28202b3688899423ae4666eeaa` |
| A | `resources/views/emails/turnos/recordatorio.blade.php` | `97cb1e5525aaf38cfe7eeeede05fd2c0b2b4b75584e2b6e2714855af0eaf8067` |
| A | `app/Services/Turnos/PrepararContenidoRecordatorioTurnoService.php` | `096aef72dcb5e0817b0cb91b38c4161c2af7289f8a92fb9c21410fdadd5c7387` |
| A | `tests/Feature/Services/Turnos/PrepararContenidoRecordatorioTurnoServiceTest.php` | `8b71cab07efadd3815ceebc0687d0b5c659c1d9d7606edf472fb044354d2b19e` |
| M | `routes/console.php` | `b18209f2b2b63a5fa7b518a7259d13fb77c62b79e7b6e3a648c173f006637039` |
| M | `app/Services/Turnos/ValidarEnlaceAsistenciaTurnoService.php` | `d8e074974e05f59bf4685005413b7a07b1c74931b0ee081dd1f9c6a52dc6d7df` |

## Reservas y próximos límites

- **MariaDB y concurrencia:** ejecución real de migraciones, triggers heredados, locks, carreras, esperas y deadlocks pendientes de certificación aislada y autorizada.
- **Caché compartida:** funcionamiento distribuido, concesiones expiradas y exclusión entre procesos/servidores pendientes de ensayo operativo. Los tests locales no certifican esas garantías.
- **Rendimiento:** cotas de consulta/despacho, volumen y carga reales no certificados por este bloque.
- **Entrega:** transporte, Jobs, SMTP, reintentos operativos, resultados del proveedor y Mailpit pendientes. Una referencia técnica de evidencia no sustituye la comprobación que debe aportar el adaptador futuro.
- **Revalidación:** antes del transporte efectivo deberán revalidarse destinatario, estado, programación, enlace, sobre, tiempo y duplicación; el contenido preparado puede quedar obsoleto posteriormente.
- **Seguridad operativa:** custodia/rotación de claves, retención de claves anteriores, depuración efectiva del sobre, logs HTTP/proxy y registros de transporte pendientes según los contratos de UC–28/UC–27.
- **Dependencias externas:** no se implementan asignaciones de equipos ni procesos clínicos ausentes. No se certifica CA-27-13 por infraestructura genérica.
- Activación real del Scheduler/worker, presentación en clientes de correo, revisión visual manual, entrega real y producción no certificadas.
- No se ejecutaron suite global, MariaDB real, JavaScript, Vite o servicios SMTP durante este bloque ni este cierre.

**Dictamen:** checkpoint parcial satisfactorio de persistencia, elegibilidad, orquestación y preparación de contenido bajo las condiciones locales ensayadas. **UC–27 permanece incompleto.** Último subpaso completado: **6.33**. Próximo para preparar: **6.34 — Integración de transporte y Mailpit**, con decisión transversal obligatoria y solicitud específica previa. No se autoriza ni ejecuta automáticamente durante este checkpoint.
