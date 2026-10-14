# AGENTS.md — KinesioSoft

## Propósito

Este archivo contiene las instrucciones permanentes para Codex al trabajar en KinesioSoft.

Aplica a todo el repositorio salvo que una instrucción más específica, aprobada explícitamente por el usuario, indique lo contrario.

## Proyecto

KinesioSoft es un sistema de gestión para un consultorio de kinesiología desarrollado como Trabajo Final de Analista de Sistemas.

Stack actual:

- Laravel 12.
- PHP 8.2.
- Blade.
- Breeze.
- Tailwind CSS.
- Alpine.js.
- SweetAlert2.
- MariaDB/MySQL sobre XAMPP en desarrollo.
- PHPUnit para pruebas automatizadas.
- Spatie Permission para roles/permisos.

## Incremento activo

Caso de uso actualmente en desarrollo:

**UC–07 Solicitar turno**

Rama esperada:

`feature/turnos-solicitar-turno`

Commit base oficial del incremento:

`9d9b1510402e64c310f02ec1014b24333c13ed0c`

Ese commit corresponde al cierre documental de **UC–XX Configurar agenda de atención** y conserva como antecesor técnico el incremento de Agenda completo.

Estado del trabajo al iniciar este incremento:

- Paso 1 — Alcance funcional: aprobado y congelado.
- Paso 2 — Relevamiento técnico: completo.
- Paso 3 — Gap analysis: aprobado y congelado.
- Paso 4 — Diseño técnico/físico: aprobado y congelado.
- D-01 a D-16: aprobadas y congeladas.
- DT-01 a DT-07: aprobadas y congeladas.
- Consolidación Técnica/Física V1.0: aprobada y congelada.
- Próxima etapa metodológica: Paso 5 — Diseño UX navegable.

No iniciar implementación de UC–07 mientras el usuario no autorice explícitamente el paso o subpaso de implementación correspondiente.

### Baseline previo a UC–07

Último baseline funcional certificado antes de iniciar este incremento:

- `php artisan test`: **1376 tests passed, 8842 assertions**.
- `npm run build`: **PASS**.
- `git diff --check`: limpio al cerrar el incremento anterior.
- Working tree sin cambios trackeados al crear la rama de UC–07.
- `docs/archivo/` permanece deliberadamente no trackeado.

Pint:

- El repositorio posee deuda histórica de formato detectada por una ejecución global de Pint sobre archivos versionados ajenos a UC–07.
- Esa deuda preexistente no debe corregirse masivamente dentro de UC–07.
- Para UC–07, exigir Pint PASS sobre los archivos modificados por el incremento cuando corresponda y mantener `git diff --check` limpio.
- No mezclar reformateos globales de módulos ajenos con cambios funcionales de UC–07.

Si la rama activa, el commit base o el estado Git no coinciden con lo esperado, detenerse e informarlo antes de modificar archivos.

No modificar, agregar, eliminar, inspeccionar ni limpiar `docs/archivo/` salvo instrucción explícita del usuario.

## Preflight técnico certificado para UC–07

Estado del entorno verificado antes de comenzar el incremento:

- MariaDB: `10.4.32-MariaDB`.
- Driver Laravel: `mysql`.
- Base de desarrollo observada: `kinesiosoft_db`.
- 43 migraciones versionadas aplicadas; ninguna pendiente al momento del preflight.
- Tablas relevantes de Agenda/Turnos: InnoDB.
- Isolation level observado: `REPEATABLE-READ`.
- `sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.
- `explicit_defaults_for_timestamp`: OFF.
- PHP CLI observado: 8.2.12.
- `upload_max_filesize`: 40M.
- `post_max_size`: 40M.
- `max_file_uploads`: 20.
- `memory_limit`: 512M.
- extensión `fileinfo`: disponible.
- Apache/XAMPP apunta a `C:/xampp/php` mediante `PHPINIDir` y no se detectó un `LimitRequestBody` restrictivo en la configuración inspeccionada.

Limitación conocida:

- Los valores efectivos del SAPI web de Apache no quedaron certificados mediante una petición HTTP real.
- Antes de cerrar la implementación de DT-04, verificar mediante el flujo web real que la política de archivos funciona efectivamente con Apache/PHP y no sólo con PHP CLI/configuración estática.

## Fuente de verdad para UC–07 Solicitar turno

Las fuentes locales de apoyo para Turnos están en:

`docs/_codex_sources/turnos/`

Antes de trabajar en el incremento, leer primero:

`docs/_codex_sources/turnos/README_Codex_sources.md`

No inventar nombres de archivos dentro de esa carpeta. Utilizar el README y el contenido real disponible para localizar las fuentes pertinentes a UC–07.

Jerarquía de decisión para este incremento:

1. Las decisiones explícitas **D-01 a D-16**, **DT-01 a DT-07** y la **Consolidación Técnica/Física V1.0** aprobadas por el usuario para UC–07 son vinculantes como interpretación, precisión y diseño del incremento.
2. El Diccionario de Datos vigente y el Modelo Relacional vigente gobiernan persistencia, entidades, relaciones y estructura general, salvo extensiones técnicas expresamente aprobadas en DT-01…DT-07.
3. El caso de uso formal UC–07 y los requisitos funcionales vigentes gobiernan comportamiento, actor, flujo, excepciones y postcondiciones.
4. Los casos de uso relacionados con Turnos sirven como contexto de integración futura y no autorizan ampliar el alcance de UC–07.
5. El código real existente de KinesioSoft define convenciones técnicas y patrones Laravel reutilizables, pero no puede contradecir decisiones funcionales congeladas.
6. Los prototipos visuales sólo son referencia UX/UI y nunca prevalecen sobre dominio, persistencia, reglas funcionales o decisiones técnicas congeladas.

Las decisiones D-01…D-16 y DT-01…DT-07 no deben reabrirse durante programación salvo que aparezca una incompatibilidad técnica real, demostrable y no prevista. En ese caso, detenerse, documentar la evidencia y pedir decisión al usuario antes de modificar arquitectura o reglas.

Si dos fuentes de autoridad parecen contradecirse, detenerse e informar la discrepancia antes de resolverla por cuenta propia.

## Decisiones funcionales congeladas de UC–07

### D-01 — Unicidad de Primera consulta activa

- Un paciente no puede mantener simultáneamente más de una Primera consulta futura que reserve agenda.
- Para esta regla, como mínimo `PENDIENTE` y `CONFIRMADO` reservan agenda.
- Estados terminales no deben bloquear indefinidamente nuevas Primeras consultas.
- La regla se aplica por paciente, no por padecimiento.
- Revalidar al ingresar al flujo y autoritativamente al confirmar.

### D-02 — Significado de Primera consulta

- Primera consulta significa consulta inicial de un nuevo episodio/ciclo, no necesariamente primera consulta histórica del paciente.
- Un paciente histórico puede volver a solicitar una Primera consulta para un nuevo ciclo.
- UC–07 sólo registra el motivo expresado por el paciente.
- No crea `Padecimiento`, diagnóstico ni clasificación clínica.

### D-03 — Disponibilidad no es precondición global de entrada

- El paciente elegible puede iniciar UC–07 aunque todavía no se conozca disponibilidad.
- Primero puede informar motivo/adjuntos y seleccionar profesional.
- La disponibilidad real se calcula después de seleccionar kinesióloga.
- Si no hay slots en la ventana actual, conservar los datos válidos y permitir cambiar profesional o fechas.

### D-04 — Kinesióloga habilitada

- Reutilizar las reglas canónicas existentes de KinesioSoft.
- Habilitación profesional y disponibilidad temporal son conceptos distintos.
- No crear una regla paralela independiente de las existentes.

### D-05 — Generación determinista de slots

- Resolver la configuración aplicable a cada fecha objetivo.
- Procesar cada franja independientemente.
- El primer slot comienza exactamente en `hora_inicio`.
- Avanzar exactamente `duracion_turno_minutos`.
- Sólo generar slots completamente contenidos en la misma franja.
- El final puede coincidir exactamente con `hora_fin`.
- Un remanente menor que la duración no genera slot.
- No fusionar ni cruzar franjas.
- Turnos y bloqueos no desplazan ni recompaktan la grilla.

### D-06 — Disponibilidad de un slot

- Un slot sólo es disponible si todo su intervalo está libre.
- Usar intersección semiabierta: `inicioA < finB && finA > inicioB`.
- Bloqueos no anulados impiden disponibilidad.
- Turnos que reservan agenda impiden disponibilidad.
- En el MVP, `PENDIENTE` y `CONFIRMADO` reservan agenda.
- `CANCELADO` libera agenda.
- El paciente sólo ve horarios disponibles; no causas ni identidades de ocupación.

### D-07 — Mismo día

- Se permite reservar el mismo día sólo cuando `slot_inicio > ahora`.
- No existe buffer funcional adicional.
- Si el slot alcanza su hora de inicio durante el flujo, la confirmación debe rechazarse y exigir otra selección.

### D-08 — Horizonte de búsqueda

- No existe máximo funcional fijo de anticipación.
- La UI puede consultar ventanas finitas por UX/rendimiento.
- El límite técnico congelado para una consulta de disponibilidad es de hasta 31 días por request, configurable.
- Ese límite por request no constituye un máximo de reserva.
- Cada fecha usa la configuración aplicable a esa fecha.
- No extrapolar una configuración donde no exista ninguna aplicable.
- Una configuración abierta continúa vigente hasta ser sustituida conforme a las reglas de Agenda.

### D-09 — Adjuntos clínicos

- Adjuntos opcionales, múltiples, 0..N.
- Política MVP: hasta 5 archivos.
- Máximo: 10 MiB por archivo.
- Máximo total de archivos por confirmación: 25 MiB.
- Formatos permitidos: PDF, JPEG/JPG y PNG.
- Validar backend, MIME real, tamaño individual, cantidad y total.
- Antes de confirmar, el paciente puede agregar/quitar/reemplazar archivos en el wizard.
- Después de confirmar, el conjunto asociado al turno es histórico e inmutable.
- El upload del paciente no equivale a validación clínica profesional.
- Los documentos son privados y sólo se sirven mediante autorización de la aplicación.

### D-10 — Inmutabilidad del snapshot inicial

- Antes de confirmar, motivo y adjuntos son editables.
- Después de confirmar, el motivo original no se edita ni se sobrescribe.
- El conjunto de archivos asociado a la solicitud confirmada no se reemplaza retroactivamente.
- Cambios clínicos posteriores deben modelarse como operaciones nuevas o estados/clasificaciones previstos, sin destruir el original.
- Cancelar o reprogramar un turno no reescribe el motivo ni elimina sus documentos originales.

### D-11 — Concurrencia

- Ver o seleccionar un slot no lo reserva.
- No existe hold temporal en el MVP.
- La confirmación revalida autoritativamente D-01, DT-02, agenda aplicable, grilla, tiempo, bloqueos y ocupación.
- Ante dos confirmaciones incompatibles, como máximo una puede consolidarse.
- Multi-tab del mismo paciente también queda protegido.
- Doble submit/retry de la misma intención no debe duplicar el turno.

### D-12 — Atomicidad funcional

- La confirmación es una sola operación todo-o-nada.
- Sólo hay éxito cuando turno, motivo y todos los adjuntos del resumen confirmado quedaron consolidados de forma coherente.
- Una falla requerida produce rollback y no deja un turno parcialmente válido.
- Los archivos físicos nuevos deben limpiarse por compensación cuando la DB no consolida.
- Respuesta incierta/retry no debe generar duplicados.

### D-13 — Resultado de la confirmación

- UC–07 crea directamente un `Turno`.
- Tipo: `PRIMERA_CONSULTA`.
- Estado inicial: `PENDIENTE`.
- No existe una entidad intermedia `SolicitudTurno` que espere aprobación profesional.
- `PENDIENTE` ocupa agenda inmediatamente.

### D-14 — Clasificación del turno

- Todo turno creado por UC–07 es `PRIMERA_CONSULTA` desde su creación.
- La clasificación es independiente del estado operativo.
- No inferirla del motivo, estado, consulta o plan.
- `SEGUIMIENTO` es una clasificación distinta.
- Sobreturno/urgencia/emergencia quedan fuera del MVP.

### D-15 — Feedback de éxito

- El feedback de éxito es inmediato y visual después del commit completo.
- Debe comunicar que el turno ya fue registrado.
- Puede mostrar profesional, fecha, hora, estado y acceso a Mis turnos.
- No implica email, SMS, push ni notificación persistente dentro de UC–07.

### D-16 — Límite funcional

UC–07 incluye:

- ingreso desde Mis turnos;
- validación preliminar de elegibilidad del paciente;
- motivo obligatorio;
- adjuntos opcionales;
- selección de kinesióloga habilitada;
- consulta de disponibilidad;
- selección de slot;
- resumen;
- confirmación autoritativa;
- alta `PRIMERA_CONSULTA/PENDIENTE`;
- asociación documental privada;
- feedback de éxito;
- visibilidad mínima posterior en Mis turnos.

UC–07 no incluye:

- paciente provisorio;
- sobreturno, urgencia o superposición excepcional;
- turnos de seguimiento solicitados por el paciente;
- cancelación;
- reprogramación;
- confirmación de asistencia;
- ausencia;
- Consulta clínica;
- Padecimiento;
- diagnóstico;
- evaluación;
- plan de tratamiento;
- gestión clínica integral de documentos;
- clasificación documental por padecimiento;
- email/SMS/push de confirmación;
- reservas de equipos;
- ofertas de adelanto.

## Diseño técnico/físico congelado de UC–07

### DT-01 — Tipo de turno

- Mantener `turnos.tipo` como `string(30)`.
- Crear `TipoTurnoEnum` respaldado por string con:
    - `PRIMERA_CONSULTA`
    - `SEGUIMIENTO`
- `Turno` debe castear `tipo` al enum.
- UC–07 fija `PRIMERA_CONSULTA` internamente; el cliente no controla ese valor.
- No agregar native `ENUM` ni `CHECK` específico para `tipo` en este incremento.
- Factories/tests deben utilizar el enum canónico.

### DT-02 — Elegibilidad técnica de kinesióloga

- No agregar `kinesiologas.habilitada` ni otra bandera duplicada.
- La elegibilidad es derivada del estado canónico existente.
- Requiere, en el `User` asociado:
    - rol `KINESIOLOGO`;
    - cuenta activa;
    - correo verificado;
    - perfil profesional completo según las reglas existentes.
- La completitud profesional sigue siendo responsable de título, matrícula activa y especialidad conforme al modelo actual.
- Incorporar un predicado canónico de alto nivel en `User`, conceptualmente `puedeAtenderComoKinesiologo()`.
- Un servicio de elegibilidad debe listar y validar profesionales reutilizando ese predicado.
- Revalidar elegibilidad al consultar disponibilidad y al confirmar.
- No exigir agenda disponible para considerar habilitada a una profesional.
- No inventar vencimientos de matrícula ni exigir que catálogos históricos de título/especialidad permanezcan activos si la regla actual no lo exige.

### DT-03 — Motor de disponibilidad

- Crear un servicio específico de Turnos, conceptualmente `DisponibilidadPrimeraConsultaService`.
- Reutilizar `AgendaQueryService`, configuraciones, franjas, bloqueos, turnos ocupantes y `RelojAplicacion`.
- Los slots son valores derivados; no persistir tabla de slots, holds ni reservas temporales.
- Generar slots con la grilla determinista de D-05.
- Filtrar por D-06 y D-07.
- Consultar ventanas finitas de hasta 31 días por request como límite técnico configurable.
- Obtener ocupaciones/bloqueos por ventana; no realizar consultas SQL por slot.
- Generación para visualización y revalidación final deben compartir el mismo motor.
- En confirmación, el servidor recibe `kinesiologa_id` e `inicio_at`, recalcula la configuración, verifica pertenencia exacta a la grilla y deriva `fin_at`.
- No confiar en `fin_at` enviado por cliente.
- No cachear disponibilidad en el MVP sin evidencia de necesidad.

### DT-04 — Documentos clínicos

Implementar:

- `documentos_clinicos`.
- `documentos_clinicos_origen_turno`.

No implementar todavía:

- `documentos_clinicos_padecimiento`.

`documentos_clinicos` debe conservar, conforme al diseño vigente:

- `paciente_id`;
- `cargado_por_user_id`;
- `origen`;
- `estado_validacion`;
- `tipo_documento_clinico`;
- `nombre_original`;
- `ruta_storage`;
- `mime_type`;
- `tamanio_bytes`;
- timestamps.

Reglas UC–07:

- `origen = SOLICITUD_TURNO`.
- `estado_validacion = PENDIENTE`.
- `tipo_documento_clinico = SIN_CLASIFICAR`.
- `ruta_storage` única y generada por backend.
- Storage exclusivamente privado.
- Sin URL pública directa.
- Sin pre-upload/staging persistente en el MVP.
- Política central: 5 archivos, 10 MiB individual, 25 MiB total, PDF/JPEG/PNG.
- Mantener nombre original como metadato; nunca usarlo como ruta física confiable.
- Encapsular storage/validación/cleanup en un servicio documental reutilizable.
- Antes de cerrar DT-04 implementado, verificar los límites efectivos mediante el flujo web Apache/PHP real.

### DT-05 / DT-06 — Concurrencia, idempotencia y atomicidad

- Toda confirmación pasa por una única Action, conceptualmente `ConfirmarPrimeraConsultaAction`.
- Ejecutar la creación dentro de `DB::transaction()`.
- Usar `kinesiologas.id` como mutex de agenda mediante `lockForUpdate()`.
- Bloquear también `pacientes.id` para proteger D-01 incluso entre profesionales diferentes.
- Mantener un orden de locks estable y coherente con los patrones existentes.
- Después de adquirir locks, releer y revalidar condiciones mutables; no confiar en lecturas previas.
- Toda operación futura que cree turnos o altere intervalo/estado de manera que cambie ocupación debe respetar el mismo convenio de locking por kinesióloga.

Modificar `turnos` mediante nueva migración:

- `clave_idempotencia`: nullable, UNIQUE.
- índice no único `(paciente_id, tipo, estado, inicio_at)` para D-01.

Idempotencia:

- UC–07 genera una UUID por flujo.
- La UUID identifica la intención de solicitar la Primera consulta, no un slot particular.
- Si la UUID ya corresponde a un turno consolidado del mismo paciente y contexto, devolver ese mismo turno sin recrear nada.
- Un replay ya consolidado se resuelve antes de exigir nuevamente condiciones propias de una creación nueva.
- Si la UUID no está consolidada, ejecutar la creación autoritativa completa.
- Rechequear UUID dentro de la transacción antes de insertar.

Atomicidad:

- Turno, documentos, asociaciones y auditoría deben consolidarse como una única operación funcional.
- Los archivos físicos nuevos se registran para compensación.
- Ante excepción previa al commit: rollback DB + eliminación de archivos nuevos.
- No usar retries automáticos del callback transaccional si pueden repetir efectos de filesystem.
- Deadlock/timeout debe dejar la operación sin efectos parciales y permitir retry seguro con la misma UUID.
- Incorporar una capacidad de reconciliación para eliminar archivos privados huérfanos sin referencia DB y con antigüedad segura, sin tocar archivos válidos.

### DT-07 — Orquestación, seguridad, Mis turnos y auditoría

Rutas previstas:

- `GET /turnos/mis-turnos` → `turnos.mis-turnos.index`.
- `GET /turnos/solicitar` → `turnos.solicitar.create`.
- `GET /turnos/solicitar/disponibilidad` → `turnos.solicitar.disponibilidad`.
- `POST /turnos/solicitar` → `turnos.solicitar.store`.
- `GET /documentos-clinicos/{documento}/descargar` → `documentos-clinicos.download`.

Middleware para UC–07 del paciente:

- `auth`.
- `verified`.
- `perfil.completo`.
- `perfil.paciente`.

La descarga documental debe usar autenticación, perfil completo y una Policy específica del documento, no una URL pública.

`Mis turnos` mínimo:

- obtener siempre al paciente desde el usuario autenticado;
- no aceptar `paciente_id` arbitrario;
- mostrar al menos tipo, profesional, fecha, hora y estado;
- ofrecer `Solicitar nuevo turno`;
- usar paginación cuando corresponda;
- no implementar en este incremento cancelación, reprogramación, confirmación de asistencia ni otras acciones de UC–06.

Requests previstos:

- `ConsultarDisponibilidadPrimeraConsultaRequest`.
- `ConfirmarPrimeraConsultaRequest`.

Confirmación acepta del cliente únicamente:

- `motivo_solicitud`;
- `kinesiologa_id`;
- `inicio_at`;
- `clave_idempotencia`;
- `adjuntos[]`.

El servidor deriva:

- `paciente_id`;
- `tipo`;
- `estado`;
- `fin_at`;
- `cargado_por_user_id`;
- origen/estado/tipo documental inicial;
- `ruta_storage`;
- MIME/tamaño autoritativos.

Contratos HTTP:

- `200`: disponibilidad válida, incluso vacía.
- `422`: validación de entrada/archivos.
- `409`: conflicto de estado al revalidar una creación aún no consolidada.
- `201`: creación nueva exitosa.
- `200`: replay idempotente de una operación ya consolidada.
- Un error interno de integridad no debe disfrazarse como falta de disponibilidad.

Frontend de confirmación:

- Blade + Tailwind + Alpine.
- `FormData` + `fetch` para archivos.
- Redirigir a Mis turnos después del éxito.
- Reutilizar el feedback global SweetAlert2.

Autorización documental mínima:

- Paciente: puede acceder a sus propios documentos.
- Kinesióloga: puede acceder a documentos originados en turnos asignados a ella.
- ADMINISTRADOR/SUPERADMIN: no reciben lectura clínica por defecto sólo por su rol administrativo.
- No crear `TurnoPolicy` sólo por este incremento si no existe todavía una operación individual que la necesite.

Inmutabilidad técnica:

- impedir modificación posterior de `motivo_solicitud` original;
- proteger metadatos originales del documento;
- proteger contenido/ruta física;
- proteger la asociación documento-turno;
- permitir que futuros UC autorizados evolucionen únicamente `estado_validacion` y `tipo_documento_clinico` según corresponda.

Auditoría:

- reutilizar `AuditoriaService`;
- auditar creación de Primera consulta;
- auditar cada documento clínico cargado;
- auditar acceso efectivo mediante descarga de documento clínico;
- no duplicar contenido binario ni el motivo clínico completo en snapshots de auditoría.

## Forma obligatoria de trabajo

Trabajar únicamente en el paso, subpaso o bloque que el usuario solicite explícitamente.

Antes de modificar:

1. Leer este `AGENTS.md`.
2. Leer `docs/_codex_sources/turnos/README_Codex_sources.md` cuando el trabajo corresponda a Turnos.
3. Leer las fuentes y decisiones específicas indicadas por el paso/subpaso.
4. Inspeccionar primero los archivos reales del proyecto relacionados.
5. Comparar fuentes congeladas, requerimiento y código existente.
6. Informar cualquier discrepancia relevante antes de inventar una solución no prevista.
7. Verificar `git branch --show-current`, `git rev-parse HEAD` y `git status --short` cuando se vaya a modificar el repositorio.

Si se crea una planificación ejecutable congelada específica para UC–07, leer también el subpaso correspondiente antes de modificar.

Después de modificar:

1. Ejecutar las pruebas indicadas por el subpaso.
2. No avanzar si una prueba requerida falla por el cambio actual.
3. Informar archivos creados.
4. Informar archivos modificados.
5. Resumir decisiones aplicadas.
6. Mostrar comandos de prueba ejecutados y resultado exacto.
7. Mostrar el estado Git relevante.
8. Detenerse al finalizar el subpaso solicitado.

No adelantar trabajo de un subpaso posterior aunque parezca conveniente.

## Arquitectura a respetar

Antes de crear nuevas abstracciones, inspeccionar implementaciones equivalentes existentes.

Como patrón general para nuevos flujos de escritura, reutilizar cuando corresponda:

`Controller -> Form Request -> Action -> DB::transaction() -> Model -> AuditoriaService`

Para UC–07, complementar ese patrón con los Services congelados en DT-02, DT-03 y DT-04.

Convenciones generales:

- Controllers tradicionales.
- Form Requests para validación y autorización de entrada cuando corresponda.
- Actions para lógica de aplicación y operaciones de negocio.
- Services para capacidades reutilizables de dominio/aplicación que ya fueron justificadas.
- Policies para autorización sobre recursos y operaciones concretas.
- Models Eloquent propios por entidad.
- Reutilizar `AuditoriaService`.
- Utilizar `DB::transaction()` para escrituras que deban ser atómicas.
- Utilizar `lockForUpdate()` cuando concurrencia, transiciones de estado o reglas temporales puedan comprometer consistencia.
- Reutilizar `RelojAplicacion` donde el dominio requiera “ahora” o “hoy”.
- Reutilizar el sistema global existente de SweetAlert2 y `.js-confirm-action` cuando su contrato encaje.
- Reutilizar Alpine/Vite ya instalados; no agregar CDN duplicados.
- Reutilizar componentes Blade sólo cuando la reutilización esté demostrada.
- No crear abstracciones genéricas únicamente porque dos pantallas se parezcan.

No crear:

- Controllers CRUD genéricos para dominios con reglas diferentes.
- Models base genéricos sin necesidad demostrada.
- Repositorios artificiales sin necesidad demostrada.
- Una segunda infraestructura de auditoría.
- Una segunda infraestructura de confirmaciones.
- Una segunda regla paralela de elegibilidad profesional.
- Una segunda lógica de disponibilidad distinta entre “mostrar” y “confirmar”.
- Abstracciones que mezclen reglas diferentes sólo porque las pantallas se parecen.

## Contratos existentes de Agenda que UC–07 debe consumir

Las reglas de Agenda ya implementadas son dependencia de UC–07 y no deben reescribirse desde cero.

### Configuraciones de agenda

- Una kinesióloga puede tener múltiples configuraciones históricas a lo largo del tiempo.
- La vigencia se representa con `vigencia_desde` y `vigencia_hasta`.
- `vigencia_hasta` puede ser `NULL`.
- Las configuraciones anuladas permanecen para trazabilidad y no participan de disponibilidad efectiva.
- No persistir estados derivados de “vigente/futura/histórica” como columnas sólo para facilitar UI.
- La configuración aplicable se resuelve respecto de la fecha objetivo consultada.

### Franjas horarias

- Las franjas pertenecen a una configuración.
- Puede haber múltiples franjas por día.
- `hora_fin > hora_inicio`.
- No puede haber solapamientos dentro del mismo día/configuración.
- Se admiten franjas contiguas.
- Cada franja debe poder contener al menos un turno de la duración estándar.

### Duración estándar

- Pertenece a cada configuración.
- Entero entre 15 y 180 minutos.
- Múltiplo de 5.
- 45 minutos es una sugerencia histórica, no una constante universal.
- Cambios futuros de duración no reescriben turnos ya otorgados.

### Bloqueos

- Pertenecen directamente a la kinesióloga.
- Pueden atravesar períodos de configuraciones distintas.
- Pueden superponerse entre sí y se conservan como registros independientes.
- Un intervalo está bloqueado mientras exista al menos un bloqueo no anulado que lo intersecte.
- No editar/eliminar físicamente un bloqueo persistido como flujo ordinario; anular y crear otro cuando corresponda.
- Intervalos conceptualmente semiabiertos `[inicio_at, fin_at)`.
- No hardcodear timezone.

### Turnos y disponibilidad

- Los cambios de agenda o bloqueos no modifican automáticamente turnos ya otorgados.
- `turnos` conserva sus propios `inicio_at` y `fin_at`.
- No agregar FK desde `turnos` a `configuraciones_agenda` sólo para recordar el origen del slot.
- No persistir slots/cupos/disponibilidades.
- Disponibilidad = configuración aplicable + franjas + bloqueos efectivos + turnos ocupantes + restricciones temporales.

## Reglas específicas de módulos ya implementados

Las reglas siguientes documentan comportamientos existentes de Catálogos Maestros y Catálogos Clínicos. Se conservan como referencia y no deben trasladarse automáticamente a UC–07.

### Estado de catálogos

- Un registro nuevo nace Activo.
- `create/store` no permite elegir estado.
- `edit/update` no cambia estado lógico.
- Activo: Consultar / Editar / Inactivar.
- Inactivo: Consultar / Editar / Reactivar.
- Inactivar/Reactivar son operaciones explícitas.
- El cambio de estado usa estado objetivo; no invertir ciegamente con `!$activo`.
- Repetir una operación ya satisfecha es idempotente y no genera auditoría ficticia.
- No existe `destroy` como operación ordinaria de catálogos.
- No realizar eliminación física para implementar baja lógica.
- Preservar referencias históricas.

No aplicar estas reglas mediante reemplazos globales a módulos ajenos al UC. En particular, no modificar por este motivo:

- `UserController::toggleActivo`.
- `UserPolicy::toggleActivo`.
- cambio de estado de Matrículas Profesionales.

### Diagnósticos clínicos

- Tabla: `diagnosticos_clinicos`.
- Model: `DiagnosticoClinico`.
- No utilizar `diagnosticos_funcionales`.
- `sistema_origen`: `CIE-10`, `CIF`, `SNOMED`, `INTERNO`.
- UK: `(sistema_origen, codigo)`.

### Pruebas funcionales

- `sistema_origen`: `INTERNO`, `SNOMED`, `LOINC`.
- `unidad_medida` opcional.
- UK: `(sistema_origen, codigo)`.

### Técnicas terapéuticas

- `sistema_origen`: `INTERNO`, `SNOMED`.
- Los parámetros concretos de dosificación/aplicación no pertenecen al catálogo.
- UK: `(sistema_origen, codigo)`.

### Categorías clínicas

Tipos:

- `ANATOMICA`.
- `FUNCIONAL`.
- `TERAPEUTICA`.
- `EQUIPAMIENTO`.

Reglas:

- Una hija debe tener el mismo tipo que su padre.
- No autorreferencia.
- No ciclos.
- Una categoría activa no puede depender de padre inactivo.
- La inactivación es restrictiva si deja dependencias maestras activas inválidas.

### Ejercicios domiciliarios

- No existe `grupo_muscular_zona`.
- `codigo` obligatorio y único.
- `dificultad_base`: `BAJA`, `MEDIA`, `ALTA`.
- `frecuencia_periodo_base`: `DIARIA` o `SEMANAL` cuando se informa frecuencia.
- `frecuencia_cantidad_base` y `frecuencia_periodo_base` se informan conjuntamente.
- Un ejercicio activo requiere al menos una categoría activa `ANATOMICA`.
- Puede asociarse a `ANATOMICA`, `FUNCIONAL` o `TERAPEUTICA`.
- Nunca puede asociarse a `EQUIPAMIENTO`.

### Recursos de ejercicio

Campos relevantes:

- `ejercicio_id`.
- `tipo`.
- `origen`.
- `ruta_storage`.
- `url_externa`.
- `titulo`.
- `orden`.
- `activo`.

Dominios:

- `tipo`: `IMAGEN`, `VIDEO`, `DOCUMENTO`.
- `origen`: `LOCAL`, `EXTERNO`.

Reglas:

- LOCAL: `ruta_storage` obligatoria y `url_externa = NULL`.
- EXTERNO: `url_externa` obligatoria y `ruta_storage = NULL`.
- `ruta_storage` nunca se acepta como valor controlado por cliente.
- Un recurso persistido se identifica por `id`.
- Omitir un recurso persistido del request no significa borrarlo.
- Recurso persistido: inactivar/reactivar; no eliminar físicamente como flujo normal.
- Recurso nuevo no persistido: puede quitarse del formulario.
- Si se almacena un archivo nuevo y luego falla la transacción DB, limpiar el archivo nuevo.
- Si se reemplaza un archivo existente, no borrar el viejo antes de confirmar la transacción.

### Tipos de equipo terapéutico

- `categoria_clinica_id` debe apuntar a una categoría activa de tipo `EQUIPAMIENTO` para crear, cambiar asociación o reactivar.

## Auditoría

Reutilizar `app/Services/AuditoriaService.php`.

Reglas generales:

- Auditar operaciones de escritura relevantes conforme al módulo y al caso de uso.
- Mantener contratos existentes de auditoría en módulos ya implementados.
- No auditar lecturas salvo decisión funcional específica; UC–07 sí exige auditar descarga efectiva de documentos clínicos según DT-07.
- No generar auditoría ficticia para updates sin cambios semánticos o no-ops idempotentes.
- Nunca almacenar contraseñas, binarios u otros datos sensibles en snapshots.
- Para UC–07 no duplicar el contenido completo de `motivo_solicitud` ni archivos dentro de auditoría.
- No asumir que Turnos usa el módulo de auditoría `Catálogos`; definir/usar el módulo/eventos acordados por DT-07 y las convenciones reales de `AuditoriaService`.

## Base de datos y migraciones

- Agregar migraciones nuevas; no reescribir migraciones históricas ya ejecutadas.
- Preservar claves foráneas y conservación histórica.
- No usar cascadas destructivas incompatibles con conservación histórica.
- No usar native `ENUM` de MySQL para dominios que deban permanecer compatibles con SQLite de PHPUnit.
- Mantener compatibilidad entre MariaDB/MySQL de desarrollo y SQLite de tests cuando corresponda.
- No ejecutar comandos destructivos sobre la BD de desarrollo.

PROHIBIDO sin instrucción explícita del usuario:

- `php artisan migrate:fresh`.
- `php artisan migrate:reset`.
- `php artisan db:wipe`.
- equivalentes destructivos.

Si una tarea requiere ejecutar una migración real sobre MariaDB de desarrollo, detenerse y pedir confirmación antes de ejecutarla.

`migrate --pretend` puede utilizarse cuando el subpaso o planificación lo indiquen.

Para UC–07, el diseño físico congelado prevé únicamente, respecto de este incremento:

- modificación de `turnos` para `clave_idempotencia` nullable UNIQUE;
- índice `(paciente_id, tipo, estado, inicio_at)`;
- creación de `documentos_clinicos`;
- creación de `documentos_clinicos_origen_turno`.

No crear por anticipado:

- `documentos_clinicos_padecimiento`;
- tabla de slots;
- tabla de holds/reservas temporales;
- entidad persistente `SolicitudTurno`.

## Archivos y configuración protegidos

No modificar salvo instrucción explícita:

- `.env`.
- `vendor/`.
- `node_modules/`.
- dumps SQL.
- documentos UP congelados.
- Modelo Relacional congelado.
- Diccionario de Datos congelado.
- fuentes congeladas dentro de `docs/_codex_sources/`.
- `docs/archivo/`.

No incluir en Git:

- cachés;
- logs;
- temporales;
- uploads de pruebas;
- credenciales;
- `.env`;
- `docs/_codex_sources/`, que contiene fuentes locales de apoyo y está excluido mediante `.git/info/exclude`;
- `docs/archivo/`, salvo instrucción explícita del usuario.

No modificar `.gitignore` o `.git/info/exclude` para incorporar `docs/_codex_sources/` o `docs/archivo/` al repositorio salvo instrucción explícita.

## Pruebas

El proyecto usa PHPUnit, NO Pest.

Estrategia:

1. Ejecutar primero tests específicos del cambio.
2. Corregir únicamente fallos causados por el subpaso actual.
3. Ejecutar la regresión indicada por planificación/prompt.
4. No avanzar con tests rojos causados por el cambio actual.
5. Ejecutar `php artisan test` completo en las puertas de regresión definidas para UC–07.
6. Ejecutar Pint sobre los archivos modificados o el alcance explícitamente indicado; no reformatear masivamente archivos ajenos para intentar producir un Pint global verde.
7. Ejecutar `git diff --check` antes de cierres/checkpoints relevantes.
8. Ejecutar `npm run build` cuando el cambio afecte frontend o en puertas de regresión definidas.

Baseline de partida de UC–07:

`1376 passed (8842 assertions)`

Build de partida:

`npm run build`: PASS.

Limitación conocida:

- PHPUnit usa SQLite `:memory:`.
- Eso no certifica locking real de MariaDB.
- Las carreras de DT-05/DT-06 deben verificarse específicamente contra MariaDB/InnoDB después de implementar la Action de confirmación.

No modificar tests para “hacerlos pasar” si eso debilita o cambia una regla congelada.

Cuando un test deba actualizarse por un cambio funcional planificado, mantener o aumentar la cobertura semántica.

## Frontend

- Blade + Tailwind + Alpine + Vite.
- SweetAlert2 ya está disponible globalmente.
- No agregar imports/CDN duplicados.
- Mantener desktop y mobile conforme a las decisiones UX aprobadas y patrones visuales reales de KinesioSoft.
- Los prototipos visuales nunca prevalecen sobre D-01…D-16, DT-01…DT-07, Diccionario, UC o Modelo Relacional.
- Las vistas no deben contener reglas críticas que sólo existan en frontend.
- Toda regla crítica debe validarse también en backend.
- Evitar JavaScript inline cuando el proyecto ya usa módulos reutilizables y el cambio justifique extraerlos.
- No incorporar una librería frontend únicamente para reproducir un mock si Blade/Alpine/Tailwind pueden resolverlo correctamente.
- Los estados visuales derivados no justifican crear columnas persistentes.

### Formularios con archivos en UC–07

- Usar `multipart/form-data` / `FormData` real.
- Enviar `UploadedFile` real.
- No serializar binarios como Base64 para suplir el transporte HTTP.
- Recordar que un `<input type="file">` no puede repoblarse con `old()` tras recarga tradicional.
- El flujo previsto utiliza `fetch`/`FormData` para poder conservar archivos válidos en memoria del navegador ante errores 422 sin recargar la página.
- La validación frontend es auxiliar; la política DT-04 se valida siempre en backend.
- No utilizar disco público para documentos clínicos.

## Git

Antes de un bloque que modifique archivos:

- comprobar `git branch --show-current`;
- comprobar `git rev-parse HEAD` cuando el commit base sea relevante;
- comprobar `git status --short`.

Durante el trabajo:

- no hacer commits intermedios salvo instrucción explícita;
- no cambiar de rama por iniciativa propia;
- no hacer push;
- no reescribir historial;
- no usar `reset --hard`, `clean -fd` u otros comandos destructivos;
- no usar `git add .`;
- no usar `git add -A`;
- usar paths explícitos al stagear;
- no agregar `docs/archivo/` al staging salvo instrucción explícita;
- no agregar `docs/_codex_sources/` al repositorio.

Antes de cierre:

- mostrar `git status --short`;
- mostrar `git diff --stat`;
- revisar el diff requerido por el subpaso;
- utilizar `git diff --check` cuando corresponda;
- si hay staging, mostrar `git diff --cached --stat` y revisar sólo los paths autorizados.

No realizar commits por iniciativa propia.

El momento y alcance de cada commit serán indicados explícitamente por el usuario.

No hacer push salvo autorización explícita.

## Seguridad operacional

Si una acción:

- excede el subpaso solicitado;
- puede destruir datos;
- requiere credenciales;
- modifica configuración de entorno;
- cambia arquitectura congelada;
- afecta módulos no incluidos;
- reabre D-01…D-16 o DT-01…DT-07;
- o contradice una fuente congelada;

detenerse y solicitar autorización antes de continuar.

No interpretar una aprobación para ejecutar un comando como autorización general para comandos posteriores.

No ejecutar comandos destructivos o de limpieza para resolver un estado Git inesperado.

No eliminar archivos no trackeados cuya procedencia no haya sido autorizada.

## Alcance del incremento Turnos actual

El alcance activo corresponde exclusivamente a:

**UC–07 Solicitar turno**

Integraciones mínimas autorizadas:

- lectura de la configuración de Agenda existente;
- lectura de bloqueos efectivos;
- lectura de turnos que reservan agenda;
- elegibilidad de kinesiólogas mediante reglas canónicas;
- `Mis turnos` mínimo como entrada y destino posterior;
- documentos clínicos privados de origen turno;
- auditoría necesaria para creación/carga/descarga;
- cambios mínimos en `Turno` exigidos por idempotencia y D-01.

No implementar por anticipado:

- programación de seguimientos por kinesióloga;
- cancelación/reprogramación de turnos;
- confirmación de asistencia;
- ausencia;
- reasignación;
- ofertas de adelanto;
- aceptación/rechazo de ofertas;
- paciente provisorio;
- sobreturno/urgencia;
- reserva/uso de equipos;
- Consulta/Padecimiento/diagnóstico/evaluación/plan;
- validación clínica completa de documentos;
- `documentos_clinicos_padecimiento`;
- notificaciones automáticas de turno;
- cualquier otra funcionalidad no incluida en D-16.

Puede diseñarse una responsabilidad reutilizable cuando sea estrictamente necesaria para UC–07 y esté respaldada por el diseño congelado, pero no implementar flujos futuros por anticipado.

## Prototipos y diseño UX

El incremento se encuentra, al momento de esta actualización, listo para **Paso 5 — Diseño UX navegable**.

Reglas:

- No convertir una propuesta UX en regla de negocio sin aprobación.
- No modificar persistencia para imitar un prototipo.
- No introducir React u otro framework sólo porque una herramienta de prototipado genere ese código.
- Si posteriormente se utiliza una herramienta de IA para prototipar pantallas, el resultado será referencia visual y deberá adaptarse a Laravel + Blade + Alpine + Tailwind.
- El material histórico existente en `docs/_codex_sources/turnos/prototipo_deepseek/` no es automáticamente el prototipo de UC–07 ni autoriza copiar código o comportamiento.
- Si el usuario aprueba un prototipo específico para UC–07, registrar explícitamente cuál es antes de usarlo como referencia visual.

Un prototipo nunca puede utilizarse como fuente para:

- crear columnas o tablas no aprobadas;
- cambiar cardinalidades;
- persistir estados derivados;
- introducir relaciones nuevas;
- modificar D-01…D-16 o DT-01…DT-07;
- ampliar alcance;
- introducir frameworks/librerías externas sin autorización;
- copiar directamente HTML/CSS/JS sin adaptación al proyecto.

## Formato de cierre de cada subpaso

Al terminar, responder con:

1. **Subpaso ejecutado**.
2. **Archivos inspeccionados**.
3. **Archivos creados**.
4. **Archivos modificados**.
5. **Cambios realizados**.
6. **Pruebas/comandos ejecutados**.
7. **Resultado exacto**.
8. **Discrepancias o riesgos encontrados**.
9. **Estado de Git**.
10. **Confirmación explícita de que no se avanzó al subpaso siguiente**.

Detenerse después de ese informe.
