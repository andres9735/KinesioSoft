# UC–07 Solicitar turno — Planificación ejecutable

## Estado

**APROBADA Y CONGELADA**

**EJECUCIÓN CERRADA — 7.29–7.52 COMPLETADOS — V2-P1–V2-P6 PASS**.

Cierre documental: 24/10/2026, actualizado tras MC-03 el 25/10/2026. Baseline técnico definitivo: `ba97c4b8e0a1df53c1c456da424b549db9798828` (`fix: unificar foco visual de pasos del wizard V2`). Regresión final 7.51: **2231 tests / 14391 assertions PASS**, Node **150 PASS**, build **PASS**. El [cierre definitivo V2](turnos-solicitar-turno-cierre.md) reúne evidencias y límites. No quedan subpasos V2 por ejecutar ni se autoriza un nuevo incremento.

## Incremento

- Caso de uso: UC–07 Solicitar turno.
- Rama: `feature/turnos-solicitar-turno`.
- Base documental previa al Paso 7: `808794f7fc0e69efba70c9c9fc3dc702b7c50a1a`.
- Trazabilidad documental 7.00: completada, incluida la corrección de la referencia del límite de 31 días a DT-03.
- Contenido: Parte I histórica V1 con los subpasos 7.01–7.28 ya ejecutados y Parte II delta V2 con los subpasos 7.29–7.52 aprobados, congelados y completados.
- Gap analysis V2 contra la implementación real: completo y aprobado.
- La implementación V1 se conserva como baseline y no debe reejecutarse automáticamente.

## Propósito

Este documento define el orden ejecutable de implementación de UC–07.

No sustituye:

- Diccionario de Datos;
- fuente funcional UC–07;
- D-01…D-16;
- DT-01…DT-07;
- UX-01…UX-03.

Ante discrepancias se aplica la jerarquía declarada en `docs/_codex_sources/turnos/README_Codex_sources.md`. La planificación gobierna el orden operativo y no puede contradecir una decisión superior.

## Fuentes y alcance

Fuentes locales vigentes contrastadas para congelar el delta V2:

- `AGENTS.md`.
- `docs/_codex_sources/turnos/README_Codex_sources.md`.
- `docs/_codex_sources/turnos/UC-07_Solicitar_turno.txt`.
- `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_TECNICO_V2.0.md`.
- `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_UX_V2.0.md`.
- `docs/_codex_sources/turnos/prototipo_stitch_uc07/README.md`.
- `docs/_codex_sources/turnos/Diccionario_de_Datos_vigente.txt`.
- `docs/_codex_sources/turnos/UC-XX_Configurar_agenda_atencion.txt`, exclusivamente como antecedente implementado consumido por UC–07.

Se encuentran contrastados D-01…D-16 V2, DT-01…DT-07 V2, UX-01…UX-03 V2, ambas consolidaciones V2.0, el contrato Stitch V2 y el gap analysis V2 contra la implementación real.

La Parte I de este documento preserva la planificación V1 7.01–7.28 como trazabilidad histórica de la implementación ya ejecutada. Cuando una regla, contrato HTTP, secuencia UX o criterio de esa Parte I contradiga las fuentes V2 o la Parte II, **prevalecen las fuentes V2 y la Parte II delta 7.29–7.52**.

El alcance V2 continúa siendo exclusivamente D-16: entrada desde Mis turnos; disponibilidad agregada; selección de fecha e inicio; selección de profesional disponible; motivo obligatorio; adjuntos opcionales con descripción individual; resumen; confirmación autoritativa; Turno `PRIMERA_CONSULTA/PENDIENTE`; documentos privados; recuperación contextual; feedback y visibilidad mínima posterior.

Quedan fuera los otros flujos de UC–06, cancelación, reprogramación, asistencia, seguimiento solicitado por paciente, paciente provisorio, sobreturnos, Consulta/Padecimiento/diagnóstico/evaluación/plan, gestión clínica integral de documentos, equipos y notificaciones email/SMS/push.

No se crean tablas de slots, holds, drafts persistentes, entidad `SolicitudTurno` ni `documentos_clinicos_padecimiento`. No se agrega React ni infraestructura paralela de auditoría o confirmaciones.

La aprobación de este documento no autoriza ejecutar implementación, migraciones reales ni comandos de base de datos. Cada ejecución futura necesita el prompt independiente del subpaso autorizado.

# PARTE I — PLANIFICACIÓN HISTÓRICA V1 — 7.01–7.28

> **Estado de esta Parte I:** ejecutada y conservada exclusivamente como trazabilidad histórica.
>
> Los subpasos 7.01–7.28 NO son trabajo pendiente y NO deben reejecutarse automáticamente.
> Sus contratos V1 explican la implementación existente, pero no gobiernan el comportamiento objetivo después de la revisión de la cátedra.
> Toda contradicción con UC–07 V2, D/DT V2, UX V2, Stitch V2 o la Parte II se resuelve a favor de V2.

## Arquitectura final resumida

Se mantiene:

`Controller → Form Request → Action → DB::transaction() → Models + AuditoriaService`

Los Services previstos concentran elegibilidad profesional, disponibilidad y gestión documental. Toda confirmación pasa por una única Action, conceptualmente `ConfirmarPrimeraConsultaAction`.

El servidor deriva paciente, tipo, estado, `fin_at`, usuario cargador, metadatos documentales iniciales y ruta física. El cliente de confirmación sólo aporta `motivo_solicitud`, `kinesiologa_id`, `inicio_at`, `clave_idempotencia` y `adjuntos[]`.

La disponibilidad se deriva de Agenda, configuraciones por fecha, franjas, bloqueos, turnos ocupantes y `RelojAplicacion`. Visualización y confirmación comparten el mismo motor. No se confía en un `fin_at` enviado por el cliente.

El frontend es un wizard local de una página en Blade + Tailwind + Alpine, con archivos reales mediante `FormData`/`fetch` y feedback global SweetAlert2. `.js-confirm-action` no se utiliza directamente cuando su submit tradicional resulte incompatible con ese transporte.

### Estados que reservan agenda

Debe existir una única definición canónica de dominio reutilizable, equivalente a `EstadoTurnoEnum::estadosQueReservanAgenda()`, que devuelva conceptualmente:

- `PENDIENTE`;
- `CONFIRMADO`.

El nombre puede ajustarse al convenio real, pero Agenda, disponibilidad, D-01 y revalidación de confirmación deben consumir esa única fuente cuando corresponda. No se mantienen arrays duplicados de estados ocupantes en Services o Actions. No se altera la enumeración existente de estados.

7.01 incluye pruebas de que Pendiente reserva, Confirmado reserva, Cancelado no reserva y los estados terminales no se incorporan accidentalmente.

### Auditoría y autorización documental

Se reutiliza `AuditoriaService`, mediante metadata mínima y whitelist explícita. Eventos propuestos conforme al patrón existente:

- creación: `turno_primera_consulta_creado`;
- carga: `documento_clinico_cargado`;
- descarga: `documento_clinico_descargado`.

Los nombres exactos pueden ajustarse a un convenio real existente, sin alterar el contrato. Creación y carga se auditan dentro del agregado transaccional. No se duplican auditorías en replay. Nunca se incluyen binarios, motivo clínico completo ni contenido completo de documentos.

La Policy documental es autoritativa: paciente propietario o kinesióloga asignada al turno de origen. ADMINISTRADOR/SUPERADMIN no reciben lectura clínica sólo por su rol. La descarga exige `auth`, `verified`, perfil completo y autorización de recurso; se audita después de comprobar autorización y acceso efectivo al archivo. No se expone la ruta física ni se crea `TurnoPolicy` para este incremento.

## Contratos HTTP

| Resultado | HTTP | Identificador estable |
|---|---:|---|
| Disponibilidad válida, incluso vacía | 200 | No requiere conflicto |
| Validación de entrada/archivos | 422 | Errores asociados a campos |
| D-01 incumplida | 409 | `primera_consulta_activa` |
| Profesional dejó de ser elegible | 409 | `profesional_no_elegible` |
| Slot inválido por tiempo, ocupación, bloqueo o Agenda | 409 | `slot_no_disponible` |
| Creación nueva | 201 | Resultado exitoso |
| Replay consolidado | 200 | Mismo resultado funcional |

El cuerpo de un conflicto tendrá conceptualmente `code` y `message`. Los códigos deben ser estables, documentados, distintos y testeados. El frontend ramifica por `code`, nunca comparando mensajes humanos. Los nombres definitivos pueden ajustarse a las convenciones reales manteniendo esas garantías.

Errores SQL inesperados, corrupción de Agenda o fallas internas no se convierten genéricamente en 409 ni en indisponibilidad. Se distingue validación, vacío válido, conflicto de negocio y fallo técnico/red.

Rutas congeladas:

| Método y ruta | Nombre |
|---|---|
| `GET /turnos/mis-turnos` | `turnos.mis-turnos.index` |
| `GET /turnos/solicitar` | `turnos.solicitar.create` |
| `GET /turnos/solicitar/disponibilidad` | `turnos.solicitar.disponibilidad` |
| `POST /turnos/solicitar` | `turnos.solicitar.store` |
| `GET /documentos-clinicos/{documento}/descargar` | `documentos-clinicos.download` |

El flujo del paciente usa `auth`, `verified`, `perfil.completo` y `perfil.paciente`. La descarga conserva el contrato específico de recurso indicado anteriormente.

## Estrategia de locking y current reads

MariaDB real utiliza `REPEATABLE-READ`. Se conserva ese nivel y se recomiendan lecturas bloqueantes explícitas dentro de la transacción:

| Información | Lectura prevista |
|---|---|
| Mutex de agenda | `Kinesiologa::…->lockForUpdate()->first()` |
| Protección D-01 | `Paciente::…->lockForUpdate()->first()` |
| UUID consolidada | Consulta por UUID con `lockForUpdate()->first()` |
| Primera consulta futura activa | Consulta de filas candidatas con `lockForUpdate()` |
| Turnos ocupantes | Consulta por intervalo con `lockForUpdate()->get()` |
| Configuraciones, franjas y bloqueos relevantes | Consultas explícitas con `lockForUpdate()` |
| Estado y relaciones de elegibilidad | Lecturas bloqueantes de los registros pertinentes, sin reutilizar relaciones antiguas |

El orden canónico de locks de negocio es **kinesióloga → paciente**. Los rechequeos críticos se realizan después de adquirir esos locks, con colecciones adicionales en orden determinista por identificador.

No deben existir lecturas tempranas dentro de la transacción que congelen un snapshot inapropiado antes de adquirir los locks relevantes. El replay rápido inicial es una lectura previa de una operación ya consolidada; si no se resuelve por esa vía, se repite autoritativamente dentro de la transacción.

Un lock sobre el padre no se propaga automáticamente a eager loads ni relaciones. La completitud profesional existente utiliza `exists()` y Agenda carga franjas relacionadas: 7.05/7.11 deben evitar que esas consultas vuelvan a un snapshot antiguo. Se reutiliza el predicado canónico con relaciones leídas autoritativamente; no se crea una regla paralela de elegibilidad.

El SQL generado y el comportamiento después de esperar locks se verifican en 7.11/7.13 con conexiones/procesos independientes. `refresh()` por sí solo no certifica una lectura actual bajo este aislamiento. Toda futura operación que altere ocupación debe respetar el convenio de mutex por kinesióloga.

## Estrategia de replay e idempotencia

### UUID generada por servidor

La UUID de `clave_idempotencia` se genera en el servidor al iniciar un flujo mediante el GET `create` y se entrega a Blade/Alpine. El navegador no genera la UUID autoritativa.

La misma UUID se conserva durante:

- navegación entre pasos y vuelta hacia atrás;
- cambio de profesional y cambio de slot;
- corrección de motivo y de archivos;
- errores 422 y conflictos 409 recuperables;
- fallo de red, respuesta incierta y retry.

No se genera una UUID nueva para un retry. Una nueva entrada efectiva a un flujo nuevo puede recibir otra UUID. Después de consolidar exitosamente, esa intención queda finalizada.

7.19/7.21 incluyen pruebas de UUID presente en el render inicial, estabilidad durante recuperación y replay usando exactamente la misma UUID.

### Validación por etapas

1. Middleware y Request validan identidad/autorización de entrada y UUID.
2. La Action consulta replay consolidado del mismo paciente y contexto UC–07.
3. Un replay válido devuelve el original sin exigir otra vez motivo, archivos, elegibilidad, futuro del slot o D-01.
4. Para una intención no consolidada se valida el payload necesario para creación.
5. Dentro de la transacción, después de los locks, se vuelve a comprobar UUID antes de las condiciones mutables y de escribir.

El Request de confirmación no tiene reglas anticipadas de profesional actualmente elegible, fecha posterior a ahora o paciente sin Primera consulta que bloqueen un replay legítimo. El contenido pendiente se transporta mediante una whitelist explícita de los cinco campos aceptados y no se considera validado hasta la validación de nueva creación de la Action.

La UUID identifica una intención, no un slot. Un replay válido puede devolver el turno aunque la kinesióloga ya no sea elegible, el slot haya quedado en el pasado o D-01 sea verdadera por el propio turno. No vuelve a exigir ni almacenar los archivos originales y no duplica auditorías de creación/carga.

Se conserva el UNIQUE nullable y el rechequeo transaccional. No hay retries automáticos del callback que realiza efectos de filesystem. Timeout/deadlock debe producir rollback íntegro y permitir retry explícito con la misma UUID.

## Estrategia documental y advisory locks

La política es 0..5 archivos, 10 MiB individuales y 25 MiB totales, PDF/JPEG/JPG/PNG, MIME real, `UploadedFile` válido y tamaños autoritativos en bytes. Storage privado, sin URL pública ni staging persistente. El nombre original es metadata; la ruta es generada por backend.

Los archivos son efectos externos a la transacción DB: se registran inmediatamente para compensación. Ante rollback confirmado se eliminan los nuevos; la limpieza no afecta archivos previos ni oculta el error original. Si no puede determinarse el resultado del commit, se preservan archivos hasta comprobar UUID/referencias o reconciliar: no se borran a ciegas.

El **advisory lock MariaDB por ruta generada es el mecanismo planificado y preferido**, específico de UC–07:

- La escritura adquiere el lock antes de hacer visible el archivo.
- La adquisición y liberación ocurren mediante la misma sesión MariaDB cuando el mecanismo lo requiere; la escritura usa la sesión de la transacción de creación.
- Una falla al adquirir el lock impide continuar la escritura dependiente.
- El lock se mantiene hasta resolver commit/rollback y compensación, y los locks adquiridos se liberan explícitamente.
- El reconciliador intenta adquirir el mismo lock antes de comprobar referencias y eliminar.
- Los nombres incluyen namespace de aplicación/base y un identificador derivado de la ruta, sin datos clínicos.

`GET_LOCK()` es un lock de sesión y no se libera por commit. Esta propiedad se utiliza para coordinar filesystem y DB sin agregar tablas. No se presume certificada por las pruebas SQLite.

**Certificación obligatoria en 7.13:** MariaDB 10.4 y lifecycle real de conexión Laravel, misma sesión cuando corresponde y ausencia de locks filtrados tras commit, rollback, excepción y timeout.

**Si la certificación demuestra que el mecanismo no es seguro o viable, DETENERSE y reportar.** No sustituirlo silenciosamente por tabla de staging, locks persistentes nuevos, nueva infraestructura ni cambio arquitectónico no aprobado. Cualquier alternativa requiere revisión explícita.

### Reconciliación segura

El reconciliador opera exclusivamente dentro del directorio clínico UC–07, ofrece dry-run, aplica antigüedad conservadora configurable, nunca borra archivos recientes, no sigue symlinks/rutas ajenas, comprueba inmediatamente referencia DB antes de borrar, aborta ante DB no disponible y registra el resultado. Un fallo de eliminación no afecta archivos válidos.

La carrera archivo escrito + transacción sin commit + reconciliador concurrente se resuelve mediante el mismo lock por ruta. Si el escritor mantiene el lock, el reconciliador omite el archivo aunque supere el grace period. Tras commit se conserva por referencia; tras rollback/interrupción podrá eliminarse cuando corresponda. La antigüedad sola no protege una confirmación activa y no se exige demostrar globalmente ausencia de confirmaciones en vuelo.

## Baseline y verificaciones comunes

Baseline histórico certificado previo al incremento:

- `php artisan test`: **1376 tests passed, 8842 assertions**.
- `npm run build`: **PASS**.
- `git diff --check`: limpio al cierre anterior.
- Pint global: deuda histórica preexistente ajena a UC–07; no se considera baseline PASS ni se corrige masivamente.

En todos los subpasos futuros se exige preflight, alcance acotado, pruebas específicas y cierre detenido. Para los que modifiquen PHP, ejecutar `php vendor/bin/pint --test` con la lista explícita de paths PHP modificados por UC–07. Todos requieren `git diff --check` y estado Git al cerrar. No avanzar con fallos causados por el subpaso.

Los archivos y nombres nuevos de tests indicados abajo son propuestos. Los comandos son previsiones para la ejecución futura autorizada: su inclusión en este documento no significa que se hayan ejecutado ni que exista todavía esa implementación. Los placeholders de paths deben sustituirse por rutas reales explícitas del subpaso.

PHPUnit habitual usa SQLite `:memory:`. La suite MariaDB es separada, usa base exclusiva InnoDB y no utiliza la base de desarrollo como destino de limpieza. Una suite omitida o saltada no cuenta como certificación.

## Plan completo

### 7.01 — TipoTurnoEnum y estados que reservan agenda

- **Objetivo:** incorporar el tipo canónico de turno y la única definición reutilizable de estados que reservan agenda.
- **Dependencias:** 7.00 completo.
- **Archivos/familias probables:** enum nuevo, `EstadoTurnoEnum`, `Turno`, `TurnoFactory`, consultas de Agenda consumidoras y tests afectados.
- **Cambios:** `PRIMERA_CONSULTA`/`SEGUIMIENTO`, cast de `tipo`, fixtures mediante enum. Mantener `string(30)` y la enumeración existente de estados. Incorporar responsabilidad equivalente a `EstadoTurnoEnum::estadosQueReservanAgenda()` con `PENDIENTE` y `CONFIRMADO`, reutilizada por Agenda, disponibilidad, D-01 y confirmación, sin arrays duplicados.
- **Pruebas:** persistencia/recuperación de ambos tipos, independencia tipo/estado y consumidores de Agenda. Pendiente y Confirmado reservan; Cancelado no reserva; los estados terminales no se incorporan accidentalmente.
- **Comandos:** `php artisan test --filter='TipoTurnoEnumTest|EstadoTurnoEnumTest|TurnoModelTest|AgendaQueryServiceTest'`.
- **Criterio de cierre:** tipo y estados ocupantes canónicos integrados sin alterar ocupación ni ampliar clasificaciones o la enumeración existente.

### 7.02 — Idempotencia e índice D-01

- **Objetivo:** agregar las extensiones físicas aprobadas de `turnos`.
- **Dependencias:** 7.01.
- **Archivos/familias probables:** nueva migración de alteración y tests de esquema.
- **Cambios:** `clave_idempotencia` nullable UNIQUE e índice `(paciente_id, tipo, estado, inicio_at)`. Sin UNIQUE sobre `inicio_at`.
- **Pruebas:** UUID duplicada rechazada; múltiples NULL admitidos; índices/FKs anteriores conservados; ausencia de referencias a slots.
- **Comandos:** `php artisan test --filter=TurnoSchemaTest`.
- **Criterio de cierre:** esquema SQLite correcto; expectativa histórica de índices actualizada específicamente, sin debilitar otras verificaciones.

### 7.03 — Esquema documental

- **Objetivo:** crear exclusivamente las dos tablas aprobadas.
- **Dependencias:** 7.02.
- **Archivos/familias probables:** nuevas migraciones y tests de esquema documental.
- **Cambios:** atributos DT-04; `ruta_storage` única; satélite con PK/FK `documento_clinico_id` y FK `turno_id`; conservación histórica, timestamps y nombres de índices compatibles.
- **Pruebas:** estructura, nulabilidad, unicidad, integridad referencial y cardinalidad del satélite.
- **Comandos:** `php artisan test --filter='DocumentosClinicosSchemaTest|TurnoSchemaTest|AgendaSchemaTest'`.
- **Criterio de cierre:** no aparecen tablas de slots, holds, solicitudes intermedias ni asociación documental a padecimientos.

### 7.04 — Models, relaciones e inmutabilidad

- **Objetivo:** representar y proteger el snapshot original.
- **Dependencias:** 7.03.
- **Archivos/familias probables:** Models documentales, `Turno`, relaciones en paciente/profesional cuando correspondan y factories/tests.
- **Cambios:** relaciones explícitas; valores iniciales documentales; impedir modificación del motivo, metadatos originales, ruta, contenido y asociación documento-turno.
- **Pruebas:** relaciones; rechazo de actualización/desvinculación del original; preservación del snapshot al modificar otros atributos permitidos.
- **Comandos:** `php artisan test --filter='TurnoModelTest|DocumentoClinicoModelTest|DocumentoClinicoOrigenTurnoModelTest'`.
- **Criterio de cierre:** protección superior a `$fillable`; ningún camino nuevo utiliza updates masivos o silenciosos para eludirla. Estado y clasificación documental quedan separados del original protegido.

### 7.05 — Elegibilidad profesional

- **Objetivo:** centralizar DT-02 reutilizando completitud existente.
- **Dependencias:** 7.01.
- **Archivos/familias probables:** `User`, Service de elegibilidad y tests de perfiles/elegibilidad.
- **Cambios:** predicado superior de rol, actividad, verificación y perfil profesional completo. Permitir evaluar relaciones precargadas autoritativamente sin repetir consultas `exists()` contra un snapshot antiguo.
- **Pruebas:** cada condición faltante; profesional sin agenda; catálogos históricos; equivalencia entre evaluación normal y relaciones precargadas; datos personales requeridos por completitud existente.
- **Comandos:** `php artisan test --filter='ElegibilidadKinesiologaTest|PerfilKinesiologaControllerTest|PerfilAuthorizationTest'`.
- **Criterio de cierre:** listado y validación individual usan el mismo predicado; sin banderas ni requisitos nuevos.

### 7.06 — Regla D-01

- **Objetivo:** centralizar la definición de Primera consulta futura activa.
- **Dependencias:** 7.02.
- **Archivos/familias probables:** consulta/scope específico de Turnos y tests.
- **Cambios:** paciente + tipo + estados ocupantes canónicos de 7.01 + referencia temporal de `RelojAplicacion`; posibilidad de ejecutar la misma consulta mediante lectura bloqueante.
- **Pruebas:** `PENDIENTE`/`CONFIRMADO` futuros; terminales; históricos; seguimiento; independencia de padecimiento y profesional. Sin redefinir arrays de estados.
- **Comandos:** `php artisan test --filter=ElegibilidadPrimeraConsultaTest`.
- **Criterio de cierre:** una sola definición para entrada y confirmación. La consulta preliminar no se presenta como garantía concurrente.

### 7.07 — Política documental

- **Objetivo:** aplicar una única política backend.
- **Dependencias:** 7.04.
- **Archivos/familias probables:** Service documental, configuración de límites y tests.
- **Cambios:** 0..5 archivos, 10 MiB individuales, 25 MiB totales, PDF/JPEG/JPG/PNG, MIME real y upload válido. Tamaños autoritativos en bytes.
- **Pruebas:** cero/cinco/seis archivos; límites exactos y un byte superior; suma total; MIME falso; upload fallido; errores identificados por archivo cuando sea posible.
- **Comandos:** `php artisan test --filter=PoliticaDocumentosClinicosTest`.
- **Criterio de cierre:** Action y presentación de errores consumen la misma política; ningún archivo inválido se acepta por extensión solamente.

### 7.08 — Storage privado y compensación

- **Objetivo:** encapsular archivos privados y su limpieza.
- **Dependencias:** 7.07.
- **Archivos/familias probables:** Service documental, configuración de filesystem, coordinación documental mínima y tests.
- **Cambios:** disco privado explícito sin servido automático; rutas backend; detección de fallas; registro inmediato de archivos nuevos; capacidad de adquirir/liberar el lock por ruta antes/después del ciclo de escritura, con la misma sesión cuando corresponda y liberación explícita. Mecanismo planificado/preferido pendiente de certificación 7.13.
- **Pruebas:** falla en segundo archivo, escritura parcial, limpieza fallida, rutas externas, nombre original no utilizado como ruta y liberación de recursos.
- **Comandos:** `php artisan test --filter=AlmacenamientoDocumentosClinicosTest`.
- **Criterio de cierre:** sin URL pública ni staging. SQLite utiliza un sustituto de coordinación para pruebas; la operación real MariaDB nunca continúa una escritura si no obtuvo el lock requerido.

### 7.09 — Grilla determinista

- **Objetivo:** generar candidatos según configuración y franjas.
- **Dependencias:** 7.05.
- **Archivos/familias probables:** Service de disponibilidad y extensión mínima de consultas de Agenda si es necesaria.
- **Cambios:** configuración por fecha, franja independiente, inicio exacto, incrementos por duración, final coincidente admitido y remanente descartado. Los consumidores de ocupación mantienen la definición canónica de 7.01.
- **Pruebas:** múltiples franjas, contiguas, diferentes duraciones, cambios de vigencia, ausencia de configuración y configuraciones anuladas.
- **Comandos:** `php artisan test --filter='GrillaPrimeraConsultaTest|AgendaQueryServiceTest'`.
- **Criterio de cierre:** candidatos reproducibles sin cruzar franjas, extrapolar vigencias ni materializar slots.

### 7.10 — Disponibilidad

- **Objetivo:** filtrar candidatos y ofrecer el mismo motor para confirmación.
- **Dependencias:** 7.09.
- **Archivos/familias probables:** Service de disponibilidad, consultas de Agenda y configuración del límite.
- **Cambios:** overlap semiabierto; ocupantes mediante la definición canónica de 7.01; cancelados liberan; bloqueos anulados no restringen; `inicio > ahora`; hasta 31 días configurables por request.
- **Pruebas:** contigüidad, intersecciones parciales, bloqueos múltiples, mismo día, instante exacto, 31 días/exceso, fechas lejanas, slot fuera de grilla y `fin_at` derivado. Comprobar consultas por ventana, no por slot.
- **Comandos:** `php artisan test --filter='DisponibilidadPrimeraConsultaTest|GrillaPrimeraConsultaTest|AgendaQueryServiceTest'`.
- **Criterio de cierre:** un único algoritmo para visualización/revalidación; soporte de lecturas bloqueantes sobre los datos que lo alimentan. El límite técnico no limita anticipación de reserva.

### 7.11 — Núcleo transaccional

- **Objetivo:** consolidar correctamente el caso válido sin adjuntos.
- **Dependencias:** 7.04, 7.05, 7.06 y 7.10.
- **Archivos/familias probables:** Action única, resultado de creación/replay, excepciones/códigos de conflicto y tests.
- **Cambios:** replay inicial; transacción; locks kinesióloga → paciente; UUID rechequeada; lecturas actuales de D-01, ocupación, Agenda y elegibilidad; consumo de estados ocupantes canónicos; tipo/estado internos; evento `turno_primera_consulta_creado`.
- **Pruebas:** alta; replay con condiciones actuales incumplidas; UUID ajena/contexto inválido; slot vencido/fuera de grilla; D-01; rollback por auditoría; inspección del SQL de lecturas críticas y relaciones.
- **Comandos:** `php artisan test --filter=ConfirmarPrimeraConsultaActionTest`.
- **Criterio de cierre:** núcleo completo para cero archivos, sin exposición HTTP y rechazando adjuntos hasta 7.12. Sin retry automático. No basta con `refresh()` o lock sobre el padre si consultas relacionadas siguen siendo lecturas consistentes antiguas.

### 7.12 — Atomicidad con documentos

- **Objetivo:** consolidar el agregado completo.
- **Dependencias:** 7.08 y 7.11.
- **Archivos/familias probables:** misma Action, Service documental y tests de fallas.
- **Cambios:** adquirir locks documentales antes de escribir; crear documentos/asociaciones; evento `documento_clinico_cargado`; mantener locks hasta resolver transacción y compensación; adquirir/liberar mediante la misma sesión cuando corresponde y liberar explícitamente. No escribir si falla la adquisición.
- **Pruebas:** 0/1/5 archivos; falla de storage, fila documental, asociación y auditoría; rollback completo; replay sin almacenar ni auditar otra vez; fallo posterior al commit y resultado incierto.
- **Comandos:** `php artisan test --filter='ConfirmarPrimeraConsultaActionTest|AtomicidadPrimeraConsultaTest'`.
- **Criterio de cierre:** éxito sólo tras commit completo. Rollback confirmado limpia archivos nuevos. Si no puede determinarse el resultado del commit, se preservan archivos hasta comprobar UUID/referencias o reconciliar; nunca se borran a ciegas. La seguridad real del advisory lock queda sujeta a 7.13.

### 7.13 — Concurrencia real MariaDB

- **Objetivo:** certificar locks, lecturas actuales e idempotencia.
- **Dependencias:** 7.12.
- **Archivos/familias probables:** suite separada `tests/Integration/MySql/`, configuración PHPUnit propia y workers.
- **Cambios:** base exclusiva InnoDB; guardas antes de preparar datos; conexiones/procesos independientes; fixtures confirmados; barreras deterministas y respuestas registradas.
- **Pruebas:** dos pacientes/mismo slot; mismo paciente/profesionales distintas; misma UUID; UUID distintas incompatibles; Agenda/bloqueo concurrente; espera de lock seguida de commit; elegibilidad modificada; timeout/deadlock con retry explícito; documentos y auditorías sin duplicar. Certificar advisory lock contra MariaDB 10.4 y el lifecycle real Laravel: misma sesión cuando corresponde y ningún lock filtrado tras commit, rollback, excepción o timeout.
- **Comandos:** `php vendor/bin/phpunit -c phpunit.mysql.xml`.
- **Criterio de cierre:** verificar respuesta de cada proceso y cantidades finales de turnos, documentos, asociaciones y auditorías; current reads bajo `REPEATABLE-READ` y lifecycle de locks documentales. La preparación del entorno real requiere autorización específica. Si el advisory lock no es seguro o viable, DETENERSE y reportar; ninguna sustitución por staging, locks persistentes nuevos, infraestructura o arquitectura alternativa sin revisión explícita.

### 7.14 — Reconciliación de huérfanos

- **Objetivo:** eliminar residuos sin afectar operaciones activas ni archivos válidos.
- **Dependencias:** 7.12 y 7.13.
- **Archivos/familias probables:** Service documental, comando con dry-run y tests SQLite/MariaDB correspondientes.
- **Cambios:** directorio clínico exclusivo; período de gracia conservador configurable; no seguir symlinks; adquirir lock por ruta con intento no bloqueante; revalidar antigüedad/ruta y referencia DB inmediatamente antes de borrar; registrar resultado.
- **Pruebas:** huérfano antiguo, reciente, referenciado, symlink, ruta externa, DB caída y eliminación fallida. Carrera determinista: archivo artificialmente antiguo, transacción sin commit y reconciliador paralelo.
- **Comandos:** `php artisan test --filter=ReconciliarDocumentosClinicosTest`; `php vendor/bin/phpunit -c phpunit.mysql.xml --filter=ReconciliacionDocumental`.
- **Criterio de cierre:** si el escritor mantiene el lock, el archivo se omite aunque supere el grace period. Tras commit se conserva por referencia; tras rollback/interrupción podrá eliminarse cuando corresponda. DB no disponible o error al adquirir coordinación aborta de forma conservadora. No se requiere demostrar globalmente que no hay confirmaciones activas.

### 7.15 — Form Requests

- **Objetivo:** separar entrada mínima, replay y creación nueva.
- **Dependencias:** 7.07, 7.10 y 7.12.
- **Archivos/familias probables:** los dos Requests previstos, validación de nueva creación en la Action y tests.
- **Cambios:** disponibilidad valida profesional/rango/formato. Confirmación valida inicialmente UUID y autorización, restringe los cinco campos aceptados y difiere requisitos exclusivos de creación hasta descartar replay. El resto del payload mantiene condición de pendiente de validar.
- **Pruebas:** motivo requerido/máximo; formatos; campos derivados manipulados; archivos reales; replay sólo con identidad/UUID sin originales; replay con profesional ineligible, slot pasado y D-01 verdadera.
- **Comandos:** `php artisan test --filter='ConsultarDisponibilidadPrimeraConsultaRequestTest|ConfirmarPrimeraConsultaRequestTest|ConfirmarPrimeraConsultaActionTest'`.
- **Criterio de cierre:** ningún `exists`, comparación temporal, D-01 o regla documental prematura bloquea un replay consolidado legítimo. La Action mantiene validación completa y autoritativa para creación nueva.

### 7.16 — Descarga privada

- **Objetivo:** servir documentos únicamente a quien corresponde.
- **Dependencias:** 7.04 y 7.08.
- **Archivos/familias probables:** Policy documental, controller de descarga, ruta y tests.
- **Cambios:** `auth`, `verified`, perfil completo y autorización de recurso. Paciente propietario o kinesióloga del turno de origen. Comprobar acceso al archivo antes de auditar `documento_clinico_descargado`.
- **Pruebas:** propietario/ajeno; profesional asignada/no asignada; administrativos sin acceso por rol; no verificado/perfil incompleto; archivo faltante; acceso efectivo y auditoría.
- **Comandos:** `php artisan test --filter='DocumentoClinicoPolicyTest|DescargarDocumentoClinicoTest'`.
- **Criterio de cierre:** Policy autoritativa, sin bypass administrativo, URL pública ni ruta física expuesta. Denegaciones no producen auditorías ficticias de descarga. No se crea `TurnoPolicy`.

### 7.17 — Mis turnos mínimo

- **Objetivo:** ofrecer entrada y destino posterior, con localización útil.
- **Dependencias:** 7.01, 7.04 y 7.06.
- **Archivos/familias probables:** controller, ruta, vista y navegación paciente.
- **Cambios:** paciente desde autenticado; eager loading; paginación; próximos por inicio ascendente e históricos por fecha descendente. Preparar localización de la página del turno recién consolidado usando su identificador y siempre consulta del paciente autenticado.
- **Pruebas:** aislamiento, paginación, orden, localización del nuevo turno, consultas sin N+1 y estado preliminar D-01.
- **Comandos:** `php artisan test --filter=MisTurnosTest`; `npm run build`.
- **Criterio de cierre:** muestra sólo tipo, profesional, fecha, horario y estado, sin acciones UC–06. El CTA se conecta en 7.21; su estado visual nunca reemplaza GET/POST y locks.

### 7.18 — HTTP de disponibilidad y confirmación

- **Objetivo:** publicar los contratos backend completos.
- **Dependencias:** 7.13, 7.15 y 7.17.
- **Archivos/familias probables:** controller de solicitud, rutas y tests HTTP.
- **Cambios:** middleware DT-07; `200/422/409/201/200`; códigos estables de la tabla HTTP; respuestas acotadas; destino Mis turnos. Prioridad de evaluación: replay, D-01, elegibilidad y slot.
- **Pruebas:** cada conflicto con su `code`, independientemente del mensaje; disponibilidad vacía `200`; nueva creación `201`; replay `200`; errores de entrada `422`; fallos internos conservan respuesta técnica de error.
- **Comandos:** `php artisan test --filter='SolicitarTurnoHttpTest|TurnosRoutesTest'`; `php artisan route:list --path=turnos`.
- **Criterio de cierre:** códigos documentados y testeados; sólo la Action crea. UUID ajena/contexto incompatible rechazada sin revelar el turno; colisión SQL no identificada no se disfraza de conflicto funcional.

### 7.19 — Wizard y captura local

- **Objetivo:** construir los cuatro pasos con estado local y UUID autoritativa del servidor.
- **Dependencias:** 7.05, 7.06 y 7.18.
- **Archivos/familias probables:** GET `create`, Blade, parciales justificados, módulo Alpine y registro en `app.js`.
- **Cambios:** nombres canónicos UX-01; UUID generada en servidor al iniciar GET `create` y entregada a Blade/Alpine, nunca generada autoritativamente en navegador; motivo/archivos editables; profesional; conservar datos y UUID al volver/corregir/cambiar selección; invalidar slot al cambiar profesional. Advertencia de abandono con datos significativos. GET revalida D-01.
- **Pruebas:** acceso, bloqueo GET, render de cuatro pasos, ausencia de profesionales y configuración inicial. UUID presente desde el render inicial y conservada en navegación/recuperación. Recorrido de captura sin persistencia.
- **Comandos:** `php artisan test --filter=SolicitarTurnoCreateTest`; `npm run build`.
- **Criterio de cierre:** sin drafts, pre-upload ni persistencia por paso. La captura de archivos conserva los válidos y no permite avanzar con inválidos. No hay confirmación falsa de pasos aún no conectados. Una nueva entrada efectiva puede recibir otra UUID; el éxito finaliza la intención.

### 7.20 — Disponibilidad frontend

- **Objetivo:** completar selección semanal y recuperación de consulta.
- **Dependencias:** 7.19.
- **Archivos/familias probables:** Alpine y vista de fecha/horario.
- **Cambios:** ventana visual de siete días, navegación sin máximo funcional, loading textual, empty válido, retry y cancelación/descarte de respuestas antiguas.
- **Pruebas:** cambiar rápidamente profesional/ventana; respuesta fuera de orden; semana vacía; fallo de red distinto de vacío; navegación lejana; selección invalidada al cambiar profesional.
- **Comandos:** `php artisan test --filter='DisponibilidadPrimeraConsultaTest|SolicitarTurnoCreateTest'`; `npm run build`; recorrido real de interacción.
- **Criterio de cierre:** error de disponibilidad mantiene Paso 3 y permite retry. Sólo se muestran slots libres, sin ocupaciones ni causas. UI siete días y backend hasta 31 días son límites distintos.

### 7.21 — Confirmación y recuperación contextual

- **Objetivo:** completar resumen, submit y resolución de resultados.
- **Dependencias:** 7.20.
- **Archivos/familias probables:** resumen, Alpine, feedback e incorporación del CTA.
- **Cambios:** `Confirmar turno`, conjunto exacto de archivos, `FormData`, CSRF, navegación/submit bloqueados durante POST y ramificación por `code`. Mantener la UUID de servidor en correcciones de motivo/archivos, cambios de profesional/slot, navegación, 422, 409 recuperables, red, respuesta incierta y retry; no generar otra para reintentar.
- **Pruebas:** todos los destinos de la tabla de recuperación UX; doble clic; respuesta perdida; estabilidad de UUID durante recuperación; retry y replay con exactamente la misma UUID; replay como éxito normal; un solo feedback tras commit.
- **Comandos:** `php artisan test --filter='SolicitarTurnoHttpTest|MisTurnosTest'`; `npm run build`; recorrido real de errores.
- **Criterio de cierre:** formulario operativo, éxito finaliza la intención y redirige a la página donde el turno resulte localizable; no envía notificaciones externas. Nueva intención sólo en una nueva entrada efectiva al flujo.

### 7.22 — Responsive y accesibilidad

- **Objetivo:** cerrar UX-03 en pantallas e interacción.
- **Dependencias:** 7.21.
- **Archivos/familias probables:** vistas/Alpine de UC–07 y tests semánticos de vistas.
- **Cambios:** stepper desktop completo; mobile `Paso X de 4` + título/progreso; cards completas seleccionables; slots semánticos; teclado/foco; `aria-invalid` y errores asociados; loading textual; selector nativo mobile; resumen label/value.
- **Pruebas:** estructura de Blade y recorrido real con teclado, lector/semántica pertinente, distintos anchos y zoom. Sin depender sólo de color ni scroll horizontal para operar.
- **Comandos:** `php artisan test --filter='TurnosAccessibilityViewTest|SolicitarTurnoCreateTest|MisTurnosTest'`; `npm run build`.
- **Criterio de cierre:** foco correcto al cambiar de paso/error y controles utilizables. Los tests Blade no se presentan como prueba de comportamiento Alpine.

### 7.23 — Integración completa

- **Objetivo:** verificar las garantías después de conectar HTTP y frontend.
- **Dependencias:** 7.14, 7.16 y 7.22.
- **Archivos/familias probables:** tests Feature integrados y extensión HTTP del harness MariaDB.
- **Cambios:** sólo correcciones de integración demostradas.
- **Pruebas:** recorrido completo; replay sin originales y con condiciones mutadas; tres códigos 409; `422`; aislamiento; auditoría mínima; resultado incierto. Repetir carreras por HTTP con procesos/sesiones independientes y sin transacción envolvente que oculte commits.
- **Comandos:** `php artisan test --filter='PrimeraConsultaIntegracionTest|SolicitarTurnoHttpTest|DescargarDocumentoClinicoTest'`; `php vendor/bin/phpunit -c phpunit.mysql.xml`.
- **Criterio de cierre:** comprobar respuestas y cantidades de turno/documentos/auditorías. La capa Request/HTTP no debilita las garantías de la Action ni duplica archivos.

### 7.24 — Esquema en entorno autorizado

- **Objetivo:** preparar el entorno web real sin operaciones destructivas.
- **Dependencias:** 7.23.
- **Archivos/familias probables:** migraciones ya creadas y evidencia de aplicación.
- **Cambios:** revisar SQL, FKs, timestamps, índices y conservación de datos; aplicar sólo migraciones nuevas en el destino autorizado.
- **Pruebas:** comparación estructural y conservación histórica; las pruebas automatizadas previas deben estar aprobadas.
- **Comandos:** `php artisan migrate --pretend --path=<migración-nueva>`; `php artisan migrate` sólo con autorización expresa para ese entorno.
- **Criterio de cierre:** esquema real correcto. No `fresh`, `reset` ni `wipe`. Si el destino es desarrollo, la confirmación exigida por `AGENTS.md` es una puerta obligatoria antes de aplicar.

### 7.25 — Multipart real Apache/PHP

- **Objetivo:** certificar DT-04 con el SAPI web.
- **Dependencias:** 7.24.
- **Archivos/familias probables:** evidencia de prueba; correcciones acotadas sólo si hay fallos demostrados.
- **Cambios:** comprobar configuración efectiva y manejo de uploads; no modificar `php.ini` preventivamente.
- **Pruebas:** 0/5 archivos; sexto; 10 MiB exactos/exceso; total 25 MiB exactos/exceso; MIME falso; reemplazo/retiro; descarga y limpieza. La petición de 25 MiB más overhead multipart debe llegar completa.
- **Comandos:** recorrido HTTP real bajo Apache; si hubo correcciones, tests específicos y `npm run build` cuando afecten frontend.
- **Criterio de cierre:** verificar bytes recibidos y persistidos. Valores relevados: `upload_max_filesize = 40M`, `post_max_size = 40M`, `max_file_uploads = 20`. Cinco archivos de 10 MiB son inválidos por total y podrían superar los 40M de transporte; su rechazo anticipado no contradice la política. Nunca aceptar como solicitud sin adjuntos un upload perdido por límites PHP/Apache.

### 7.26 — Aceptación manual

- **Objetivo:** validar el caso completo con datos sintéticos.
- **Dependencias:** 7.25.
- **Archivos/familias probables:** evidencia reproducible; correcciones de defectos acotados si aparecen.
- **Cambios:** únicamente ajustes necesarios para cumplir contratos congelados.
- **Pruebas:** paciente histórico/nuevo episodio; D-01 en entrada/durante flujo; sin profesionales; semanas vacías; búsqueda lejana; mismo día; slot perdido; todos los retornos contextuales; abandono; retry; éxito y localización inmediata; desktop/mobile.
- **Comandos:** recorrido manual; tests del área afectada si se corrige; build si corresponde.
- **Criterio de cierre:** aceptación trazable a D/DT/UX, sin incorporar operaciones de otros UC.

### 7.27 — Regresión final

- **Objetivo:** certificar el estado técnico final.
- **Dependencias:** 7.26.
- **Archivos/familias probables:** sólo correcciones causadas por UC–07.
- **Cambios:** resolver fallos propios sin reformateos ajenos ni debilitamiento de reglas.
- **Pruebas:** suite SQLite completa, MariaDB separada, frontend y formato del incremento.
- **Comandos:** `php artisan test`; `php vendor/bin/phpunit -c phpunit.mysql.xml`; `npm run build`; Pint sobre PHP modificado; `git diff --check`.
- **Criterio de cierre:** todas las puertas pasan con resultados exactos. Comparar contra 1376 tests/8842 assertions; una suite omitida o saltada no cuenta como certificación.

### 7.28 — Cierre

- **Objetivo:** documentar el incremento terminado.
- **Dependencias:** 7.27.
- **Archivos/familias probables:** documento de cierre en `docs/implementacion/`; otros documentos sólo si reciben autorización específica.
- **Cambios:** matriz final de decisiones, contratos HTTP, locks, idempotencia, documentos, operación segura del reconciliador y evidencias.
- **Pruebas:** revisión cruzada con implementación y resultados; no repetir suites sin cambios o motivos nuevos.
- **Comandos:** `git status --short`; `git diff --stat`; `git diff --check`.
- **Criterio de cierre:** ninguna evidencia obligatoria pendiente. Staging, commit y push siguen siendo acciones separadas, nunca implícitas.

## Tabla de recuperación UX

Contrato obligatorio para 7.19–7.21:

| Situación | Conservar | Invalidar / destino |
|---|---|---|
| `profesional_no_elegible` | Motivo, archivos, UUID | Profesional y slot; Paso 2 |
| `slot_no_disponible` | Motivo, archivos, profesional, UUID y ventana pertinente | Sólo slot; Paso 3 y refrescar |
| `primera_consulta_activa` | Sin corrección dentro del wizard | Feedback bloqueante y Mis turnos |
| 422 de motivo | Resto compatible del estado y UUID | Paso 1, error inline |
| 422 de archivo | Archivos válidos, resto compatible y UUID | Paso 1; marcar/rechazar sólo inválidos identificados |
| Error de disponibilidad | Estado compatible y UUID | Permanecer en Paso 3; retry; no mostrar como vacío |
| POST con resultado incierto | Formulario, archivos y UUID | Mensaje de incertidumbre y retry con la misma UUID |
| Replay exitoso | Turno original | Éxito normal |
| Cambio voluntario de profesional | Motivo, archivos y UUID | Slot anterior |

Errores globales de cantidad/total se presentan como tales; no se atribuye arbitrariamente invalidez a un archivo concreto. Los inválidos se marcan/rechazan sin eliminar archivos válidos y no se avanza mientras existan inválidos.

Si la profesional deja de ser elegible, se vuelve al Paso 2; si se pierde el slot, se vuelve al Paso 3 y se refresca disponibilidad; D-01 bloqueante dirige a Mis turnos. Durante la confirmación se deshabilitan navegación y repetición de submit. El abandono voluntario con datos significativos contempla advertencia, sin draft persistente.

La ventana UI es siete días; backend admite hasta 31 días configurables por request según DT-03; no existe horizonte funcional máximo.

## Matriz D/DT/UX → subpasos responsables

| Decisión | Subpasos responsables | Evidencia de cierre |
|---|---|---|
| D-01 | 7.01, 7.06, 7.11, 7.13, 7.17–7.19, 7.21, 7.23 | GET/POST, estados canónicos, carrera entre profesionales y salida a Mis turnos |
| D-02 | 7.06, 7.11, 7.23, 7.26 | Paciente histórico admitido; sin crear entidades clínicas |
| D-03 | 7.19–7.21, 7.26 | Profesional antes de consultar; estado conservado ante vacío |
| D-04 | 7.05, 7.10–7.11, 7.23 | Misma elegibilidad canónica en listado/consulta/confirmación |
| D-05 | 7.09–7.10, 7.11 | Matriz de grilla y revalidación exacta |
| D-06 | 7.01, 7.10–7.13 | Estados canónicos, intersecciones y bloqueos |
| D-07 | 7.10–7.13, 7.21 | Inicio estrictamente futuro y vencimiento durante el flujo |
| D-08 | 7.10, 7.15, 7.20, 7.26 | Ventanas 7/31 y navegación lejana |
| D-09 | 7.07–7.08, 7.12, 7.19, 7.25 | Límites exactos, MIME real y multipart |
| D-10 | 7.04, 7.12, 7.21, 7.23 | Original protegido y resumen exacto |
| D-11 | 7.11–7.13, 7.18–7.19, 7.21, 7.23 | Carreras, UUID de servidor estable y recuperación de slot |
| D-12 | 7.08, 7.12–7.14, 7.23 | Fallas inyectadas, compensación y reconciliación |
| D-13 | 7.11–7.12, 7.18, 7.21 | Turno directo `PENDIENTE`, sin solicitud intermedia |
| D-14 | 7.01, 7.11, 7.17, 7.21 | Enum y tipo fijado por servidor |
| D-15 | 7.18, 7.21, 7.23, 7.26 | Éxito posterior al commit, redirect y feedback único |
| D-16 | 7.03, 7.17–7.28 | Revisión de alcance sin acciones de otros UC |
| DT-01 | 7.01–7.02 | Tipo string, enum/cast y fixtures |
| DT-02 | 7.05, 7.10–7.13 | Predicado canónico y relaciones actuales |
| DT-03 | 7.01, 7.09–7.11, 7.15, 7.20 | Estados canónicos, motor único, ventanas y fin derivado |
| DT-04 | 7.03–7.04, 7.07–7.08, 7.12, 7.25 | Esquema, privacidad y Apache real |
| DT-05 | 7.11, 7.13, 7.23 | Mutex, paciente y current reads |
| DT-06 | 7.02, 7.08, 7.11–7.15, 7.19, 7.21, 7.23 | Replay, UUID de servidor, atomicidad, locks documentales y huérfanos |
| DT-07 | 7.12, 7.15–7.23 | HTTP, autorización, auditoría y Mis turnos |
| UX-01 | 7.17, 7.19–7.21, 7.26 | Cuatro pasos locales, navegación semanal y recorrido |
| UX-02 | 7.18–7.21, 7.23, 7.26 | Cada recuperación de la tabla, códigos y retry con misma UUID |
| UX-03 | 7.17, 7.19–7.22, 7.26 | Responsive, foco, teclado, semántica y feedback |

## Dependencias principales

- Persistencia: 7.01 → 7.02 → 7.03 → 7.04.
- Reglas reutilizables: 7.05/7.06, documentos 7.07–7.08 y disponibilidad 7.09–7.10.
- Confirmación: esos contratos convergen en 7.11 → 7.12.
- Certificación concurrente: 7.13; después reconciliación 7.14.
- HTTP: Requests 7.15; descarga 7.16; destino Mis turnos 7.17; endpoints 7.18.
- Frontend: 7.19 → 7.20 → 7.21 → 7.22.
- Integración y entorno: 7.23 → aplicación autorizada 7.24 → Apache 7.25.
- Aceptación y cierre: 7.26 → 7.27 → 7.28.

La separación 7.11/7.12 se conserva porque cero adjuntos es un caso funcional válido y permite verificar primero locks/idempotencia/DB. No se publica confirmación HTTP hasta completar ambos.

La auditoría no queda diferida al cierre: creación en 7.11, carga en 7.12 y descarga en 7.16. El CTA se publica cuando el wizard esté operativo, sin enlaces rotos. El estado visual de D-01 no reemplaza validación GET, POST ni locking.

## Puertas P1–P7

| Puerta | Momento | Alcance |
|---|---|---|
| P1 | 7.04 | Enums, esquema, Models y consumidores de Agenda afectados |
| P2 | 7.10 | Elegibilidad, documentos, disponibilidad y regresión de Agenda |
| P3 | 7.13 | Suite SQLite completa y concurrencia real de la Action |
| P4 | 7.18 | Requests, middleware, Policy, rutas y cada contrato HTTP |
| P5 | 7.22 | Vistas/HTTP, build e interacción real |
| P6 | 7.25 | Integración, carreras HTTP, reconciliador concurrente y Apache multipart |
| P7 | 7.27 | Suite completa, MariaDB, build, Pint acotado y diff limpio |

Se conserva la estructura P1–P7 aprobada. P6 explicita la carrera del reconciliador y el máximo multipart. Deben registrarse resultados exactos; no se ocultan pendientes mediante skips ni se modifican tests para debilitar reglas congeladas.

## Riesgos y checkpoints

- Snapshot antiguo: 7.11/7.13 deben demostrar SQL bloqueante sobre todas las lecturas críticas, incluidos hijos y pivotes.
- Locks adicionales de elegibilidad: comprobar interacción con escritores de perfiles. Un deadlock debe producir rollback íntegro y retry explícito, sin refactor transversal preventivo.
- Advisory locks documentales: mecanismo planificado/preferido pendiente de certificación obligatoria en 7.13 contra MariaDB 10.4 y lifecycle real de Laravel. Usar la misma sesión cuando corresponda, no escribir si falla adquisición, liberar explícitamente y verificar commit, rollback, excepción y timeout sin locks filtrados. Si no es seguro/viable, detenerse y reportar; ninguna alternativa arquitectónica sin revisión expresa.
- Conexión: no reconectar silenciosamente durante una transacción fallida ni presumir conservado un lock de sesión perdido.
- Carrera del reconciliador: probar un archivo que supere el grace period mientras su transacción siga activa. El lock, no la estimación de duración, debe protegerlo.
- Commit incierto: preservar archivos hasta poder comprobar estado; no compensar destructivamente por un error de respuesta.
- Replay: probarlo sin archivos originales, con slot pasado y profesional ineligible; UUID generada por servidor, estable y no identificadora de un slot.
- Estados ocupantes: una sola definición canónica; no introducir arrays duplicados ni estados terminales accidentalmente.
- Errores HTTP: no confundir 409, 422, vacío válido y fallo interno; no ramificar por mensajes humanos.
- Inmutabilidad: revisar todas las escrituras nuevas y sus límites; `$fillable` por sí solo no satisface D-10.
- Auditoría: whitelist mínima; sin motivo completo ni binarios; replay no duplica creación/carga; descarga sólo tras autorización y acceso efectivo.
- Apache: certificar 25 MiB más overhead y detectar pérdida/rechazo de uploads; no modificar `php.ini` sin necesidad demostrada.
- Seguridad de pruebas: abortar antes de preparar/limpiar si la suite apunta a una base distinta de la exclusiva autorizada.
- UX real: no sustituir interacción Alpine por aserciones sobre HTML renderizado.

## Criterios de cierre del Paso 7

El cierre documental del Paso 7 exige:

1. Este documento completo, marcado aprobado y congelado, con exactamente los subpasos 7.01–7.28 sin huecos ni duplicados.
2. Contratos HTTP, locks/current reads, replay, documentos, recuperación UX, trazabilidad, dependencias, puertas y checkpoints materializados sin rediseñar decisiones.
3. Las tres microprecisiones finales presentes: estados ocupantes canónicos, UUID generada por servidor y advisory lock sujeto a certificación con regla de detención.
4. `AGENTS.md` actualizado con el estado, documento operativo, jerarquía y siguiente fase.
5. Fuentes locales de 7.00 preservadas sin modificación.
6. Validaciones documentales y `git diff --check` limpios, con estado Git informado.
7. Ninguna implementación, prueba/build, acceso a DB, migración, staging, commit o push realizado como parte de este cierre documental.

Estas condiciones cierran planificación; no certifican implementación ni dan por pasadas las pruebas futuras. Los criterios técnicos de cierre de UC–07 siguen siendo los de cada subpaso y P1–P7.

## Regla histórica de ejecución V1

Esta regla gobernó la ejecución original de los subpasos 7.01–7.28. Esa ejecución ya fue completada y hoy constituye baseline histórico.

Durante V1, cada subpaso 7.01–7.28 requirió autorización y prompt blindado independiente. Antes de actuar se lee `AGENTS.md`, las fuentes pertinentes y el apartado de este documento correspondiente al subpaso solicitado.

No existe autorización de avance automático al siguiente subpaso. Codex debe detenerse después de ejecutar, verificar e informar el cierre del subpaso expresamente solicitado. La aprobación del plan no autoriza migraciones reales sobre desarrollo; se conserva su puerta de confirmación específica.

No hacer staging, commits ni push por iniciativa propia. No inspeccionar, modificar, agregar ni limpiar `docs/archivo/`.

# PARTE II — PLANIFICACIÓN EJECUTABLE DELTA V2 — 7.29–7.52

## Estado de la Parte II

**APROBADA Y CONGELADA — EJECUTADA Y CERRADA**.

Esta Parte II conserva el plan ejecutado para adaptar la implementación V1 al contrato vigente de UC–07 V2. Los objetivos, dependencias y criterios siguientes se mantienen como trazabilidad del proceso; no son trabajo por ejecutar.

| Subpaso | Resultado |
|---|---|
| 7.29 — Instrucciones activas V2 | COMPLETADO |
| 7.30 — Persistencia de descripción | COMPLETADO |
| 7.31 — Request de adjuntos estructurados | COMPLETADO |
| 7.32 — Pipeline documental | COMPLETADO |
| 7.33 — Action, atomicidad y replay | COMPLETADO |
| 7.34 — Disponibilidad agregada | COMPLETADO |
| 7.35 — Disponibilidad HTTP | COMPLETADO |
| 7.36 — Profesionales por inicio | COMPLETADO |
| 7.37 — Integración backend / V2-P2 | COMPLETADO |
| 7.38 — Configuración inicial del wizard | COMPLETADO |
| 7.39 — Estado Alpine V2 | COMPLETADO |
| 7.40 — Paso 1 | COMPLETADO |
| 7.41 — Paso 2 | COMPLETADO |
| 7.42 — Paso 3 | COMPLETADO |
| 7.43 — Paso 4 y multipart | COMPLETADO |
| 7.44 — Recovery R1/R2/R3 | COMPLETADO |
| 7.45 — D-01 y éxito | COMPLETADO |
| 7.46 — Mis turnos | COMPLETADO |
| 7.47 — Stitch, responsive y accesibilidad | COMPLETADO |
| 7.48 — Regresión focal | COMPLETADO |
| 7.49 — MariaDB/InnoDB | COMPLETADO |
| 7.50 — Apache y aceptación integral | COMPLETADO |
| 7.51 — Regresión global, reejecutada sobre `ba97c4b` después de MC-03 | COMPLETADO |
| 7.52 — Cierre documental V2 | COMPLETADO; documentos sin stage, para revisión y versionado del usuario |

V2-P1, V2-P2, V2-P3, V2-P4, V2-P5 y V2-P6: **PASS**. La evidencia global vigente después de MC-03 es **2231 tests / 14391 assertions**, 158,06 s, sin failures/errors/skipped/risky/warnings, exit code 0; Node **150 PASS / 0 FAIL / 0 SKIP**, 455,409 ms, ambos syntax PASS, y build **94 módulos / 3,55 s**, ambos con exit code 0. Pint de MC-03 pasó antes del commit; la reejecución sin cambios PHP no requirió repetirlo ni ejecutar `php -l`.

MC-03 (`ba97c4b`) fue una microcorrección UX/accesibilidad posterior a la primera preparación documental de 7.52: extendió a los Pasos 1, 3 y 4 el patrón de heading sin ring ya aplicado al Paso 2, preservando foco programático, controles y stepper, sin JavaScript productivo. Tras versionarla se recertificó V2-P6 y se actualizó 7.52; no constituye un subpaso 7.53 ni un nuevo incremento.

No autoriza reescribir infraestructura V1 que el gap analysis clasificó como reutilizable.

Durante la ejecución, cada subpaso 7.29–7.52:

- requirió autorización independiente;
- tuvo alcance limitado;
- ejecutó únicamente sus verificaciones previstas o justificadas;
- informó archivos modificados, pruebas y estado Git;
- terminó con detención;
- no autorizó avanzar automáticamente al siguiente.

## Objetivo del delta V2

Adaptar UC–07 para incorporar:

1. disponibilidad agregada antes de seleccionar profesional;
2. wizard canónico:
   - Fecha y horario;
   - Elegir profesional;
   - Motivo y documentación;
   - Revisar y confirmar;
3. selección de `inicio_at` antes de conocer profesional/duración;
4. profesionales filtradas por `inicio_at`;
5. descripción individual obligatoria por cada adjunto válido;
6. multipart estructurado archivo + descripción;
7. recuperación diferenciada de profesional, horario y fecha;
8. diferenciación entre período sin disponibilidad y error técnico;
9. adaptación estructural a Stitch V2;
10. recertificación responsive, concurrencia, transporte real y regresión.

## Fuentes autoritativas V2

Antes de cada subpaso, Codex debe consultar únicamente lo necesario de:

- `AGENTS.md`;
- `docs/_codex_sources/turnos/README_Codex_sources.md`;
- `docs/_codex_sources/turnos/UC-07_Solicitar_turno.txt`;
- `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_TECNICO_V2.0.md`;
- `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_UX_V2.0.md`;
- `docs/_codex_sources/turnos/prototipo_stitch_uc07/README.md`;
- las 14 capturas Stitch V2 cuando el subpaso sea visual;
- esta Parte II de la planificación.

La Parte I puede consultarse únicamente como antecedente de la implementación V1.

## Baseline histórico de apertura del delta

- Rama esperada: `feature/turnos-solicitar-turno`.
- HEAD verificado durante el gap analysis: `3a95e84b26a43fa90f663372ba0ba6c3192ff685`.
- El HEAD puede avanzar por commits manuales del usuario y debe verificarse siempre, nunca asumirse.
- Estado conocido al cerrar el gap analysis:

```text
?? docs/archivo/
?? docs/implementacion/turnos-solicitar-turno-cierre.md
```

`docs/archivo/` permanece fuera de alcance y no debe inspeccionarse, modificarse, agregarse, stagearse ni limpiarse.

Al abrir el delta, el documento de cierre contenía evidencia histórica V1. En 7.52 se convirtió en el cierre definitivo V2 y conservó esa evidencia como anexo histórico, sin stage ni commit automático.

## Infraestructura V1 protegida

Salvo evidencia nueva de contradicción real, preservar:

- `TipoTurnoEnum`;
- `EstadoTurnoEnum` y estados que reservan agenda;
- D-01;
- `ElegibilidadPrimeraConsultaService`;
- `ElegibilidadKinesiologaService`;
- predicado profesional canónico;
- `DisponibilidadPrimeraConsultaService`;
- `AgendaQueryService`;
- grilla determinista;
- overlap semiabierto;
- `RelojAplicacion`;
- mismo día sin buffer;
- índice D-01;
- `clave_idempotencia`;
- `ReplayPrimeraConsultaService`;
- orden de locks kinesióloga → paciente;
- storage privado;
- generación segura de rutas;
- compensación filesystem;
- advisory locking documental;
- reconciliación;
- `DocumentoClinicoPolicy`;
- descarga documental privada;
- `AuditoriaService`;
- aislamiento/paginación backend de Mis turnos;
- integración Alpine/Vite/SweetAlert2.

No reabrir estas piezas sólo porque cambió el flujo UX.

## Decisiones operativas congeladas del delta

### Agregación

Crear una capa fina:

`DisponibilidadAgregadaPrimeraConsultaService`

Debe consumir la disponibilidad individual existente y no duplicar:

- grilla;
- overlap;
- estados ocupantes;
- reloj;
- reglas de Agenda.

Debe permitir:

- agregar slots válidos por fecha;
- deduplicar por `inicio_at`;
- localizar profesionales válidas para un `inicio_at`.

### Endpoint de disponibilidad

Se conserva:

`GET /turnos/solicitar/disponibilidad`

Contrato V2:

- entrada: `desde`, `hasta`;
- ya no requiere `kinesiologa_id`;
- salida: fechas y horas de inicio;
- no existe un `fin_at` universal.

### Profesionales disponibles

Agregar:

`GET /turnos/solicitar/profesionales-disponibles`

Nombre:

`turnos.solicitar.profesionales-disponibles`

Entrada:

`inicio_at`

Salida: únicamente profesionales elegibles que conservan un slot individual válido para ese inicio, con su intervalo real.

### Recuperación después de 409

Preservar inicialmente:

- `primera_consulta_activa`;
- `profesional_no_elegible`;
- `slot_no_disponible`.

No crear códigos separados sólo para profesional/hora/fecha perdida.

Después de un conflicto recuperable, el frontend reevalúa mediante los GET V2:

1. mismo inicio con alternativas → Paso 2;
2. inicio perdido pero fecha conserva horas → Paso 1 conservando fecha;
3. fecha sin horas → Paso 1 sin selección;
4. D-01 → Mis turnos.

Un error técnico de reevaluación no demuestra pérdida de disponibilidad.

### Multipart V2

Contrato conceptual:

```text
adjuntos[0][archivo]
adjuntos[0][descripcion]
adjuntos[1][archivo]
adjuntos[1][descripcion]
```

No utilizar arrays paralelos independientes.

### Persistencia documental

Agregar mediante migration nueva:

`documentos_clinicos.descripcion VARCHAR(500) NULL`

Reglas:

- no modificar migrations históricas;
- no backfill artificial;
- históricos pueden conservar `NULL`;
- UC–07 exige descripción:
  - trim;
  - mínimo 1 carácter significativo;
  - máximo 500 caracteres.

## Puertas V2

### V2-P1 — Documentación clínica backend

Resultado: **V2-P1 — PASS**, después de 7.33. Alcance certificado:

- migration;
- Model/factory;
- Request documental;
- transporte de descripción;
- Action;
- atomicidad;
- replay;
- auditoría privada.

### V2-P2 — Disponibilidad backend

Resultado: **V2-P2 — PASS**, después de 7.37. Alcance certificado:

- agregador;
- disponibilidad agregada HTTP;
- endpoint de profesionales;
- rutas;
- Requests;
- tests HTTP/servicio.

### V2-P3 — Flujo frontend funcional

Resultado: **V2-P3 — PASS**, después de 7.44. Alcance certificado:

- nuevo estado Alpine;
- Pasos 1–4;
- multipart;
- recuperación contextual;
- respuestas obsoletas;
- UUID estable.

### V2-P4 — UX / Stitch / responsive

Resultado: **V2-P4 — PASS**, después de 7.47. Alcance certificado:

- fidelidad estructural;
- D-01 visual;
- éxito;
- Mis turnos;
- responsive;
- accesibilidad.

### V2-P5 — Certificación técnica real

Resultado: **V2-P5 — PASS**, después de 7.50. Alcance certificado:

- regresión focal;
- MariaDB/InnoDB;
- Apache/PHP multipart;
- concurrencia;
- aceptación funcional principal.

### V2-P6 — Cierre integral

Resultado: **V2-P6 — PASS**, reejecutado en 7.51 después de MC-03 sobre el baseline definitivo `ba97c4b`, antes de actualizar el cierre de 7.52. Alcance certificado:

- regresión global;
- Node;
- build;
- Pint acotado;
- `git diff --check`;
- aceptación V2 completada.

---

## 7.29 — Preparar instrucciones activas para el delta V2

- **Objetivo:** alinear `AGENTS.md` con UC–07 V2 antes de modificar código productivo.
- **Dependencias:** gap analysis V2 completo y aprobado; Parte II 7.29–7.52 aprobada y congelada.
- **Archivo permitido:** `AGENTS.md`.
- **Cambios:** sustituir referencias operativas V1 por V2; registrar 7.01–7.28 como baseline histórico ejecutado; registrar 7.29–7.52 como planificación activa; nuevo orden del wizard; disponibilidad agregada; descripción por adjunto; recuperación contextual; Stitch V2; siguiente subpaso 7.30.
- **No hacer:** código productivo, tests, migrations, build, Pint, DB.
- **Verificaciones:** `git diff --check`; `git diff -- AGENTS.md`; `git status --short`; `git diff --stat`.
- **Criterio de cierre:** sólo `AGENTS.md` modificado por el subpaso y Codex detenido antes de 7.30.

## 7.30 — Persistir `documentos_clinicos.descripcion`

- **Objetivo:** introducir la única modificación física obligatoria de V2.
- **Dependencia:** 7.29.
- **Archivos previstos:** nueva migration aditiva; `DocumentoClinico`; `DocumentoClinicoFactory`; tests de schema/model.
- **Cambio:** `descripcion VARCHAR(500) NULL`, reversible, sin backfill y sin tocar migration histórica.
- **Modelo:** proteger descripción como parte del snapshot original; no imponer obligatoriedad global.
- **Factory:** permitir documentos UC–07 con descripción y documentos históricos con `NULL`.
- **Pruebas:** columna, nulabilidad, longitud, rollback, persistencia, histórico `NULL`, inmutabilidad.
- **Cierre:** tests focales PASS; Pint sobre PHP modificado; `git diff --check`; detenerse.

## 7.31 — Adaptar Request de confirmación a adjuntos estructurados

- **Objetivo:** convertir la frontera HTTP a archivo + descripción.
- **Dependencia:** 7.30.
- **Archivos previstos:** `ConfirmarPrimeraConsultaRequest.php` y su test.
- **Cambios:** aceptar `adjuntos[n][archivo]` + `adjuntos[n][descripcion]`; descripción requerida para cada archivo, trim, 1..500; mantener política binaria centralizada; errores estables `adjuntos.N.archivo` / `adjuntos.N.descripcion`.
- **Pruebas:** 0 adjuntos; uno/múltiples; descripción vacía/espacios; 500/501; Unicode; estructura mal formada; archivo/descripcion faltantes; límites documentales.
- **Cierre:** Request tests PASS; Pint; `git diff --check`; detenerse.

## 7.32 — Propagar descripción por el pipeline documental

- **Objetivo:** transportar `descripcion` hasta `DocumentoClinico` sin alterar el lifecycle físico V1.
- **Dependencia:** 7.31.
- **Archivos previstos:** almacenamiento documental, operación documental, gestión documental y tests focales.
- **Cambios:** metadata archivo + descripción; persistencia de `DocumentoClinico.descripcion`; descripción fuera de path, lock key y autorización.
- **Preservar:** disk privado, rutas seguras, advisory locks, cleanup y compensación.
- **Pruebas:** asociación archivo-descripción, múltiples, Unicode, fallos, rollback, descripción no usada como ruta.
- **Cierre:** tests focales PASS; detenerse.

## 7.33 — Integrar descripción en confirmación, atomicidad y replay

- **Objetivo:** cerrar el agregado documental V2 en `ConfirmarPrimeraConsultaAction`.
- **Dependencia:** 7.32.
- **Archivos previstos:** Action y tests de Action/atomicidad/replay/auditoría.
- **Cambios:** consolidar turno + motivo + documentos + descripciones + asociaciones + auditoría; ampliar `comprobarConsolidacion()`; replay histórico resuelto antes de exigir originales/descripciones.
- **Auditoría:** no incluir descripción completa.
- **Pruebas:** alta, múltiples adjuntos, rollback, compensación, resultado incierto, replay, histórico `NULL`, auditoría negativa.
- **Gate:** ejecutar suite focal conjunta del bloque documental.
- **Criterio:** **V2-P1 PASS**; detenerse.

## 7.34 — Crear `DisponibilidadAgregadaPrimeraConsultaService`

- **Objetivo:** agregar la proyección V2 sin duplicar el motor individual.
- **Dependencia:** V2-P1.
- **Archivos nuevos previstos:** servicio agregador y test de servicio.
- **Cambios:** profesionales elegibles → slots individuales → fechas → inicios → profesionales para inicio.
- **No hacer:** nueva grilla, overlap, clock, persistencia de slots o cache prematuro.
- **Pruebas:** una/múltiples profesionales; mismo inicio con mismo/distinto fin; distintos inicios; sin agenda; sin slots; no elegible; mismo día; vacío; orden; deduplicación.
- **Rendimiento:** medir consultas sólo con assertions estables; no optimizar especulativamente.
- **Cierre:** tests focales PASS; detenerse.

## 7.35 — Adaptar disponibilidad HTTP a fechas y horas agregadas

- **Objetivo:** convertir el endpoint existente al contrato V2.
- **Dependencia:** 7.34.
- **Archivos previstos:** Request de disponibilidad, `SolicitarTurnoController`, tests Request/HTTP.
- **Request:** `desde`, `hasta`, máximo técnico 31 días; eliminar `kinesiologa_id`.
- **Respuesta:** `dias -> fecha -> horarios -> inicio_at`; sin `fin_at` universal.
- **Semántica:** vacío correcto = 200; fallo interno real no se convierte en vacío.
- **Pruebas:** rango, exceso, vacío, agregación/deduplicación, error real.
- **Cierre:** tests focales PASS; detenerse.

## 7.36 — Incorporar profesionales disponibles por `inicio_at`

- **Objetivo:** crear la lectura del Paso 2.
- **Dependencia:** 7.35.
- **Archivos:** Request nuevo, Controller, routes, tests Request/rutas/HTTP.
- **Ruta:** `GET /turnos/solicitar/profesionales-disponibles`, nombre `turnos.solicitar.profesionales-disponibles`.
- **Middleware:** `auth`, `verified`, `perfil.completo`, `perfil.paciente`.
- **Entrada:** `inicio_at`.
- **Respuesta:** sólo elegibles con slot válido; identificador y datos mínimos de presentación, `inicio_at`, `fin_at`, duración cuando sea útil.
- **Foto:** no agregar persistencia; fallback frontend.
- **Pruebas:** una/múltiples; distintos fines; lista vacía; no elegible; slot perdido; middleware/ruta.
- **Cierre:** tests PASS; detenerse.

## 7.37 — Integración backend de disponibilidad V2 y gate V2-P2

- **Objetivo:** certificar conjuntamente los contratos backend de Pasos 1 y 2.
- **Dependencias:** 7.34–7.36.
- **Cambios:** preferentemente sólo tests; productivo sólo ante defecto demostrado.
- **Casos:** dos profesionales 17:00 con 30/45 min; fecha única; hora única; ambas profesionales; `fin_at` distinto; pérdida de elegibilidad/slot; ventana vacía; mismo día; 31 días; privacidad.
- **Gate:** elegibilidad + grilla + disponibilidad individual + agregador + Requests + HTTP + routes.
- **Criterio:** **V2-P2 PASS**; detenerse.

## 7.38 — Adaptar `GET /turnos/solicitar` y contrato inicial del wizard

- **Objetivo:** eliminar supuestos V1 del GET inicial.
- **Dependencia:** V2-P2.
- **Archivos:** Controller, `create.blade.php`, `SolicitarTurnoCreateTest`.
- **Cambios:** no precargar lista global como contrato del Paso 2; proveer UUID, URLs, límites y ventana inicial; no consultar slots individuales.
- **D-01:** si bloquea, no iniciar wizard ni disponibilidad; presentar estado V2.
- **Pruebas:** config inicial, UUID, URLs, ausencia de lista global como contrato, D-01, Paso 1.
- **Cierre:** detenerse.

## 7.39 — Reestructurar estado Alpine/JavaScript

- **Objetivo:** cambiar la máquina de estado a dependencias V2.
- **Dependencia:** 7.38.
- **Archivos:** `resources/js/turnos/solicitar-turno.js` y suite Node.
- **Estado:** paso, ventana, agregado, fecha, hora, profesionales, profesional, intervalo, motivo, adjuntos+descripciones, UUID.
- **Eliminar como estado canónico:** slot universal inicio+fin previo a profesional.
- **Dependencias:** fecha → limpia hora/profesional; hora → limpia profesional; profesional no altera fecha/hora; captura no altera disponibilidad.
- **UUID:** jamás regenerarla en transiciones.
- **Stale responses:** adaptar cancelación/secuenciación.
- **Cierre:** Node focal PASS; detenerse.

## 7.40 — Implementar Paso 1 V2: Fecha y horario

- **Objetivo:** selección agregada de fecha e inicio.
- **Dependencia:** 7.39.
- **Archivos:** `_fecha-horario.blade.php`, JS, Node y tests de vista necesarios.
- **Comportamiento:** consulta inicial, loading, fechas consultadas, sólo disponibles seleccionables, horas por fecha, selección de inicio.
- **No mostrar:** profesional ni intervalo universal.
- **Estados:** loading, disponible, deshabilitada, seleccionada, período vacío, error técnico, retry.
- **Stitch:** `desktop/02-paso-1-fecha-horario.png`.
- **Pruebas:** fetch, fecha, hora, cambio de fecha, empty, error, retry, stale response, CTA.
- **Cierre:** detenerse.

## 7.41 — Implementar Paso 2 V2: Profesionales disponibles

- **Objetivo:** mostrar dinámicamente profesionales válidas para `inicio_at`.
- **Dependencia:** 7.40.
- **Archivos:** `_profesionales.blade.php`, JS, Node/vistas.
- **Comportamiento:** consultar endpoint; loading; cards con intervalo propio; seleccionar una.
- **Foto:** opcional; fallback circular sin persistencia nueva.
- **Volver:** conserva fecha/hora mientras no cambien.
- **Stale response:** una respuesta anterior no reemplaza la lista actual.
- **Stitch:** `desktop/03-paso-2-elegir-profesional.png`.
- **Pruebas:** carga, vacío, selección, fines distintos, cambio de hora, stale, error/retry, fallback.
- **Cierre:** detenerse.

## 7.42 — Implementar Paso 3 V2: Motivo, adjuntos y descripciones

- **Objetivo:** adaptar captura local documental.
- **Dependencias:** 7.41 y V2-P1.
- **Archivos:** `_motivo-documentacion.blade.php`, JS, Node/vistas.
- **Entrada válida local:** id, archivo, descripción, errores.
- **Archivo inválido:** rechazo individual; fuera de selección válida/contador/total; no bloquea una vez descartado.
- **Descripción:** propia, required, máximo 500; quitar archivo quita descripción; reemplazo crea nueva unidad.
- **Motivo:** required, máximo 500.
- **Stitch:** desktop Paso 3 + excepciones archivo inválido y descripción requerida.
- **Pruebas:** 0/válidos/inválido; descripción; límites; quitar/reemplazar; conservación de disponibilidad.
- **Cierre:** detenerse.

## 7.43 — Implementar Paso 4 y confirmación multipart V2

- **Objetivo:** construir revisión y POST final.
- **Dependencia:** 7.42.
- **Archivos:** `_revision.blade.php`, JS, Node/vistas/HTTP focales.
- **Resumen:** Primera consulta, fecha, inicio, intervalo, profesional, motivo, archivos+descripciones, Pendiente.
- **Semántica:** no `Asignada`; usar `Seleccionada` o nada.
- **FormData:** motivo, `kinesiologa_id`, `inicio_at`, UUID y `adjuntos[n][archivo/descripcion]`; no `fin_at` autoritativo.
- **422:** mapear errores anidados y volver a Paso 3 conservando estado compatible.
- **Submit:** bloquear doble envío; conservar UUID.
- **Stitch:** desktop Paso 4.
- **Cierre:** tests focales PASS; detenerse.

## 7.44 — Implementar recuperación contextual V2

- **Objetivo:** resolver conflictos sin reiniciar datos independientes.
- **Dependencia:** 7.43.
- **Archivo principal:** JS y suite Node.
- **D-01:** termina flujo → Mis turnos.
- **Conflictos de elegibilidad/slot:** reevaluar mediante GET V2.
- **Caso A:** mismo inicio con alternativas → conservar fecha/hora; limpiar profesional; Paso 2.
- **Caso B:** inicio perdido pero fecha con otras horas → conservar fecha; limpiar hora/profesional; Paso 1.
- **Caso C:** fecha sin horas → limpiar fecha/hora/profesional; Paso 1.
- **Error de reevaluación:** estado técnico/retry; no inferir pérdida.
- **Preservar:** motivo, archivos, descripciones, UUID cuando compatibles.
- **Stitch:** excepciones 04–07.
- **Gate:** suite Node completa + vistas/HTTP afectados.
- **Criterio:** **V2-P3 PASS**; detenerse.

## 7.45 — Ajustar D-01 visual y feedback de éxito V2

- **Objetivo:** alinear entrada/salida global.
- **Dependencia:** V2-P3.
- **Archivos:** `create.blade.php`, parciales existentes necesarios, JS y tests focales.
- **D-01:** sin stepper/disponibilidad; mensaje de Primera consulta activa; datos mínimos propios; CTA Mis turnos; sin acciones UC–06.
- **Éxito:** SweetAlert2 existente; turno registrado, profesional, fecha, horario, Pendiente, ocupa agenda; `Entendido` → Mis turnos; X opcional con mismo destino.
- **Stitch:** excepción D-01 y desktop éxito.
- **Cierre:** tests focales PASS; detenerse.

## 7.46 — Adaptar Mis turnos a referencia estructural V2

- **Objetivo:** alinear presentación preservando backend correcto.
- **Dependencia:** 7.45.
- **Archivos:** vistas de Mis turnos y sus tests.
- **No modificar:** `MisTurnosController` salvo evidencia nueva.
- **Preservar:** aislamiento, paginación, eager loading, orden y localización.
- **Adaptar:** estructura desktop según Stitch; CTA respetando D-01.
- **No agregar:** cancelación, reprogramación o acciones UC–06.
- **Cierre:** tests de Mis turnos PASS; detenerse.

## 7.47 — Pulido Stitch V2, responsive y accesibilidad

- **Objetivo:** cerrar fidelidad estructural y UX-03.
- **Dependencias:** 7.40–7.46.
- **Archivos:** sólo vistas/JS UC–07 ya afectados; layout global sólo ante defecto demostrado.
- **Fidelidad:** funcional 100%; estructural alta; estética alta no pixel-perfect.
- **Mobile:** una columna, `Paso X de 4`, sin overflow operativo, cards apiladas, archivo+descripción vertical, resumen una columna.
- **Accesibilidad:** labels, teclado, foco, estados semánticos, `aria-invalid`, mensajes, loading textual, targets, error focus.
- **Viewport mínimo:** 375×667 y 320×568 más desktop representativo.
- **Tests:** `TurnosAccessibilityViewTest` y afectados; revisión real sigue obligatoria.
- **Gate:** vistas focales + Node + `git diff --check`.
- **Criterio:** **V2-P4 PASS**; detenerse.

## 7.48 — Regresión focal integrada UC–07 V2

- **Objetivo:** ejecutar regresión focal amplia del delta productivo.
- **Dependencia:** V2-P4.
- **Cambios:** preferentemente ninguno; sólo corregir defectos demostrados del delta.
- **Suites mínimas:** enums, schema, models, elegibilidad, grilla, disponibilidad individual/agregada, Requests, Action, atomicidad, storage, HTTP, routes, Mis turnos, Policy/descarga, reconciliación, integración, vistas/accesibilidad y JavaScript.
- **Evidencia:** registrar tests/assertions/tiempo disponible.
- **Cierre:** todo verde o detenerse con diagnóstico.

## 7.49 — Checkpoint MariaDB/InnoDB V2

- **Objetivo:** recertificar garantías no demostrables por SQLite.
- **Dependencia:** 7.48.
- **Seguridad:** sólo DB exclusiva de test; abortar antes de preparar/limpiar si no puede verificarse.
- **Cobertura:** locks kinesióloga→paciente, D-01, mismo slot, profesionales distintas, replay, descripción, rollback, filesystem, advisory locks, incertidumbre, nueva migration.
- **Escenario V2:** mismo `inicio_at` con profesionales/duraciones distintas cuando corresponda.
- **Comando:** suite MariaDB existente.
- **Cierre:** resultados reales registrados; detenerse.

## 7.50 — Checkpoint Apache/PHP, multipart y aceptación funcional V2

- **Objetivo:** validar transporte y flujo real.
- **Dependencia:** 7.49.
- **Multipart real:** pares archivo+descripción; múltiples; límites 10/25 MiB; descripción; MIME; índices; verificar límites efectivos sin cambiar `php.ini` preventivamente.
- **Aceptación manual mínima:** Mis turnos, CTA, D-01, Paso 1, fechas/horas, empty/error, Paso 2, duraciones distintas, Paso 3, archivo inválido, descripción faltante, Paso 4, éxito, Mis turnos, tres recuperaciones, UUID, mobile 375×667 y 320×568, teclado/foco y overflow.
- **Gate:** MariaDB + Apache/multipart + aceptación principal.
- **Criterio:** **V2-P5 PASS**; detenerse.

## 7.51 — Regresión global y calidad final V2

- **Objetivo:** certificar ausencia de regresiones generales.
- **Dependencia:** V2-P5.
- **Ejecutar:** `php artisan test`; suite Node completa pertinente; `npm run build`; Pint sólo sobre PHP modificado por el delta; `git diff --check`; estado/stat.
- **No hacer:** corregir deuda histórica global de Pint ajena al delta.
- **Comprobar:** `docs/archivo/` no tocado y cierre no stageado accidentalmente.
- **Criterio:** PHPUnit PASS + Node PASS + build PASS + Pint scoped PASS + diff limpio = **V2-P6 técnico PASS**.
- **Cierre:** detenerse ante cualquier gate fallido.

## 7.52 — Cierre documental definitivo de UC–07 V2

- **Objetivo:** actualizar trazabilidad sólo después de certificación integral.
- **Dependencias:** V2-P6 + aceptación manual aprobada.
- **Archivos previstos:** `AGENTS.md`, este documento y `docs/implementacion/turnos-solicitar-turno-cierre.md`.
- **No tocar:** `docs/archivo/`.
- **Planificación:** marcar 7.29–7.52 según resultados reales sin borrar historia V1.
- **AGENTS:** estado V2 y baselines finales reales.
- **Cierre:** distinguir certificación V1, devolución de cátedra, delta V2 y resultados finales.
- **Git:** Codex no stagea ni commitea por iniciativa propia; `git diff --check` y `git status --short`.
- **Estado esperado:** **UC–07 SOLICITAR TURNO V2 — IMPLEMENTADO, CERTIFICADO Y DOCUMENTADO**.

## Matriz resumida de dependencias V2

```text
7.29  AGENTS / instrucciones V2
  ↓
7.30  schema descripcion
  ↓
7.31  Request multipart
  ↓
7.32  pipeline documental
  ↓
7.33  Action + atomicidad + replay
  ↓
V2-P1
  ↓
7.34  agregador
  ↓
7.35  disponibilidad HTTP
  ↓
7.36  profesionales HTTP
  ↓
7.37  integración backend
  ↓
V2-P2
  ↓
7.38  create/config inicial
  ↓
7.39  state machine JS
  ↓
7.40  Paso 1
  ↓
7.41  Paso 2
  ↓
7.42  Paso 3
  ↓
7.43  Paso 4 / multipart
  ↓
7.44  recuperación contextual
  ↓
V2-P3
  ↓
7.45  D-01 + éxito
  ↓
7.46  Mis turnos
  ↓
7.47  Stitch + responsive + accesibilidad
  ↓
V2-P4
  ↓
7.48  regresión focal
  ↓
7.49  MariaDB/InnoDB
  ↓
7.50  Apache/multipart + aceptación
  ↓
V2-P5
  ↓
7.51  regresión global / build / Pint
  ↓
V2-P6
  ↓
7.52  cierre documental
```

## Archivos nuevos previstos por el delta

La planificación autoriza conceptualmente:

- `database/migrations/<timestamp>_add_descripcion_to_documentos_clinicos_table.php`;
- `app/Services/Turnos/DisponibilidadAgregadaPrimeraConsultaService.php`;
- `app/Http/Requests/ConsultarProfesionalesDisponiblesPrimeraConsultaRequest.php`;
- `tests/Feature/Services/Turnos/DisponibilidadAgregadaPrimeraConsultaTest.php`;
- `tests/Feature/Requests/ConsultarProfesionalesDisponiblesPrimeraConsultaRequestTest.php`.

Un parcial visual nuevo para D-01 puede autorizarse en 7.45 si mejora mantenibilidad.

Otro archivo nuevo no previsto requiere detenerse e informar antes de crearlo, salvo autorización expresa del prompt específico.

## Archivos que no deben reabrirse sin evidencia

Como regla inicial:

- `app/Enums/TipoTurnoEnum.php`;
- `app/Enums/EstadoTurnoEnum.php`;
- `app/Models/Turno.php`;
- `app/Models/DocumentoClinicoOrigenTurno.php`;
- `app/Models/Kinesiologa.php`;
- `app/Models/Paciente.php`;
- `app/Models/User.php`;
- `app/Services/Turnos/ElegibilidadPrimeraConsultaService.php`;
- `app/Services/Turnos/ElegibilidadKinesiologaService.php`;
- `app/Services/Turnos/DisponibilidadPrimeraConsultaService.php`;
- `app/Services/Agenda/AgendaQueryService.php`;
- `app/Services/Agenda/RelojAplicacion.php`;
- `app/Services/Turnos/ReplayPrimeraConsultaService.php`;
- `app/Services/Turnos/PoliticaDocumentosClinicosService.php`;
- `app/Services/Turnos/ReconciliadorDocumentosClinicosService.php`;
- `app/Services/Turnos/Documentos/CoordinadorBloqueoDocumentalMariaDb.php`;
- `app/Services/Turnos/Documentos/BloqueoDocumental.php`;
- `app/Console/Commands/ReconciliarDocumentosClinicosCommand.php`;
- `app/Policies/DocumentoClinicoPolicy.php`;
- `app/Http/Controllers/DescargarDocumentoClinicoController.php`;
- `app/Http/Controllers/MisTurnosController.php`;
- `app/Services/AuditoriaService.php`;
- `config/documentos_clinicos.php`;
- `config/turnos.php`;
- `config/filesystems.php`;
- `resources/js/app.js`;
- `resources/views/layouts/navigation.blade.php`.

No modificar migrations históricas existentes de UC–07.

Si una prueba demuestra la necesidad de adaptar alguno de estos archivos, detener el subpaso, presentar evidencia y ampliar alcance únicamente con autorización.

## Política de pruebas del delta

- Por subpaso: pruebas focales.
- Por gate: suite conjunta del bloque.
- 7.48: regresión focal integral UC–07.
- 7.49: MariaDB/InnoDB real de test.
- 7.50: Apache/PHP + aceptación.
- 7.51: regresión global.

No diferir todos los tests hasta el final y no ejecutar regresión global después de cada cambio pequeño.

## Política de commits

Codex no realiza commits por iniciativa propia.

El usuario controla:

- staging;
- commits;
- mensajes;
- checkpoints Git.

No stagear:

- `docs/archivo/`;
- el cierre antes del subpaso autorizado;
- `_codex_sources`.

## Criterio de éxito del delta V2

UC–07 V2 quedó cerrado al certificar los siguientes criterios, preservados del plan congelado:

- comienza por disponibilidad real;
- sólo permite fechas con disponibilidad;
- selecciona hora de inicio sin duración universal;
- sólo muestra profesionales válidas para ese inicio;
- cada profesional conserva su intervalo real;
- motivo continúa obligatorio;
- adjuntos continúan opcionales;
- cada adjunto válido posee descripción obligatoria;
- un archivo inválido se rechaza individualmente;
- confirmación sigue siendo autoritativa;
- D-01 continúa protegida;
- locking e idempotencia continúan correctos;
- el turno sigue siendo `PRIMERA_CONSULTA/PENDIENTE`;
- recuperación distingue profesional/hora/fecha;
- vacío y error técnico son estados distintos;
- Stitch V2 tiene fidelidad estructural alta;
- responsive/accesibilidad quedan recertificados;
- MariaDB/concurrencia queda verde;
- multipart Apache real queda verde;
- regresión global queda verde;
- documentación final refleja V2.

## Regla de ejecución V2 aplicada — proceso concluido

Después de congelar esta Parte II se ejecutaron los subpasos 7.29–7.52 mediante prompts blindados independientes.

El primer subpaso autorizado después del commit documental de esta planificación fue:

**7.29 — Preparar instrucciones activas para el delta V2**

No existe autorización de avance automático.

Cada prompt terminó y requirió aprobación antes de continuar. Los subpasos 7.29–7.52 están completados; no existe autorización para un nuevo incremento ni para reejecutar sus gates por iniciativa propia.

La aprobación de esta planificación no autoriza migrations reales sobre la base de desarrollo; las operaciones sobre entornos reales mantienen sus puertas específicas.

No hacer staging, commits ni push por iniciativa de Codex.

No inspeccionar, modificar, agregar ni limpiar `docs/archivo/`.
