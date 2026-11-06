# Agenda Diaria Automática — Certificación local UC–28, 6.26–6.29

**Fecha:** 06/11/2026.

**Dictamen:** UC–28 implementado y verificado localmente, con reservas. Los subpasos 6.26–6.29 quedan completados en el alcance local ensayado. No constituye certificación operativa ni de producción.

## Estado revisado y fuentes

Rama: `feature/turnos-agenda-diaria-automatica`. HEAD previo al checkpoint: `690e76cc6649ad71be57f84150abb4dc4ca743a1`. El commit de cierre incorpora este certificado; no se anticipa su hash.

El preflight encontró ocho archivos versionados modificados, once archivos nuevos de implementación/pruebas y staging vacío. Las diecinueve huellas SHA-256 coinciden exactamente con las registradas al terminar la regresión definitiva. En este checkpoint sólo se crean los dos documentos y se actualiza el seguimiento activo de `AGENTS.md`; el código funcional y las pruebas permanecen intactos.

Se verificó la disponibilidad de las ocho fuentes locales congeladas en `docs/_codex_sources/turnos/agenda_diaria_automatica/`: los siete contenidos enumerados conservan sus hashes y tamaños del manifiesto de 6.02. Las exclusiones permanecen intactas. `docs/archivo/` permanece untracked, protegido, sin inspección ni modificación.

El antecedente certificado es [6.22–6.25](turnos-agenda-diaria-622-625-uc28.md). Sus contratos entonces pendientes se resuelven en el alcance descrito aquí, conservando sus resultados como evidencia histórica independiente. El compendio V1.0 establece UXP-14; la delimitación numérica de estos subpasos procede de las solicitudes específicas autorizadas. No se alteran los originales congelados.

## Alcance implementado

| Subpaso | Resultado local |
| --- | --- |
| 6.26 | Autorización específica GET → POST mediante cookies protegidas; resolución mediante las Actions históricas de UC–06 y revalidación transaccional. |
| 6.27 | Presentación de decisiones, resultados, rechazos y errores recuperables, con navegación PRG y sin datos clínicos. |
| 6.28 | Revisión de seguridad y correcciones de saneamiento de cookies, separación de propósitos y origen HTTPS canónico, con pruebas de rechazo. |
| 6.29 | Recuperación comprobable de respuesta perdida, contraste de los 34 criterios e infraestructura mínima del sobre durable cifrado aprobada para la entrega futura. |

## Autorización, resolución y privacidad

`AutorizacionNavegadorEnlaceService` transforma el acceso válido en una autorización cifrada y autenticada del navegador, vinculada al enlace, revisión, propósito, caducidad, CSRF y operaciones. La cookie `__Secure-ks_asistencia` es Secure, HttpOnly, SameSite=Strict, sin dominio ampliado y limitada a `/turnos/asistencia`. Su ventana máxima es de quince minutos, acotada por el inicio; los límites efectivos se revalidan en servidor con precisión temporal.

El recorrido específico no exige login convencional ni utiliza la sesión general del portal. El POST exige decisión expresa, cookie válida, CSRF, origen HTTPS autorizado y controles de frecuencia. Identificadores de turno, paciente o profesional enviados por el visitante no amplían la autorización.

GET/HEAD no ejecutan transiciones, revocaciones, consumo de enlaces, auditorías funcionales o intenciones de cupo. GET puede preparar cookies del navegador; HEAD no las crea. Abrir o previsualizar no equivale a confirmar. La credencial original no se incorpora al HTML; el formulario contiene sólo los datos de control indispensables y una correlación que no autoriza por sí misma.

`ResolverAsistenciaEnlaceService::ejecutar()` conserva los locks canónicos kinesióloga → paciente → turno y añade relectura/bloqueo de revisión y enlace. Revalida programación, revisión monotónica, revocación, estado, actor y tiempo, coordinados con las Actions compartidas. Utiliza el paciente persistido y el canal real `ENLACE_SEGURO`.

`ConfirmarAsistenciaTurnoAction` y `CancelarTurnoAction` permanecen intactas, al igual que sus pruebas históricas. Se preservan Pendiente → Confirmado estrictamente antes del inicio y Pendiente/Confirmado → Cancelado con al menos doce horas corridas, incluido el límite exacto. Confirmar conserva la ocupación y la credencial; cancelar impide nuevas operaciones modificatorias con el enlace previo. No se recrean reservas ni se alteran datos clínicos, programación u otros turnos.

Transición, auditoría obligatoria y, al cancelar, intención durable de evaluación del cupo, permanecen atómicas. Se conserva la idempotencia sin segunda transición o intención. UC–07 y Configuración de Agenda no se modifican; esta regresión no constituye recertificación global de UC–07.

Los resultados usan redirección 303 a una ruta fija y una vista administrativa mínima, con mensajes comprensibles, escape Blade, decisión explícita y accesibilidad básica. Se aplican no-store, no-referrer, CSP sin recursos externos, protección de framing y saneamiento previo de las entradas controladas por el recorrido. La revisión de 6.28 corrigió cookies no escalares antes del saneamiento, cookies con propósito incorrecto y acceso desde un host distinto del origen canónico. Los errores no propagan credenciales ni excepciones originales a los mensajes o logs controlados.

## Respuesta perdida y CA-28-33

Antes del POST se preparan correlaciones independientes por acción y cookies cifradas de recuperación, con propósito, enlace, revisión, acción y vencimiento propios. El UUID visible de operación no es una credencial: exige la cookie correspondiente. La autorización de recuperación sólo permite consultar el resultado; no rehabilita un enlace cancelado o vencido para modificar el turno.

El evento técnico `enlace_asistencia_resultado` se registra atómicamente con la decisión y conserva SHA-256 de la correlación, referencia de auditoría efectiva o replay de negocio y contexto mínimo sin secretos. No se modifica la auditoría histórica de UC–06. Una transición puede producir su auditoría de negocio y este comprobante técnico; un replay de la misma operación no los duplica.

`ResolverAsistenciaEnlaceService::recuperar()` verifica el resultado registrado sin ejecutar Actions ni inferir éxito a partir del estado actual. Las pruebas descartan la respuesta del POST y recuperan confirmación o cancelación con la autorización preparada previamente, incluso después de invalidar el enlace modificatorio. Si no existe resultado registrado, se informa esa ausencia sin afirmar éxito. CA-28-33 queda verificado localmente; no equivale a certificar fallos de red reales en producción.

## Sobre durable cifrado y decisión transversal

Referencia normativa: [Aclaración técnica transversal UC–28 / UC–27 — Sobre técnico durable de entrega con cifrado autenticado](turnos-agenda-diaria-decision-transversal-uc28-uc27-sobre-cifrado.md), **APROBADA Y CONGELADA el 06/11/2026**, reglas R1–R10 completas.

La tabla separada `sobres_entrega_enlace` mantiene una asociación única y FK restrictiva con el enlace, material cifrado, referencias técnicas y fechas de ciclo de vida. `EnlaceAsistenciaTurno` conserva exclusivamente SHA-256 de la credencial. No se persisten tokens o URLs con credenciales en claro.

`SobreEntregaEnlaceService::preparar()` cifra y autentica el material dentro de la emisión. Emisión, sobre y auditoría se consolidan juntos; un fallo de cifrado, persistencia o auditoría revierte la nueva emisión. Repetir la emisión devuelve la misma identidad y referencia durable, sin otro token, URL o auditoría. Los registros históricos sin sobre se reconocen como no recuperables para entrega, sin backfill, reconstrucción desde hashes o reemisión silenciosa.

Se utiliza el cifrado autenticado instalado de Laravel: AES-256-CBC con MAC, clave activa y claves anteriores. El modelo oculta material y reclamo en representaciones ordinarias y bloquea serialización. No hay descifrado automático en consultas.

`conMaterialParaDespacho()` es una interfaz interna limitada a contexto de consola, sin ruta HTTP ni usuario autenticado; rechaza una transacción exterior. Bajo locks revalida enlace, revisión, programación, revocación y tiempo, comprueba autenticidad y asociación del contenido cifrado y de la URL HTTPS, recifra con la clave activa y registra un reclamo antes de exponer el material transitorio al callback. El callback se ejecuta después del commit y fuera de la transacción.

El reclamo queda inicialmente INCIERTO. `registrarResultado()` distingue ACEPTADO, FALLO_CONFIRMADO, FALLO_DEFINITIVO e INCIERTO y exige la titularidad del reclamo; uno anterior no puede cerrar un despacho posterior. Sólo un fallo confirmado puede habilitar otra recuperación autorizada del mismo material. Una excepción del callback deja incertidumbre y no habilita reenvío automático.

El cierre aceptado o definitivo elimina el material recuperable mediante NULL y conserva la identidad técnica cerrada; no permite reabrir el sobre. Su vencimiento inicial no supera el inicio del turno. `limitarVigencia()` sólo reduce ese límite y `depurar()` elimina el material vencido o invalidado sin descifrarlo. Estos mecanismos requieren su invocación por los procesos operativos posteriores; no se agregó un Scheduler de limpieza.

La rotación se verificó localmente con clave anterior y recifrado con la activa; retirar prematuramente la clave anterior falla de forma cerrada. La custodia, retención segura de claves anteriores, rotación y depuración efectivas requieren procedimientos operativos y certificación posterior.

No se implementan correo, Jobs, colas, Mailpit ni política de reintentos de UC–27. La referencia durable permite que los futuros mensajes persistentes transporten sólo IDs. UC–27 deberá aplicar GA-05/GA-06, destinatario vigente, identidad de recordatorio, límites y ventana de reintentos y cierre/depuración, sin reemitir enlaces ni tratar resultados inciertos como fallos confirmados. No se promete entrega exactamente una vez ni atomicidad entre base de datos y transporte externo.

## Contraste de los 34 criterios

Fuente: matriz consolidada 3.03 del compendio aprobado `Planificacion Agenda Diaria Automatica(1).docx`, tabla de UC–28; TXT auxiliar homónimo para localización. Los textos de esta tabla son síntesis del comportamiento, no sustituyen los enunciados originales.

**V:** verificado en las pruebas locales del recorrido. **L:** verificado localmente con las reservas de motor/operación de este certificado. **D:** dependencia externa, sin cumplimiento integral certificado. Ninguna clasificación equivale a certificación de producción.

Referencias de evidencia: **EN** = EnlaceAsistenciaTurnoTest; **EM** = EmitirEnlaceAsistenciaTurnoServiceTest; **VA** = ValidarEnlaceAsistenciaTurnoServiceTest; **RE** = ResolverAsistenciaEnlaceServiceTest; **HG** = EnlaceAsistenciaTurnoHttpTest; **HP** = ResolverAsistenciaEnlaceHttpTest; **AC** = Actions históricas de confirmar/cancelar; **TX** = TransaccionTurnoServiceTest. Las rutas completas están en el conjunto de regresión.

| Criterio | Comportamiento contrastado | Estado | Evidencia y límite |
| --- | --- | --- | --- |
| CA-28-01 | Acceso válido sin login convencional. | V | HG, HP. |
| CA-28-02 | Autenticidad, integridad, vigencia y no revocación. | L | EN, VA, HP; operación y motor real pendientes. |
| CA-28-03 | Alcance específico de turno y programación. | L | VA, RE, HP; identificadores manipulados no autorizan. |
| CA-28-04 | Sin otros turnos, portal o información clínica. | V | HG, HP; recorrido administrativo mínimo. |
| CA-28-05 | Rechazo privado de credenciales inválidas. | V | VA, HG, HP. |
| CA-28-06 | Sólo cita, estado y acciones esenciales. | V | HG, HP. |
| CA-28-07 | Validez del enlace separada del permiso de acción. | L | VA, RE, HP; estado y límites revalidados. |
| CA-28-08 | Apertura y previsualización sin modificar el turno. | L | HG, HP; GET/HEAD y repetición sin efectos funcionales. |
| CA-28-09 | Decisión expresa antes del cambio. | V | HP; GET no decide y POST exige confirmación. |
| CA-28-10 | Confirmar sólo Pendiente antes del inicio. | L | VA, RE, HP, AC. |
| CA-28-11 | Inicio exacto excluye confirmar. | L | RE, HP, AC; límites temporales incluidos. |
| CA-28-12 | Únicamente Pendiente → Confirmado. | L | RE, HP, AC. |
| CA-28-13 | Ocupación conservada, sin reserva o presencia nueva. | L | RE, AC; atributos y efectos preservados. |
| CA-28-14 | Confirmación repetida sin otra transición. | L | RE, HP, AC. |
| CA-28-15 | Cancelar Pendiente con al menos doce horas. | L | RE, HP, AC. |
| CA-28-16 | Cancelar Confirmado con al menos doce horas. | L | RE, HP, AC. |
| CA-28-17 | Límite inclusivo de doce horas. | L | RE, HP, AC; precisión temporal comprobada. |
| CA-28-18 | Menos de doce horas: rechazo y contacto. | L | VA, HP, AC. |
| CA-28-19 | Estados incompatibles o inicio impiden nuevas decisiones. | L | VA, RE, HP; recuperación de resultado sólo de lectura. |
| CA-28-20 | Cancelación canónica con conservación histórica. | L | RE, HP, AC. |
| CA-28-21 | Liberación efectiva una sola vez. | L | RE, AC; concurrencia MariaDB pendiente. |
| CA-28-22 | Motivo textual no obligatorio. | V | HP, AC. |
| CA-28-23 | Seguimiento sin alterar plan, sesiones o cómputos clínicos. | D | AC/RE protegen atributos y otros turnos; integración clínica externa no disponible ni certificada. |
| CA-28-24 | Confirmar permite cancelar con el mismo enlace válido. | L | RE, HP; no consumo automático al confirmar. |
| CA-28-25 | Cancelación impide nuevas modificaciones con el enlace previo. | L | EN, VA, RE, HP; recuperación sólo consulta. |
| CA-28-26 | Programación anterior rechazada, incluso ABA. | L | EN, VA, RE, HP; triggers MariaDB no ejecutados. |
| CA-28-27 | Revalidación al consolidar prevalece sobre la pantalla abierta. | L | RE, HP, TX; locks y rollback locales, motor real pendiente. |
| CA-28-28 | Dobles clics y reintentos no duplican efectos. | L | RE, HP, AC; sin certificación concurrente real. |
| CA-28-29 | Canales simultáneos sin sobrescrituras inválidas. | L | RE, HP, TX; intercalaciones/secuencias locales, concurrencia MariaDB pendiente. |
| CA-28-30 | Auditoría completa y canal real Vía enlace. | L | RE, HP, AC; sin credenciales. |
| CA-28-31 | Reflejo en Mis turnos y agenda profesional. | L | HP; lecturas compartidas UC–06/UC–05/UC–29, sin recertificar el proceso automático completo. |
| CA-28-32 | Habilitación única de evaluación de cupo elegible. | D | AC/RE verifican intención durable única; consumidor, elegibilidad y evaluación externos pendientes. |
| CA-28-33 | Comprobar resultado antes de repetir una respuesta incierta. | L | RE, HP; correlación y recuperación previa verificadas, sin inferir éxito. |
| CA-28-34 | Mensajes comprensibles sin divulgar datos sensibles. | V | HG, HP; revisión visual manual pendiente. |

Resultado: siete V, veinticinco L y dos D. No se declara cumplimiento integral sin reservas de los 34 criterios.

## Regresión definitiva reutilizada

**340 tests / 2843 assertions PASS, exit code 0**, sin errores ni fallos, tiempo 01:20.237, PHP 8.2.12 / PHPUnit 11.5.55. La evidencia corresponde a los bytes de las diecinueve rutas consignadas más abajo; no se repite PHPUnit durante este checkpoint documental.

Conjunto focalizado conjunto, invocado directamente mediante PHPUnit y `phpunit.xml`, con `--do-not-cache-result --colors=never --fail-on-risky --fail-on-warning` y perfil aislado:

- `tests/Feature/Services/Turnos/EnlaceAsistenciaTurnoTest.php`.
- `tests/Feature/Services/Turnos/EmitirEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/ValidarEnlaceAsistenciaTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/ResolverAsistenciaEnlaceServiceTest.php`.
- `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php`.
- `tests/Feature/Turnos/ResolverAsistenciaEnlaceHttpTest.php`.
- `tests/Feature/Actions/Turnos/ConfirmarAsistenciaTurnoActionTest.php`.
- `tests/Feature/Actions/Turnos/CancelarTurnoActionTest.php`.
- `tests/Feature/Services/Turnos/TransaccionTurnoServiceTest.php`.
- `tests/Feature/Services/Turnos/SobreEntregaEnlaceServiceTest.php`.

Las pruebas nuevas del sobre cubren persistencia cifrada, rollback por cifrado/persistencia/auditoría, repetición sin segundo token, recuperación autorizada fuera de transacciones, contextos rechazados, alteración o sustitución de material, revocación, expiración, ABA, cierre, incertidumbre, reclamos antiguos, reducción de vigencia, rotación, claves retiradas e históricos sin sobre. Sus ejecuciones focalizadas sucesivas registraron 19 tests / 131 assertions, 3 / 13 y 2 / 9, todas PASS, exit 0; no son una ejecución única ni se suman a la regresión definitiva.

Una ejecución inicial del nuevo harness tuvo diecinueve errores de teardown al intentar revertir una migración histórica. Se corrigió exclusivamente el aislamiento de la nueva clase: conexión propia SQLite en memoria, migración de subida y descarte de la aplicación, sin rollback de infraestructura histórica. La ejecución definitiva posterior indicada arriba es la evidencia válida.

No se suman los resultados definitivos a los antecedentes de 316 / 2690 o 258 / 2032. No se repitieron suite global, MariaDB, JavaScript ni build; tampoco se certificó globalmente UC–07.

## Aislamiento y calidad

El bootstrap efectivo de la ejecución definitiva comprobó `testing`, SQLite `:memory:`, PDO SQLite sin PDO MySQL, configuración sin caché, storage/discos/vistas/cachés aislados y logging con `Monolog\Handler\NullHandler`. No se utilizó la base de desarrollo. Las migraciones se verificaron exclusivamente en SQLite aislado; el DDL MariaDB compilado offline no es ejecución real.

Sintaxis PHP y Pint `--test` acotados: PASS en los archivos afectados de cada ejecución. El último cambio del sobre verificó siete PHP; los restantes conservaron la evidencia previa y sus huellas. Diff y whitespace de las diecinueve rutas: PASS al cerrar el código. En este checkpoint se repiten únicamente comprobaciones documentales, huellas, diff/whitespace y staging.

## Archivos y huellas del código certificado

Estado respecto del HEAD previo: **M** modificado, **A** nuevo. Son hashes de archivos, no credenciales. El checkpoint agrega además `AGENTS.md` (M), este certificado (A) y la decisión transversal (A): **22 archivos, nueve modificados y trece nuevos**.

| Estado | Archivo | SHA-256 |
| --- | --- | --- |
| A | `app/Exceptions/Turnos/AccesoEnlaceRechazadoException.php` | `45b566dbec00f3e34358c170de851f83e32affb7ccaebcd79f34a2375e987af8` |
| A | `app/Services/Turnos/AutorizacionNavegadorEnlaceService.php` | `cc31e477f60e1a59d4f3b4ee9de4ab37119462c27c04f72ceb61abc4034263fd` |
| A | `app/Services/Turnos/ResolverAsistenciaEnlaceService.php` | `9c362179043662b86145d9093a2fd78f7200e5863ac2348a75483f9a1ff6fd20` |
| M | `app/Services/Turnos/ValidarEnlaceAsistenciaTurnoService.php` | `ee3d1021d980f8a4e080c9f0d2e20420523403c7cc1b6762501a42470fd57b06` |
| M | `app/Http/Middleware/ProtegerAccesoEnlaceAsistencia.php` | `2a6140e4fb6d7f47814e36295d71ed3a86e60a5bd6e8bd8dc8645a89dc37f8ba` |
| M | `app/Http/Controllers/EnlaceAsistenciaTurnoController.php` | `9e954e8df594fba475bb64f441d0d3093dc410609ed722bccc5ebc28d557cadd` |
| M | `routes/web.php` | `e407773a11d4f807acf223216886e48be74cb32c67a8b5ba505fc459587756ed` |
| A | `tests/Feature/Services/Turnos/ResolverAsistenciaEnlaceServiceTest.php` | `c990b271519a7c356703b310f04893738d573a8b747541710ab2716159ecd177` |
| A | `tests/Feature/Turnos/ResolverAsistenciaEnlaceHttpTest.php` | `4589ea14655792634536b9cd5bd6d2d77e7a4fa3a6f10e108f8f1ffd3bc01e2b` |
| M | `tests/Feature/Turnos/EnlaceAsistenciaTurnoHttpTest.php` | `8118a982cdd246676635d82990461458409d9bd6febc77b9d57f8242d5cc04dd` |
| M | `resources/views/turnos/asistencia/show.blade.php` | `6d5e2b0d8645f52f73e53223a94b43346345e037cfc44f4fc92dcaf99ddb8d2c` |
| A | `resources/views/turnos/asistencia/resultado.blade.php` | `73c80bdbe36b8ad7c0f7dff43f39ff570339bbb40cfbd450d30aff8497bb9ec0` |
| A | `app/Models/SobreEntregaEnlace.php` | `31655047f29133d22af7e51e8e8026a1fbf358d61532c0a7264b74287a269dc0` |
| A | `app/Exceptions/Turnos/EntregaEnlaceNoDisponibleException.php` | `175e50cf0b6ddb904c72747d56c0c7ae93069da533ae9dee668e0068e4172709` |
| A | `app/Services/Turnos/SobreEntregaEnlaceService.php` | `dd70528eb1f6c38dabe5a49914b70a926960d7ea10499f0bbce3e68a64b1c04d` |
| M | `app/Services/Turnos/EmitirEnlaceAsistenciaTurnoService.php` | `bed02674e09fccd7027b24f26d489f43302a3e758b2577316020e1e54a98a896` |
| M | `app/Services/Turnos/EnlaceAsistenciaEmitido.php` | `c1ac5f80b73c8519841f5c0383ddfa5f984b1aaae6cf81fecdd5775ac31afdf4` |
| A | `database/migrations/2026_11_06_000001_create_sobres_entrega_enlace_table.php` | `c65fbfed301c870fef4c2f9c09939f6d747d6495bd8fabd534c78151a7db64e6` |
| A | `tests/Feature/Services/Turnos/SobreEntregaEnlaceServiceTest.php` | `fe419e181433d63f02d88bd1e40ffc5737a6207012884edb65f8edf8bb4acbf6` |

## Reservas y cierre

- **MariaDB real, triggers y concurrencia:** pendientes de instalación y ejecución aisladas autorizadas, integridad en el motor objetivo, locks, esperas, carreras y deadlocks. SQLite y compilación offline no certifican esas garantías.
- **Dependencias externas:** CA-28-23 y CA-28-32 conservan los límites consignados. No se inventan planes/sesiones ni consumidor de cupos.
- **Logs operativos:** falta verificar servidor HTTP, proxy y registros anteriores al middleware; la protección probada sólo abarca los componentes controlados.
- **Claves y ciclo de vida:** pendientes custodia, rotación operativa, retención de claves anteriores y activación efectiva de depuración/cierre al terminar la necesidad de entrega.
- **UX y entorno real:** revisión visual manual, navegador/dispositivos reales, producción, transporte y carga real no certificados.
- **UC–27:** correo, Jobs con sólo referencias, colas, reintentos, destinatarios, política de resultados inciertos y Mailpit pendientes de los subpasos 6.30–6.36. El sobre resuelve el bloqueo de material durable de diseño, sin certificar ese proceso futuro.
- No se ofrece entrega exactamente una vez. El transporte externo queda fuera de la atomicidad de la base y exige revalidación previa según GA-05.
- Las Actions históricas y los contratos UC–06/UC–07 quedan preservados en el alcance revisado; no se amplía la evidencia a certificación integral o concurrente actual de esos procesos.

**Cierre local de UC–28 con reservas:** la recuperación de respuesta incierta y la infraestructura cifrada eliminan los bloqueos locales identificados de 6.29. Último subpaso completado: **6.29**. Próximo para preparar: **6.30 — UC–27**, con integración obligatoria del sobre cifrado y alcance específico previo. Este checkpoint no autoriza ni ejecuta 6.30.
