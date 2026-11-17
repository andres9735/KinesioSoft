# Subpaso 6: consulta y salidas sin traslado

Las cuatro fuentes aprobadas del incremento se conservan. El contrato de emisión del subpaso 5 se materializa mediante GET/HEAD `/turnos/adelanto?token=...` y POST `/turnos/adelanto/rechazar`. No se implementa aceptación ni reprogramación.

## Enlace y navegador

`ConsultarOfertaAdelantoService` busca el hash y comprueba asociación de despacho, oferta, paciente, profesional, turno y vencimiento. La consulta no escribe estados ni auditorías: incluso al alcanzar el vencimiento sólo informa el estado derivado; el cierre durable corresponde al proceso de ciclo. Las ofertas cerradas o vencidas muestran un resultado sin detalle de agenda, y las credenciales inválidas bloquean todo detalle. Una oferta vigente muestra ambos intervalos registrados, profesional, vencimiento exacto y zona, con Rechazar oferta como única acción.

El canal carece de middleware web y sesión del portal. El middleware sanea URL, credencial, cookies y cuerpo antes de continuar; exige HTTPS y origen canónico, limita intentos y usa no-store, CSP, DENY, nosniff y noindex. GET genera una autorización cifrada de propósito exclusivo y quince minutos, con referencias y CSRF, sin credencial original. HEAD no la genera. Cookie Secure, HttpOnly, SameSite Strict y path limitado. El POST no lleva token del correo: exige cookie válida, CSRF, Origin canónico y acción explícita; valida además Sec-Fetch-Site cuando está presente. Referrer-Policy strict-origin permite el Origin del formulario sin divulgar ruta ni query. No hay credenciales en HTML, redirecciones ni auditorías.

## Cierre y continuación

`CerrarOfertaAdelantoService` usa Kinesióloga → Paciente → Turno, relee oferta y despacho y muestrea el reloj después de los locks. Desde el instante exacto del vencimiento no registra rechazo: registra vencida. El cierre, la liberación efectiva de la reserva, la eliminación de material recuperable y la salida UNIQUE por oferta son atómicos e idempotentes. El rechazo audita al paciente asociado. Los cambios del original se tratan como salida sin traslado. Ningún cierre cancela, mueve o cambia el estado del turno original ni crea otra intención.

La salida referencia la evaluación original de la cancelación. La continuación durable empieza pendiente y se completa una sola vez. Se ejecuta en una transacción separada para no conservar el lock del paciente anterior al adquirir el del siguiente: toma K, reutiliza selector y activación y excluye por paciente a todos los ya ofertados. Exige de nuevo cupo libre y al menos cuatro horas; prepara el despacho de la siguiente oferta y registra su identidad en la misma transacción. Si no hay candidato, plazo o posibilidad de activar, registra el agotamiento sin reservar. Un fallo deja pendiente recuperable y revierte la siguiente oferta. No se crea otra evaluación inicial.

`turnos:procesar-cupos` continúa con su sondeo de un minuto y conexión database. Agrega Jobs de ciclo para ofertas activas, aun vencidas sin visitas, y para salidas pendientes. `RevisarCicloOfertaAdelantoJob` cierra si corresponde y luego continúa fuera de la transacción del cierre. El sondeo recupera el trabajo tras una interrupción; referencias de cola repetidas no duplican salidas ni ofertas. No se inicia Scheduler ni worker durante las verificaciones locales.

## RF12.5 y transporte

La especificación aprobada de UC-27/UC-28 (`KinesioSoft_Etapa3_302_GrupoA_UC27_UC28_APROBADO_V1.0`, excepción 8.a), usada como límite vigente de RF12.5, permite hasta tres reintentos durante la hora posterior al primer fallo. Se reutilizan las esperas técnicas progresivas de UC-27: 5, 10 y 20 minutos desde cada fallo conocido. El instante del fallo se captura antes de esperar los locks de registro: esa espera no extiende la hora permitida. La hora es inclusiva; la vigencia de la oferta es estricta y nunca se extiende. Sólo se inicia el siguiente intento si ambas ventanas y su posición lo permiten.

Cada intento tiene identidad UNIQUE despacho/número y registra INCIERTO antes del transporte. La posición viaja en el Job: repetir un Job viejo no consume el próximo reintento. Una respuesta SMTP negativa explícita 4xx es recuperable, una 5xx es definitiva. Un fallo de conexión antes de DATA permite reintentar. Errores sin evidencia de rechazo son inciertos y no habilitan reenvío automático. Los textos/debug de transporte no se persisten ni propagan.

Las ventanas, el destinatario, el original y el destino se revalidan de nuevo después del reclamo y, con SMTP, después de conectar, inmediatamente antes de DATA. No se conserva una transacción ni locks durante el transporte. Si una espera consume la vigencia o la hora permitida, no se envía.

Los reintentos conservan oferta, despacho, hash, URL cifrada y vencimiento. Un fallo definitivo o agotamiento de los límites queda auditado y cierra la oferta; la continuación de la misma evaluación queda durable. Una caída entre el registro técnico del fallo y su cierre se recupera por el sondeo del despacho terminal. Los resultados inciertos conservan su evidencia y esperan el cierre por vencimiento. El material recuperable se elimina al cerrar, preservando hash, identidad e intentos. No se promete entrega SMTP exactamente una vez.

## Límites de certificación

Las pruebas funcionales usan SQLite en memoria y correo simulado. Quedan pendientes MariaDB aislada (rechazo frente a vencimiento, dos continuadores, dos entregas del mismo intento, ventanas de fallo/cierre, alta y cambios de agenda) y el recorrido visual en navegador con HTTPS/cookies reales, incluida revisión de logs HTTP/proxy. CA-R11 y aceptación corresponden al subpaso 7. El HTML y las rutas pueden verificarse mediante el cliente HTTP de Laravel sin iniciar servidores; eso no equivale a una prueba real de navegador ni a SMTP externo.

## Archivos del subpaso

Se crean el controlador `OfertaAdelantoController`, el middleware `ProtegerAccesoOfertaAdelanto`, la vista `resources/views/turnos/adelanto/show.blade.php` y los servicios `ConsultarOfertaAdelantoService`, `CerrarOfertaAdelantoService` y `ContinuarCupoAdelantoService`. Se agrega `RevisarCicloOfertaAdelantoJob` y la migración aditiva `2026_11_16_000003_add_salidas_e_intentos_ofertas_adelanto.php`, junto con los modelos `SalidaOfertaAdelanto` e `IntentoDespachoAdelanto`. Las pruebas nuevas son `tests/Feature/Turnos/OfertaAdelantoSalidasTest.php` y `tests/Support/SimularEnvioOfertaAdelanto.php`.

Se extienden `routes/web.php`, `ProcesarCuposLiberadosCommand`, `DespacharOfertaAdelantoJob`, `DespacharOfertaAdelantoService`, `OfertaAdelanto` y `DespachoOfertaAdelanto`, además del seguimiento de `AGENTS.md` y este informe. Los archivos de subpasos previos siguen sin staging ni commit; su condición de no seguidos no significa que se hayan creado en este subpaso. No se cambian las cuatro fuentes aprobadas, el informe histórico del subpaso 5 ni `docs/archivo/`.

## Trazabilidad de pruebas nuevas

Archivo: `tests/Feature/Turnos/OfertaAdelantoSalidasTest.php`. Los proveedores de datos cuentan cada variante como una prueba.

| Criterio | Evidencia focalizada |
| --- | --- |
| CA-R01 | Correo simulado → ruta HTTP real; GET/HEAD sin escrituras, agenda mínima y vencimiento, token inválido/no escalar, asociación con otro paciente, cookie de otro propósito, CSRF/origen/HTTPS. |
| CA-R02 | Rechazo explícito y repetido, una salida/auditoría y original intacto; rollback conjunto del cierre y reserva. |
| CA-R03 | Vencimiento exacto en GET sin escrituras y POST sin rechazo; cierre por Job y sondeo sin visitas, repetición idempotente. |
| CA-G13 | Mismo enlace/hash/oferta/vencimiento, espera progresiva, posición de Job, tres reintentos y hora inclusiva; espera de registro que no extiende esa hora y revalidación anterior al transporte. |
| CA-G14 | Fallo definitivo auditado, reserva resuelta, original intacto y continuación; recuperación del fallo técnico confirmado antes de un cierre interrumpido. Resultado incierto sin reenvío y cierre al vencer. |
| CA-G15 | Reutilización de evaluación, siguiente por prioridad, exclusión del paciente con varios turnos, cuatro horas exactas, ocupación por un alta, agotamiento y disponibilidad habitual; rollback de continuación y repetición sin duplicación. |

El caso de 101 ofertas comprueba además que el sondeo pagina y encola cada referencia una sola vez en esa ejecución. Es un conjunto de datos de volumen para el comando; no declara elegibles activaciones incompatibles del mismo cupo.

## Verificación definitiva — 16/11/2026

Rama `feature/turnos-asignacion-automatica`, HEAD `6d8afd7a54a20b5b25de3592c96f842c22b0a390`, sin staging ni commit. Se preservó el estado previo, incluidos los archivos no seguidos de los subpasos anteriores. Las cuatro fuentes aprobadas conservaron sus SHA-256; `docs/archivo/` no se inspeccionó ni modificó.

Comando del arnés ignorado:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File storage/framework/testing/asignacion-salidas-20261116-06/verificar.ps1 -Fase regresion
```

Resultado: **429 pruebas / 1991 aserciones PASS**, cero errores/fallos/omisiones, salida **0**, PHP 8.2.12 / PHPUnit 11.5.55, 120,613 segundos. Incluye **36 pruebas / 164 aserciones** nuevas de `OfertaAdelantoSalidasTest` (58,870 segundos); no se suman al total. Corpus restante: `PrimeraEmisionAdelantoTest`, `OfertaAdelantoTest`, selector y validador de destino, disponibilidad individual/agregada, `ConfirmarPrimeraConsultaActionTest`, Actions de Agenda y `ResolverAsistenciaEnlaceHttpTest`.

La corrida focalizada previa de 34/156, las regresiones intermedias y sus salidas no se suman ni representan los últimos refuerzos. La evidencia definitiva es `storage/framework/testing/asignacion-salidas-20261116-06/regresion.junit.xml`.

Preflight: entorno testing sin configuración cacheada, SQLite `:memory:` efectivo por PDO/PRAGMA sin archivo, sólo driver sqlite y PDO MySQL deshabilitado también en hijos; correo/cache/sesión array, Queue fake sobre cola predeterminada sync, NullHandler y storage/vistas/cachés exclusivos. Clave efímera no impresa. La migración nueva y las existentes sólo se ejecutaron internamente sobre SQLite en memoria; no hubo migraciones operativas ni conexión a base persistente.

Sintaxis PHP (`php -n -l`) y Pint `--test` de los 17 PHP del subpaso: PASS, salida 0. `npm run build`: PASS, Vite 7.3.1, 96 módulos, 6,08 segundos, salida 0. El primer intento falló con spawn EPERM dentro del sandbox; se repitió con autorización fuera de él. Sólo se emitió el aviso existente de Browserslist desactualizado; no se instalaron dependencias. Build y arnés permanecen ignorados.

`git diff --check`: sin salida, código 0. Índice sin cambios. No se iniciaron servidores, Scheduler, workers ni SMTP real, y no se avanzó al subpaso 7. La certificación es funcional local con los pendientes de navegador/MariaDB/SMTP indicados antes, sin atribuir rendimiento productivo ni concurrencia física a SQLite.
