# UC–27 — Checkpoint 6.34–6.36: transporte, reintentos y certificación local

**Fecha del checkpoint documental:** 07/11/2026.
**Estado:** implementación principal verificada localmente; **6.36 PASS con reservas**.
**Rama:** `feature/turnos-agenda-diaria-automatica`.
**HEAD previo autorizado:** `8fa848c3fb6f337d4b0c7dc90902ec6dde8b7491`.
Este documento reutiliza la evidencia definitiva de 6.36, sin nuevas pruebas ni envíos durante el checkpoint. No certifica producción ni cumplimiento integral de los 29 criterios.

## Alcance y fuentes

- **6.34:** Job con referencias técnicas, transporte SMTP local real de Laravel/Symfony y despacho mediante el sobre cifrado de UC–28.
- **6.35:** reintentos limitados, deduplicación, reclamos durables, recuperación y separación de resultados conocidos e inciertos.
- **6.36:** contraste funcional y de seguridad, integración SMTP real con Mailpit y mediciones sintéticas de selección, preparación y despacho simulado.

Se preservan el [checkpoint 6.30–6.33](turnos-agenda-diaria-630-633-uc27.md), los cierres locales de UC–05/UC–29/UC–28 y la [decisión transversal R1–R10](turnos-agenda-diaria-decision-transversal-uc28-uc27-sobre-cifrado.md), aprobada y congelada el 06/11/2026. Las declaraciones pendientes de certificados anteriores describen su estado de emisión; no se reescriben.

Fuentes originales: los tres DOCX y sus TXT auxiliares, `SHA256_6_02.txt` y `LEEME_6_02.txt`, en `docs/_codex_sources/turnos/agenda_diaria_automatica/`. Se contrastó la matriz consolidada CA-27-01–29 del compendio, las reglas de UC–27 y contratos AT/GA pertinentes. Los DOCX prevalecen. Las ocho fuentes y sus exclusiones se conservaron; el desglose operativo 6.34–6.36 procede de las solicitudes específicas, subordinadas a UXP-14 y las decisiones congeladas.

## Transporte y seguridad de entrega

`DespacharRecordatorioTurnoJob` persiste únicamente ID de recordatorio, identidad técnica de intento y ordinal, además de metadatos de cola. No transporta modelos completos, tokens, URLs con credenciales ni contenido del correo. Tiene un intento de infraestructura (`tries=1`); la política durable de negocio decide cualquier nuevo intento, evitando que una excepción de la cola repita un envío automáticamente.

`OrquestarRecordatoriosTurnoService` conserva selección/preparación compartidas, planifica los despachos y encola referencias después del commit. El Scheduler UC–27 continúa con cron predeterminado de cinco minutos, zona `app.timezone` y franja opcional; UC–29 mantiene su evento independiente de las 7:00 locales.

`DespacharRecordatorioTurnoService` coordina intento persistente, reclamo del sobre y transporte. Reutiliza el hash de enlace y el sobre separado cifrado/autenticado de UC–28. El descifrado ocurre sólo dentro del despacho autorizado; la disponibilidad de material no concede autorización adicional. Se conservan emisión/sobre/auditoría atómicos e identidad del enlace en los reintentos; no se generan nuevas credenciales para recuperar un fallo técnico.

`TransportarRecordatorioTurnoService` utiliza Laravel Mailer y Symfony Esmtp instalados, con configuración SMTP temporal comprobada, sin fallback a logs ni reenvíos globales del mailer. El adaptador actual restringe el destino SMTP a loopback; la salida hacia proveedores externos y su configuración operativa no están certificadas por este bloque.

La conexión inicial se establece antes de construir el contenido mediante callback. Inmediatamente antes del transporte se revalidan destinatario vigente, estado, futuro estricto, programación/revisión monotónica, enlace, revocación, vigencia, sobre, reclamo y duplicación. El contenido se reconstruye con los predicados existentes, sin modificar las Actions históricas de UC–06.

El mensaje contiene sólo datos administrativos permitidos y acciones vigentes: confirmar Pendiente antes del inicio; cancelar Pendiente/Confirmado con al menos doce horas corridas, incluido el límite exacto. Menos de doce horas no excluye por sí solo el recordatorio. Blade escapa los datos; no incorpora documentos, diagnóstico ni motivo clínico. Si un aviso pasa a ser informativo, conserva la frontera de revocación del sobre previamente asociado y omite la URL cuando no corresponden acciones.

La URL indispensable sólo existe transitoriamente en el correo autorizado. Las pruebas verificaron su exclusión de payloads de Job, tablas ordinarias, auditoría y logs inspeccionados, sin imprimir la credencial. El sobre se cierra y elimina su material recuperable cuando termina la necesidad de entrega; permanece la referencia técnica, sin revocar automáticamente la autorización del paciente.

## Resultados, reintentos y recuperación

Se distinguen `ACEPTADO`, fallos recuperables/definitivos e `INCIERTO`; la entrega efectiva sólo se marca con evidencia específica. Aceptación SMTP no significa recepción o lectura por el paciente.

- Primer intento más **hasta tres reintentos**, con backoff de **5, 10 y 20 minutos** después del fallo recuperable anterior.
- Ventana máxima de **una hora desde el primer fallo recuperable**, sin reiniciarla con nuevos fallos. El inicio del turno, vencimiento o pérdida de elegibilidad puede cerrarla antes.
- Sólo los fallos conocidos y recuperables permiten otro ordinal. Los definitivos, revocados, vencidos, sobres cerrados y resultados inciertos no provocan reenvío automático.
- Identidad técnica estable por recordatorio/ordinal, unicidad persistente y locks canónicos Kinesióloga → Paciente → Turno, seguidos de referencias de revisión/enlace/sobre/recordatorio/intento.
- Revalidación en cada intento de estado, programación/ABA, destinatario, plazo, credencial y titularidad. Un Job repetido, adelantado u obsoleto no transporta otra vez.
- Una interrupción anterior al reclamo permite recuperar la referencia pendiente. Después del reclamo durable se conserva incertidumbre y necesidad de resolución; no se infiere un fallo conocido ni se crea otro enlace.
- Resultados y auditoría se consolidan transaccionalmente; el SMTP queda fuera de la transacción SQL. Si se pierde el resultado después del transporte, se mantiene la incertidumbre y se evita el reenvío automático.
- `SobreEntregaEnlaceService::finalizarNecesidad()` termina la necesidad recuperable sin cambiar el estado del turno ni la autorización del paciente. Se conserva tratamiento explícito de sobres históricos no recuperables.

Los ensayos deterministas cubren aceptación inicial, fallos recuperables/definitivos, backoff, límite/ventana, repetición de Jobs, reclamos intercalados, interrupciones, reprogramación, cambio de estado/correo, revocación, expiración, sobre cerrado y ausencia de efectos ajenos sobre turnos. Los intercalados SQLite no certifican Workers concurrentes sobre MariaDB real ni entrega exactamente una vez.

## SMTP real mediante Mailpit

Mailpit **v1.33.0**, ya instalado e iniciado por el usuario. Disponibilidad verificada desde el proceso de pruebas: SMTP **127.0.0.1:1025** y HTTP/API **127.0.0.1:8025**, ambos loopback. Bandeja inicialmente vacía; no se instaló software, cambió el servicio ni sobrescribió `.env`.

La prueba optativa `RecordatoriosUc27CertificacionTest::test_smtp_real_mailpit_con_job_persistido_y_sin_credenciales_en_registros()`, activada mediante `UC27_MAILPIT_SMTP_REAL=1`, aplicó configuración exclusiva del proceso: SMTP local, sin usuario/contraseña ni URL de transporte; remitente sintético y destinatario único `example.test`.

Flujo comprobado: fixtures sintéticos → selección del recordatorio → emisión y sobre cifrado → Job persistido en cola database SQLite → pop/fire del Job → recuperación autorizada/revalidación → transporte SMTP real → consulta API del mensaje propio. No se usó Mail::fake para demostrar ese envío.

Resultados comprobados:

- **Aceptación SMTP y captura de un mensaje sintético en Mailpit.**
- Destinatario, remitente, asunto, fecha/hora, profesional, estado, escape HTML, acciones y ausencia de adjuntos/información clínica verificados.
- Correspondencia interna del enlace con el hash y validador de UC–28, sin reproducirlo.
- Un intento `ACEPTADO`, recordatorio `PROCESADO`, evidencia de entrega nula y sobre `CERRADO` con material eliminado.
- Payload persistente de 886 bytes, sin credenciales. Repetición del mismo Job por la misma cola: **un solo mensaje propio**, un intento y un enlace; atributos de Turno preservados.
- **605,156 ms** para el despacho local medido desde pop/fire hasta resultado; incluye coordinación, renderizado, SMTP y persistencia, no sólo latencia de red.
- No se vació la bandeja ni se eliminaron mensajes. Se dejó el mensaje sintético identificado por destinatario único.

Esto acredita aceptación por el SMTP local y captura en Mailpit. **No acredita entrega a un buzón real, recepción o lectura del paciente.** El Job se ejecutó controladamente en el proceso de prueba; no certifica activación operativa de un worker permanente.

## Contraste de los 29 criterios

Resumen de evidencia, sin sustituir los enunciados aprobados: **27 verificados localmente con las reservas transversales, CA-27-13 pendiente y CA-27-26 parcial**. Las clases indicadas pertenecen al corpus descrito debajo; no se declara 29/29.

| ID | Comportamiento contrastado | Estado | Evidencia y límite |
| --- | --- | --- | --- |
| CA-27-01 | Primera consulta y Seguimiento seleccionables. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest. |
| CA-27-02 | Pendiente y Confirmado elegibles. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest. |
| CA-27-03 | Exclusión de Cancelado, En atención, Atendido y Ausente. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest; pruebas de despacho/reintentos. |
| CA-27-04 | Umbral inclusivo de 24 horas, sin exigir entrega exacta. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest. |
| CA-27-05 | Altas tardías consideradas mientras sean futuras y elegibles. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest; PrepararRecordatoriosTurnoCommandTest. |
| CA-27-06 | No despachar al alcanzar o superar el inicio. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest; DespacharRecordatorioTurnoServiceTest. |
| CA-27-07 | Un recordatorio lógico por programación gestionada. | Verificado localmente | RegistrarRecordatorioTurnoServiceTest; repetición de Job con Mailpit. |
| CA-27-08 | Confirmar no produce otro recordatorio para la misma programación. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest. |
| CA-27-09 | Correo vigente y utilizable del paciente. | Verificado localmente | Selección, contenido y despacho; destinatario comprobado en Mailpit. |
| CA-27-10 | Correo inválido: incidencia, sin envío ni modificación del turno. | Verificado localmente | SeleccionarRecordatoriosTurnoServiceTest; GestionarReintentosRecordatorioTurnoServiceTest. |
| CA-27-11 | Fecha, hora, tipo, profesional y estado pertinentes. | Verificado localmente | PrepararContenidoRecordatorioTurnoServiceTest; contenido capturado por Mailpit. |
| CA-27-12 | Exclusión de información clínica innecesaria. | Verificado localmente | Pruebas de contenido/escape y marcador clínico excluido en SMTP real. |
| CA-27-13 | Equipos sólo cuando corresponden y existe vinculación efectiva. | Dependencia externa pendiente | No existe vinculación efectiva Turno–equipo en el alcance implementado. No se inventa contenido ni se declara cumplimiento completo. |
| CA-27-14 | Pendiente con al menos doce horas: confirmar y cancelar. | Verificado localmente | PrepararContenidoRecordatorioTurnoServiceTest; ValidarEnlaceAsistenciaTurnoServiceTest. |
| CA-27-15 | Pendiente con menos de doce horas y futuro: sólo confirmar. | Verificado localmente | Mismas pruebas de contenido y acciones temporales; contacto con consultorio. |
| CA-27-16 | Confirmado con al menos doce horas: informativo y cancelar. | Verificado localmente | Pruebas de contenido y validador; sin segunda confirmación. |
| CA-27-17 | Confirmado con menos de doce horas: sólo informativo. | Verificado localmente | Pruebas de contenido y despacho; sin acciones modificatorias. |
| CA-27-18 | Cancelación disponible exactamente a doce horas. | Verificado localmente | Pruebas de límites del contenido y validador compartido. |
| CA-27-19 | Enlace del turno/programación; apertura sin transición. | Verificado localmente | Emisión/validación, EnlaceAsistenciaTurnoHttpTest y asociación interna del mensaje SMTP. |
| CA-27-20 | Revalidación inmediatamente anterior al despacho. | Verificado localmente | DespacharRecordatorioTurnoServiceTest; TransportarRecordatorioTurnoServiceTest; callback posterior a conexión SMTP. |
| CA-27-21 | Reprogramación invalida información previa y permite reevaluar. | Verificado localmente | Selección/emisión/validación y despacho/reintentos; revisión monotónica y ABA. |
| CA-27-22 | Intento, aceptación y entrega con evidencia diferenciados. | Verificado localmente | RegistrarRecordatorioTurnoServiceTest y transporte; SMTP real registra ACEPTADO con evidencia_entrega nula. |
| CA-27-23 | Hasta tres reintentos dentro de una hora del primer fallo. | Verificado localmente | GestionarReintentosRecordatorioTurnoServiceTest: backoff, máximo y ventana no reiniciable. |
| CA-27-24 | Cada reintento conserva elegibilidad y deduplicación. | Verificado localmente | GestionarReintentosRecordatorioTurnoServiceTest: cambios de turno, destinatario, revocación y vencimiento. |
| CA-27-25 | Incertidumbre sin reenvío indiscriminado. | Verificado localmente | Despacho/transporte/reintentos: interrupciones y resultados conocidos conservados. |
| CA-27-26 | Fallo definitivo/indisponibilidad: registro y alerta pertinente. | Parcial | Incidencia y auditoría verificadas; falta implementar la alerta efectiva al personal. No es sólo una reserva de entorno. |
| CA-27-27 | Sin candidatos: salida normal, sin registros ficticios. | Verificado localmente | PrepararRecordatoriosTurnoCommandTest. |
| CA-27-28 | Recuperación tras interrupción sin duplicados ni efecto en agenda. | Verificado localmente | Despacho/reintentos: recuperación anterior al reclamo y bloqueo conservador después; intercalados SQLite, no concurrencia MariaDB real. |
| CA-27-29 | Envío, fallo o falta de respuesta no transicionan asistencia. | Verificado localmente | Comparación de atributos de Turno en selección/despacho/reintentos y SMTP real. |

## Rendimiento local observado

Un ensayo por carga, datos sintéticos y fixtures creados antes de medir. SQLite en memoria, dos profesionales, pacientes distintos, tipos Primera consulta/Seguimiento y estados Pendiente/Confirmado alternados, intervalos no superpuestos de treinta minutos por profesional. Un tercio de los turnos era elegible y el resto quedaba fuera de las 24 horas. Cada caso utilizó aplicación/conexión nueva en el mismo proceso PHP; no se controlaron cachés frías del sistema.

Despacho **simulado** con construcción/renderizado real del mensaje y respuesta ACEPTADO del doble de transporte. Las cargas de 10/50 despachos no representan correos SMTP reales. Se contaron consultas por eventos, sin capturar bindings ni material sensible.

| Turnos evaluados | Elegibles | Selección ms | Preparación ms | Despacho simulado ms | Consultas selección / preparación / despacho | Pico proceso PHP MiB |
| --- | --- | --- | --- | --- | --- | --- |
| 30 | 10 | 1085,307 | 754,222 | 1824,424 | 171 / 240 / 1005 | 52 |
| 150 | 50 | 560,005 | 1018,639 | 4101,164 | 851 / 1340 / 5340 | 52 |

**Hay patrón N+1/relecturas por registro**: selección 17n+1 consultas respecto de candidatos elegibles y consultas reiteradas de asociaciones, locks y revalidaciones; el despacho simulado de 150 turnos evaluados/50 elegibles ejecutó **5340 consultas**. No se certifica ausencia de N+1 ni escalabilidad productiva. Parte del coste corresponde a garantías transaccionales, que deberán preservarse al evaluar mejoras bajo alcance aprobado; no se optimizó código en este checkpoint.

La diferencia de tiempos entre cargas no demuestra una mejora al crecer: son observaciones únicas sujetas a calentamiento/variación del entorno. El pico de memoria corresponde al proceso PHP, no a memoria incremental aislada de cada fase. No se inventan umbrales; las referencias históricas de tiempo no se convierten en nuevos compromisos aprobados.

## Evidencia reutilizada y aislamiento

| Ejecución definitiva | Tests | Assertions | Resultado | Exit | Tiempo |
| --- | --- | --- | --- | --- | --- |
| Regresión focalizada conjunta de UC–27 y contratos directamente afectados | 277 | 1832 | PASS | 0 | 03:40.447 |
| SMTP real y mediciones locales, en ejecución separada | 3 | 62 | PASS | 0 | 00:26.862 |

No sumar estas cantidades ni equipararlas a una suite global. Los resultados previos de 6.34 (235/1408) y 6.35 (255/1575) son antecedentes de conjuntos distintos, no ejecuciones adicionales de este checkpoint.

Corpus exacto de los 277 tests, PHPUnit directo con `phpunit.xml`, perfil PHP aislado, `--do-not-cache-result --colors=never --fail-on-risky --fail-on-warning`:

- `tests/Feature/Console/PrepararRecordatoriosTurnoCommandTest.php`.
- `tests/Feature/Services/Turnos/PrepararContenidoRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/RegistrarRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/SeleccionarRecordatoriosTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/SobreEntregaEnlaceServiceTest.php`.
- `tests/Feature/Services/Turnos/TransaccionTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/TransportarRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/DespacharRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/GestionarReintentosRecordatorioTurnoServiceTest.php`.
- `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php`.

La ejecución separada utiliza `tests/Feature/Services/Turnos/RecordatoriosUc27CertificacionTest.php`: un ensayo SMTP optativo y dos casos de carga. No se repitió dentro de la regresión para evitar otro correo. No hubo failures ni errors en las ejecuciones definitivas.

PHP 8.2.12 / PHPUnit 11.5.55. Bootstrap efectivo comprobado antes de ejecutar: `testing`, SQLite `:memory:`, sólo PDO SQLite y sin PDO MySQL, configuración sin caché, storage/discos y vistas exclusivos, logging con `Monolog\Handler\NullHandler`. Las variables se aplicaron sólo al proceso. No se utilizaron datos reales ni bases MariaDB.

Sintaxis PHP de los **14 archivos**, Pint `--test` acotado, diff y whitespace: **PASS** en 6.36. Migraciones compartidas intactas y verificadas en SQLite aislado; compilar DDL MariaDB offline no se presenta como ejecución real. Actions históricas de confirmación/cancelación intactas, sin modificar reglas de UC–06/UC–07. No se repitieron suite global, JavaScript, Vite o MariaDB.

En este checkpoint se cotejaron nuevamente las catorce huellas, fuentes, inventario Git, documentación, diff y whitespace. **No se ejecutaron PHPUnit, Pint, builds, migraciones ni nuevos envíos SMTP.** La evidencia corresponde al código definitivo de la tabla siguiente.

## Archivos y huellas del código certificado

M/A respecto del HEAD previo. A estas **14 rutas (5 M y 9 A)** se agregan `AGENTS.md` (M) y este certificado (A): **16 archivos, seis modificados y diez nuevos**. Los SHA-256 identifican archivos de código/pruebas, no credenciales.

| Estado | Archivo | SHA-256 |
| --- | --- | --- |
| A | `app/Services/Turnos/TransportarRecordatorioTurnoService.php` | `87814843fb21f8314f50674ae87ae7e4530f0c988d250cd6957acd4f492a1594` |
| A | `app/Services/Turnos/DespacharRecordatorioTurnoService.php` | `b203c7cbd3079d82d4720e4c4046b3af5c4f2a8730b2b9f4c8c26a83749bf794` |
| A | `app/Jobs/DespacharRecordatorioTurnoJob.php` | `f4cc6b62629fbb79eb03e503d4447d2fde5469c88eae8db1ffe361c49d644ab6` |
| M | `app/Services/Turnos/OrquestarRecordatoriosTurnoService.php` | `32d32947dcb444fb0ee7c7d6bea28af8554aa2ad70d556ccfab018ca5db8bd13` |
| M | `tests/Feature/Console/PrepararRecordatoriosTurnoCommandTest.php` | `8c8212f8586792893585ec75c7ac2b87461501b68f0712ebd95b66af60a34532` |
| A | `tests/Feature/Services/Turnos/DespacharRecordatorioTurnoServiceTest.php` | `70155f4dec24a098c84602981bd33567f3e1303b59e036a501094fa1408f6d4f` |
| A | `tests/Feature/Services/Turnos/TransportarRecordatorioTurnoServiceTest.php` | `f475253e826dccdc8026ae8b76694a8ef5fc44f90493dd8e33b8fffbc31e6aff` |
| M | `app/Services/Turnos/PrepararContenidoRecordatorioTurnoService.php` | `4bed1eb4230ab092a4d5c07c17f021bdf0972c2cec1b39cff81bcb8a4618fad4` |
| A | `tests/Support/StreamSmtpRecordatorio.php` | `5f3b236db62af44a184ed0173f4cd1631a58f41edc2e53c247e0d28d525daf29` |
| M | `app/Models/RecordatorioTurno.php` | `0ff62f17923d5e17bef4a9aba0ee4af711c35fde4fecdf9f284ef6fae1500788` |
| M | `app/Services/Turnos/SobreEntregaEnlaceService.php` | `becae321a98cf053147d9fff71e71b28b2607f1bd0126323106e333c9004e7b3` |
| A | `app/Services/Turnos/GestionarReintentosRecordatorioTurnoService.php` | `3b9aca4f651c0d269ee31b019daeb2f8a335ff7ba2520f0fea98fbbbe22442bb` |
| A | `tests/Feature/Services/Turnos/GestionarReintentosRecordatorioTurnoServiceTest.php` | `51ace17df73fce19ded346fab6adaafb9e5cf447791e254d0d0522c8726edec6` |
| A | `tests/Feature/Services/Turnos/RecordatoriosUc27CertificacionTest.php` | `dd1543d975010283d570ec61fc9683c2a91f2b571e15572d5f9210199a0cd8bf` |

## Reservas y dictamen

- **CA-27-13:** dependencia externa de vinculación efectiva con equipos; no satisfecha por un catálogo genérico ni por omitir equipos del correo.
- **CA-27-26:** registro de fallos/incidencias disponible; **alerta efectiva al personal ausente**, pendiente de implementación bajo alcance autorizado.
- **Rendimiento:** N+1/relecturas y coste por recordatorio pendientes de evaluación/corrección; sin certificación de producción, carga fría, estrés o gran volumen.
- **MariaDB real:** ejecución de migraciones/triggers, locks, concurrencia de Workers, carreras, esperas y deadlocks pendientes. SQLite no los certifica.
- **Operación:** Scheduler/worker permanentes, exclusión mediante caché compartida, configuración/despliegue, custodia/rotación de claves, limpieza operativa de sobres y logs HTTP/proxy/transporte requieren validación específica.
- **Correo:** Mailpit acredita captura local, no buzón/lectura del paciente, clientes de correo, entregabilidad, proveedor externo ni comportamiento de producción. Presentación/revisión visual manual pendiente.
- **Integración final:** recorridos entre UC–05/UC–27/UC–28/UC–29 y dependencias externas conservan sus reservas y requieren el alcance específico siguiente. No se incorporan operaciones clínicas o de reservas ajenas.

**Dictamen:** UC–27 implementado en su alcance principal y verificado localmente; **6.34 y 6.35 verificados localmente, 6.36 completado con reservas**. No se declara cumplimiento integral de todos los criterios, certificación operativa ni producción.

Último subpaso completado: **6.36**. Próximo para preparar: **6.37 — Integración entre casos de uso**, con solicitud y alcance específicos; **no autorizado automáticamente ni ejecutado durante este checkpoint**. El commit local de cierre sólo registra las dieciséis rutas delimitadas y no autoriza operaciones remotas.
