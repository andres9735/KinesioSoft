# UC–XX Configurar agenda de atención — Diseño técnico

## 1. Propósito y alcance

Este documento traduce las fuentes congeladas y el contrato UX AGENDA-01 a AGENDA-10 a un diseño implementable en Laravel 12. Define persistencia futura, responsabilidades, consultas, autorización, validación, transacciones, concurrencia, auditoría y pruebas, pero no implementa ninguna de ellas.

El alcance se limita a configuraciones temporales de agenda, sus franjas semanales, bloqueos excepcionales y la consulta de turnos afectados por un bloqueo. No incluye solicitar, confirmar, reprogramar o cancelar turnos; materializar slots; reservar equipos; ni habilitar actores administrativos.

Reglas rectoras:

- `vigencia_hasta` es inclusiva y puede ser `NULL`.
- Una primera configuración o una configuración creada durante un hueco real puede empezar hoy; nunca en el pasado.
- Una sucesora de una configuración vigente y un cambio de duración deben empezar estrictamente después de hoy.
- Una configuración con `vigencia_desde = hoy` ya está iniciada y no es editable ni anulable como futura.
- Los huecos son válidos; no se fuerza continuidad total.
- Configuraciones no anuladas de una kinesióloga no se superponen. Las anuladas no participan en disponibilidad ni en esa validación.
- Los bloqueos usan intervalos semiabiertos `[inicio_at, fin_at)`, pueden superponerse y nunca se fusionan.
- Los estados son derivados. No se persisten estados, slots, `tipo`, `todo_el_dia` ni una FK de turno a configuración.

## 2. Fuentes y jerarquía aplicada

Se aplicó, en este orden: Diccionario de Datos; UC–XX; Modelo Relacional; decisiones congeladas en `README_Codex_sources.md` y `AGENTS.md`; UX aprobado; casos de uso relacionados sólo como contexto; y código real como convención técnica.

El Diccionario define las tres tablas de agenda, sus relaciones e invariantes. El UC define los flujos A–F y las reconciliaciones. El Modelo Relacional confirma `Kinesiologa 1:N ConfiguracionAgenda`, `ConfiguracionAgenda 1:N FranjaHorariaAgenda`, `Kinesiologa 1:N BloqueoAgenda` y la independencia de `Turno`. El UX define páginas, navegación, revisión previa de bloqueos y contratos de presentación sin alterar el dominio.

No se detectó contradicción bloqueante entre esas fuentes. La decisión funcional complementaria de este diseño congela el dominio canónico de `turnos.estado` y limita la advertencia de nuevos bloqueos a turnos `PENDIENTE` y `CONFIRMADO`, aunque la persistencia compartida de Turno todavía no está implementada.

## 3. Estado actual del proyecto

El repositorio usa Laravel 12, PHP 8.2, Blade, Tailwind, Alpine, SweetAlert2, Spatie Permission y PHPUnit 11. Los tests usan SQLite en memoria. `config/app.php` fija `America/Argentina/Buenos_Aires`.

Hallazgos relevantes:

- Existen `Kinesiologa`, `User`, `Auditoria`, `AuditoriaService`, middleware `auth`, `verified`, `perfil.completo` y `perfil.kinesiologo`, Policies con ownership, Actions transaccionales y ejemplos de `lockForUpdate()`.
- Laravel descubre Policies por convención; `AppServiceProvider` no registra mappings manuales.
- Las escrituras siguen `Controller -> Form Request -> Action -> DB::transaction() -> Model -> AuditoriaService`.
- Las rutas profesionales operativas de matrículas usan actualmente `auth`, `perfil.completo` y `verified`. Las rutas separadas para completar el perfil profesional usan `auth` y `perfil.kinesiologo` porque deben permanecer accesibles antes de completar ese perfil. Para Agenda corresponde conservar `verified` y agregar la restricción explícita `perfil.kinesiologo` por tratarse de una funcionalidad operativa exclusiva de la kinesióloga.
- La paginación existente usa `paginate(10)` y `withQueryString()`.
- El layout contiene feedback global SweetAlert2 y `.js-confirm-action`. Las vistas existentes ofrecen patrón de tabla desktop/cards mobile, pero los componentes de Catálogos tienen semántica Activo/Inactivo y no deben reutilizarse para estados temporales.
- Sólo existe `UserFactory`; las factories de agenda deberán agregarse después.
- No existen actualmente migrations, Models, Controllers, Requests, Actions, Policies, rutas ni tests para `configuraciones_agenda`, `franjas_horarias_agenda`, `bloqueos_agenda` o `turnos`.
- `kinesiologas` y `auditorias` sí existen. Sus migrations históricas no deben reescribirse.

Patrones que se reutilizarían: Actions finales inyectando `AuditoriaService`, transacción que reconsulta y bloquea el estado actual, `Gate::authorize`, ownership por `user->kinesiologa`, Form Requests que normalizan entrada, redirect con flash, paginación y confirmación global. No se reutilizarían Controllers CRUD genéricos, badges/filtros booleanos de Catálogos, cambio ciego de estado, ni lógica temporal en Blade.

## 4. Arquitectura técnica propuesta

La solución mantiene Laravel tradicional:

```text
Route + middleware
  -> Controller fino + Policy
    -> Form Request (forma, tipos y feedback temprano)
      -> Action por intención
        -> transacción + lock de Kinesiologa + revalidación
          -> Models/relaciones + AuditoriaService

Lecturas
  -> AgendaQueryService + scopes temporales explícitos
    -> contrato de vista con estados/flags ya resueltos
```

Clases candidatas, sin crearlas aún:

- Models `ConfiguracionAgenda`, `FranjaHorariaAgenda`, `BloqueoAgenda`; `Turno` sólo cuando se defina e implemente su persistencia compartida.
- Enum PHP `DiaSemanaEnum:int`, con codificación ISO-8601 `1..7`, porque el UC fija exactamente lunes a domingo y el Diccionario no fija la codificación física. No se usa `ENUM` nativo.
- `AgendaQueryService` para lecturas que combinan configuraciones, franjas, bloqueos y turnos. No Repository Pattern.
- `ValidadorMatrizAgenda`, utilidad de dominio pura y pequeña, reutilizada por Requests y Actions para ordenar y comprobar la matriz.
- Actions específicas para crear, actualizar futura, cambiar duración, anular futura, registrar bloqueo y anular bloqueo.
- Excepciones de dominio pequeñas para carreras o pérdida de elegibilidad; errores de campos siguen usando `ValidationException`.

## 5. Mapa de responsabilidades

| Capa | Sí hace | No hace |
|---|---|---|
| Middleware | Autenticación, email verificado, perfil completo y rol kinesióloga | Ownership de un registro concreto |
| Policy | Actor permitido, ownership y elegibilidad visible | Garantizar invariantes frente a carreras |
| Form Request | Normalizar payload, tipos, obligatoriedad, rangos y validación cruzada temprana | Confiar en datos del cliente para `kinesiologa_id`; decidir solapamientos concurrentes |
| Controller | Autorizar, invocar consulta/Action, seleccionar vista o redirect | Reconciliar vigencias o escribir relaciones |
| Action | Transacción, locks, revalidación, writes atómicos y auditoría | Renderizar o decidir layout |
| Model | Relaciones, casts, scopes con semántica estable y métodos derivados con fecha explícita | Orquestar casos de uso o leer el reloj implícitamente |
| AgendaQueryService | Componer lecturas para dashboard/detalles, estado derivado y consultas temporales | Escribir o abrir transacciones de negocio |
| Blade/Alpine | Presentar datos, edición de matriz y feedback inmediato | Ser la única defensa de una regla crítica |

## 6. Persistencia y migrations futuras

### Inventario de persistencia

| Entidad | Estado actual | Migration futura | Model | Observaciones |
|---|---|---|---|---|
| `kinesiologas` | Existe | No modificar | `Kinesiologa` existente | Fila ancla para serializar todas las mutaciones de una profesional. Añadir relaciones en el Model, no columnas. |
| `configuraciones_agenda` | No existe | `create_configuraciones_agenda_table` | `ConfiguracionAgenda` | Sin UK por kinesióloga ni por fecha; overlap transaccional. |
| `franjas_horarias_agenda` | No existe | `create_franjas_horarias_agenda_table` | `FranjaHorariaAgenda` | N filas por configuración; sin máximo artificial. |
| `bloqueos_agenda` | No existe | `create_bloqueos_agenda_table` | `BloqueoAgenda` | FK directa a kinesióloga; admite intervalos superpuestos. |
| `turnos` | No existe | Dependencia compartida aún no implementada | `Turno` futuro | No agregar `configuracion_agenda_id`. Estados canónicos congelados: `PENDIENTE`, `CONFIRMADO`, `EN_ATENCION`, `ATENDIDO`, `AUSENTE`, `CANCELADO`. |
| `auditorias` | Existe y alineada | No modificar | `Auditoria` existente | Reutilizar `AuditoriaService`; no crear infraestructura paralela. |

Migrations futuras de este UC:

1. `create_configuraciones_agenda_table`: `id`; `kinesiologa_id` FK no nula; `vigencia_desde` `date`; `vigencia_hasta` `date` nullable; `duracion_turno_minutos` unsigned small integer; `anulado_at` `dateTime` nullable; `motivo_anulacion` `string(500)` nullable; timestamps; índice `(kinesiologa_id, anulado_at, vigencia_desde, vigencia_hasta)`. FK con `restrictOnDelete()` y `restrictOnUpdate()` para preservar historia. Sin UK ni columna `estado`.
2. `create_franjas_horarias_agenda_table`: `id`; `configuracion_agenda_id` FK no nula y restrictiva; `dia_semana` unsigned tiny integer; `hora_inicio` y `hora_fin` `time`; timestamps; índice `(configuracion_agenda_id, dia_semana)`. No UK: los solapamientos se validan como conjunto.
3. `create_bloqueos_agenda_table`: `id`; `kinesiologa_id` FK no nula y restrictiva; `inicio_at`, `fin_at` `dateTime`; `motivo` `string(500)`; `anulado_at` `dateTime` nullable; `motivo_anulacion` `string(500)` nullable; timestamps; índice `(kinesiologa_id, anulado_at, inicio_at, fin_at)`.

Las longitudes 500 son una decisión técnica para motivos libres y no agregan un atributo de dominio. Requests y columnas deben compartir el mismo límite. No se agregan checks dependientes de un motor: rangos, coherencia temporal y matrices se validan en aplicación, manteniendo compatibilidad MySQL/SQLite. Las FKs e índices sí son portables.

`turnos` requiere su propia migration futura compartida, basada en todo el Diccionario, no una tabla parcial creada ad hoc por Agenda. Cuando se implemente deberá incluir como mínimo un índice útil para solapamiento temporal por profesional, por ejemplo `(kinesiologa_id, inicio_at, fin_at)`, y nunca una FK a configuración. `estado` se almacenará sin `ENUM` nativo y se validará/casteará mediante un futuro Enum PHP con exactamente `PENDIENTE`, `CONFIRMADO`, `EN_ATENCION`, `ATENDIDO`, `AUSENTE` y `CANCELADO`.

## 7. Models y relaciones

### Models

| Model | Tabla | Relaciones | Casts principales | Responsabilidad |
|---|---|---|---|---|
| `Kinesiologa` | `kinesiologas` | `hasMany` configuraciones, bloqueos y turnos futuros | Existentes | Raíz de ownership y recurso lógico de serialización. |
| `ConfiguracionAgenda` | `configuraciones_agenda` | `belongsTo Kinesiologa`; `hasMany FranjaHorariaAgenda` | `vigencia_desde/vigencia_hasta: immutable_date`, `duracion_turno_minutos: integer`, `anulado_at: immutable_datetime` | Relación, scopes temporales y métodos derivados con fecha explícita. |
| `FranjaHorariaAgenda` | `franjas_horarias_agenda` | `belongsTo ConfiguracionAgenda` | `dia_semana: DiaSemanaEnum`; horas conservadas canónicamente como `H:i:s` | Representar una franja persistida; no validar la matriz completa por sí sola. |
| `BloqueoAgenda` | `bloqueos_agenda` | `belongsTo Kinesiologa` | `inicio_at/fin_at/anulado_at: immutable_datetime` | Intervalo original, anulación y métodos derivados con instante explícito. |
| `Turno` futuro | `turnos` | `belongsTo Kinesiologa`, `belongsTo Paciente` | Datetimes y futuro Enum PHP con los seis estados canónicos | Fuente de lectura para advertencias; este UC sólo considera afectados `PENDIENTE` y `CONFIRMADO` y no modifica turnos. |

Los Models seguirán el patrón `$fillable`, pero las Actions construirán arrays permitidos y nunca harán mass assignment del request completo. `kinesiologa_id`, `anulado_at` y motivos de anulación se derivan o asignan en Actions, no se confían al cliente.

Scopes claros: `noAnuladas()`, `aplicablesEn(fecha)`, `futurasDesde(hoy)`, `historicasEn(hoy)` y `superpuestosCon(inicio, fin)` para bloqueos. Todo scope temporal recibe fecha/instante: ningún global scope ni accessor consulta `today()` silenciosamente. Métodos `estadoEn($hoy)` y `puedeEditarseEn($hoy)` son puros. “Próxima” se calcula en la consulta como la futura no anulada con menor inicio.

No se sobrecarga `delete()` en los Models. La ausencia de rutas/Actions de borrado, Policies sin `delete` y FKs restrictivas prohíben el borrado ordinario. Las franjas de una configuración futura sí pueden reemplazarse físicamente dentro de su actualización atómica; no constituyen historia autónoma una vez reemplazadas antes del inicio.

## 8. Representación temporal y estados derivados

Una configuración no anulada aplica a `F` cuando:

```text
vigencia_desde <= F
AND (vigencia_hasta IS NULL OR vigencia_hasta >= F)
```

La semántica se centraliza en scopes/métodos del Model y se compone en `AgendaQueryService`. Estados respecto de un `$hoy` explícito:

- Anulada: `anulado_at != NULL`, con precedencia visual sobre cualquier fecha.
- Futura: no anulada y `vigencia_desde > hoy`.
- Vigente: no anulada, inició y no finalizó respecto de hoy.
- Histórica: no anulada y `vigencia_hasta < hoy`.
- Próxima: marca secundaria de la primera futura, nunca estado persistido.

Bloqueos respecto de `$ahora`: Anulado si tiene `anulado_at`; Próximo si `inicio_at > ahora`; En curso si `inicio_at <= ahora < fin_at`; Pasado si `fin_at <= ahora`. El borde `fin_at` ya no pertenece al bloqueo.

Las fechas se manejan como `CarbonImmutable`; no se mutan objetos compartidos al sumar/restar días. Las horas semanales se comparan como minutos desde medianoche, evitando asociarlas artificialmente a una fecha o zona.

## 9. Resolución de configuración aplicable

La fecha objetivo siempre es argumento. No existe “la última configuración” como sustituto de la aplicable.

```text
func configuraciónAplicable(kinesiologaId, F):
    exigir F como fecha local, sin componente horario
    consultar configuraciones de kinesiologaId
      donde anulado_at es NULL
      y vigencia_desde <= F
      y (vigencia_hasta es NULL o vigencia_hasta >= F)
      ordenar por vigencia_desde descendente, id descendente
    resultado = primera fila con franjas ordenadas
    si hay más de una fila, lanzar error de integridad (no elegir silenciosamente)
    devolver resultado o null para representar hueco
```

El orden sólo hace determinista la lectura ante datos corruptos; la cardinalidad mayor a uno debe observarse y corregirse. “Vigente hoy” invoca esta consulta con `hoy` de la zona configurada. La disponibilidad futura invoca la misma consulta con la fecha consultada.

## 10. Regla de no solapamiento

Para candidato inclusivo `[N.desde, N.hasta]`, donde `NULL` equivale a infinito, existe overlap con `E` si:

```text
E.vigencia_desde <= (N.vigencia_hasta o +infinito)
AND (E.vigencia_hasta es NULL OR E.vigencia_hasta >= N.vigencia_desde)
```

La consulta restringe la misma kinesióloga, `anulado_at IS NULL` y, al editar, `id != candidato.id`. En SQL/Eloquent se evita representar infinito: si el fin candidato es nulo no se agrega el límite sobre `E.vigencia_desde`; siempre se exige que el fin existente sea nulo o alcance el inicio candidato.

Hay dos niveles:

1. Request/consulta sin lock para feedback temprano.
2. Action dentro de transacción: bloquea primero `kinesiologas.id`, luego todas las configuraciones de esa kinesióloga ordenadas por `id`, recalcula vecinos y overlap, y recién escribe.

El lock de kinesióloga serializa incluso dos primeras altas cuando aún no hay filas de configuración. Bloquear sólo configuraciones existentes no cubre ese caso y los gap locks dependerían del motor/índice y no serían equivalentes en SQLite.

```text
func hayOverlap(candidato, excluirId = null, ajustesPlaneados = {}):
    tomar conjunto bloqueado de configuraciones no anuladas de la kinesióloga
    excluir excluirId
    proyectar en memoria sólo los cambios de fin autorizados en ajustesPlaneados
    para cada existente:
        si existente.desde <= candidato.fin_o_infinito
           y existente.fin_o_infinito >= candidato.desde:
             devolver existente conflictiva
    devolver null
```

Los ajustes planeados sólo incluyen reconciliaciones expresamente autorizadas; nunca se ignora un overlap para “arreglarlo después”.

## 11. Franjas horarias y validación de matriz

Payload canónico:

```text
franjas: [
  { dia_semana: 1, hora_inicio: "08:00", hora_fin: "12:00" },
  { dia_semana: 1, hora_inicio: "12:00", hora_fin: "16:00" }
]
```

El Request exige un array no vacío, sin máximo funcional; cada día pertenece a `DiaSemanaEnum`; ambas horas tienen formato `H:i`; inicio < fin. `prepareForValidation` elimina filas visuales completamente vacías, pero no oculta filas parciales: éstas generan error. `ValidadorMatrizAgenda` normaliza segundos, ordena por día/inicio/fin y detecta duplicados/overlap.

```text
func validarMatriz(franjas, duración):
    exigir al menos una franja
    normalizar y ordenar por (día, inicio, fin)
    para cada franja:
        exigir inicio < fin
        exigir (finMinutos - inicioMinutos) >= duración
    para cada par consecutivo del mismo día:
        si actual.inicio < anterior.fin: error de solapamiento
        si actual.inicio == anterior.fin: válido (contiguas)
    devolver matriz canónica
```

No se exige que la duración de la franja sea múltiplo de la duración del turno. El Request produce errores indexados por día/fila. La Action repite la validación de la matriz canónica antes de persistir. Alta: configura cabecera y crea franjas en una transacción. Edición futura: compara la matriz canónica; si cambió, elimina sólo sus franjas aún futuras y crea el conjunto nuevo dentro de la misma transacción.

## 12. Crear primera configuración

`StoreConfiguracionAgendaRequest` valida inicio no pasado, fin inclusivo no anterior, duración entera `15..180` y múltiplo de 5, y matriz. No recibe `kinesiologa_id`.

`CrearConfiguracionAgendaAction` decide bajo lock si el caso es primera configuración, hueco real o sucesora. Permite `vigencia_desde = hoy` únicamente si no hay configuración aplicable hoy; si hay vigente, exige `> hoy`. También permite una fecha futura sin predecesora aplicable, preservando huecos.

Transacción: lock de kinesióloga; lock ordenado de configuraciones; recalcular hoy; revalidar temporalidad, período, overlap y matriz; crear configuración y franjas; registrar `configuracion_agenda_creada` en módulo `Agenda`; commit. Cualquier excepción revierte cabecera, franjas y auditoría. Postcondición: una configuración completa; si comienza hoy queda inmediatamente iniciada.

Los valores 45 y lunes a viernes 08:00–17:00 son sólo defaults de AGENDA-02 y nunca llegan como regla implícita a la Action.

## 13. Crear configuración sucesora

Predecesora `A`: no anulada con mayor `vigencia_desde < B.vigencia_desde`. Sucesora posterior `C`: no anulada con menor `vigencia_desde > B.vigencia_desde`. Ambas se obtienen del conjunto bloqueado, no mediante lecturas separadas sin orden.

Si existe una configuración vigente hoy, `B.vigencia_desde > hoy`. Si `A` termina antes de `B.desde - 1`, se conserva el hueco. Si `A` alcanza o rebasa `B.desde` —incluido fin nulo— puede cerrarse prospectivamente en `B.desde - 1`, siempre que ese nuevo fin no sea anterior a `A.desde`. No se cambian duración ni franjas de A. `C` nunca se mueve: el período ingresado para B debe finalizar antes de C; si B tiene fin nulo y existe C, AGENDA-02 debe pedir un fin compatible.

```text
BEGIN
  bloquear Kinesiologa propia
  bloquear todas sus configuraciones por id ascendente
  fijar hoy/ahora una sola vez desde reloj de aplicación
  revalidar B.desde >= hoy y, si hay vigente hoy, B.desde > hoy
  validar B.fin nulo o B.fin >= B.desde; validar duración y matriz
  localizar A y C
  ajusteA = ninguno
  si A existe y (A.fin es nulo o A.fin >= B.desde):
      nuevoFinA = B.desde - 1 día
      exigir nuevoFinA >= A.desde y que el ajuste no cambie días pasados
      ajusteA = nuevoFinA
  revalidar overlap proyectando ajusteA; C y las demás siguen intactas
  si conflicto: ROLLBACK con error de período
  aplicar ajusteA sólo si cambia realmente
  crear B y sus franjas canónicas
  auditar B como configuracion_agenda_creada
  si A cambió, auditar A como configuracion_vigencia_reconciliada
COMMIT
ante cualquier error: ROLLBACK
```

## 14. Editar configuración futura

`UpdateConfiguracionAgendaRequest` comparte las reglas de estructura de creación y recibe un token opaco de snapshot semántico firmado por el servidor. El snapshot mostrado en AGENDA-03 contiene como mínimo id, `vigencia_desde`, `vigencia_hasta`, `duracion_turno_minutos`, `anulado_at`, `motivo_anulacion` y la matriz completa, ordenada canónicamente por día, inicio, fin e id; cada franja aporta id, día, inicio y fin. La Action reconsulta bajo lock, reconstruye ese snapshot, verifica la firma y la igualdad semántica, y exige además `anulado_at IS NULL` y `vigencia_desde > hoy`. Excluye su id del overlap.

Para no perder la intención de huecos, se determina antes del cambio si la predecesora original estaba encadenada a B (`A.fin = B.desde - 1`). Conceptualmente se retira B de la cadena: sólo si existía ese vínculo se restaura prospectivamente A hasta `C.desde - 1` o `NULL`; un hueco anterior deliberado no se rellena. Luego se intenta insertar el período editado: su nueva predecesora puede acortarse hasta el día anterior sólo si alcanza el nuevo inicio; la sucesora nunca se mueve y limita el fin de B.

```text
BEGIN
  bloquear Kinesiologa; bloquear configuraciones por id
  recargar B con todas sus franjas ordenadas canónicamente
  reconstruir snapshot actual y verificar token firmado del estado mostrado
  si la firma es inválida o el snapshot no coincide: ROLLBACK sin escribir, refrescar y exigir nueva confirmación
  exigir B no anulada y B.desde > hoy
  snapshot anterior = cabecera + matriz canónica
  localizar vecinos originales y detectar vínculo estructural A->B
  proyectar retirada de B y reconciliar A sólo si estaba vinculada
  localizar vecinos del período propuesto
  validar elegibilidad, período, matriz y overlap excluyendo B
  aplicar sólo reconciliaciones prospectivas autorizadas
  actualizar B; reemplazar franjas sólo si la matriz cambió
  si snapshot semántico no cambió y tampoco vecinos: COMMIT sin auditoría
  auditar B como configuracion_futura_actualizada
  auditar cada vecino realmente reconciliado
COMMIT; cualquier fallo implica ROLLBACK
```

Una configuración que alcanzó hoy entre GET y POST se rechaza sin writes. No se edita una configuración iniciada aunque el payload fuese idéntico.

## 15. Cambiar duración estándar

AGENDA-04 siempre crea una configuración nueva y exige `vigencia_desde > hoy`. No existe cambio intradía porque la vigencia tiene granularidad DATE.

La matriz de origen es la configuración no anulada aplicable a `vigencia_desde - 1 día`. Si no existe, no se copia una histórica remota: se deriva a AGENDA-02. La copia es un payload nuevo; el usuario puede corregir franjas incompatibles. Cada franja se revalida contra la nueva duración. AGENDA-04 incluye un token firmado del snapshot semántico completo de la configuración de origen y su matriz, con la misma composición y orden canónico definidos para AGENDA-03.

Si existe una configuración posterior C, la nueva configuración de duración se limita de forma derivada a `C.vigencia_desde - 1 día`, dato que la vista previa debe mostrar; C no se modifica. La predecesora aplicable se cierra en `nueva.vigencia_desde - 1 día`. Si el período calculado no es válido o existe cualquier otra configuración intermedia, se rechaza.

```text
BEGIN
  bloquear Kinesiologa; bloquear configuraciones por id
  fijar hoy; exigir fechaEfecto > hoy
  origen = configuración aplicable a fechaEfecto - 1 día
  exigir origen existente y no anulada
  reconstruir snapshot completo de origen y verificar el token firmado mostrado en GET
  si no coincide: ROLLBACK sin escribir, refrescar origen/matriz y exigir nueva confirmación
  matriz = payload corregido o copia canónica de origen
  validar nueva duración 15..180 múltiplo de 5 y matriz completa
  C = primera no anulada posterior a fechaEfecto
  nuevoFin = C ? C.desde - 1 día : NULL
  cerrar origen en fechaEfecto - 1 día sólo si la alcanza
  revalidar ausencia de overlap con la proyección completa
  crear nueva configuración y nuevas franjas; nunca actualizar duración/franjas de origen
  auditar duracion_agenda_programada sobre la nueva configuración
  auditar reconciliación de origen si cambió su fin
COMMIT; ante error ROLLBACK
```

## 16. Anular configuración futura

`AnularConfiguracionAgendaRequest` exige `motivo_anulacion` normalizado, no vacío, máximo 500, y el token firmado del snapshot semántico completo mostrado en AGENDA-10. La Policy comprueba ownership; la Action determina elegibilidad definitiva.

Al excluir B del conjunto no anulado, A es la anterior más cercana y C la posterior más cercana. A sólo se extiende si fue finalizada para dar paso a B, evidenciado por `A.vigencia_hasta = B.vigencia_desde - 1 día`. Si A terminaba antes, el hueco era deliberado y se conserva. Sin A queda hueco. Con vínculo A->B: A termina en `C.desde - 1 día` si C existe, o queda abierta si no existe. No se alteran duración/franjas de A, C, turnos ni historia pasada.

```text
BEGIN
  bloquear Kinesiologa; bloquear todas sus configuraciones por id
  recargar B con sus franjas; reconstruir snapshot canónico y verificar el token firmado
  si no coincide: ROLLBACK sin escribir, refrescar la proyección A/B/C y exigir nueva confirmación
  exigir B.anulado_at NULL y B.desde > hoy
  localizar A y C excluyendo B
  vinculada = A existe y A.fin == B.desde - 1 día
  si vinculada:
      finPropuestoA = C ? C.desde - 1 día : NULL
      revalidar que A no se superpone con C ni altera pasado
  marcar B.anulado_at = ahora y guardar motivo; conservar período/matriz
  si vinculada y A cambia, actualizar sólo A.vigencia_hasta
  auditar B como configuracion_futura_anulada
  auditar A como configuracion_vigencia_reconciliada si cambió
COMMIT; ante carrera, estado inválido o conflicto: ROLLBACK
```

Una segunda anulación no es no-op legítimo: se rechaza como recurso ya anulado y no audita.

## 17. Reconciliación temporal entre configuraciones

Las reconciliaciones se limitan a `vigencia_hasta` de una predecesora y son prospectivas. Se reconoce un vínculo automático por adyacencia exacta con el registro que lo sucedió. Esa regla evita rellenar huecos permitidos.

| Operación | Predecesora | Registro operado | Sucesora posterior |
|---|---|---|---|
| Crear sucesora | Acortar sólo si alcanza el nuevo inicio | Crear completo | No modificar; obliga a fin compatible |
| Cambiar duración | Cerrar al día anterior | Crear copia con nueva duración | No modificar; limita automáticamente el nuevo fin |
| Editar futura | Restaurar/reencadenar sólo si había vínculo | Actualizar futura y matriz | No modificar |
| Anular futura | Extender sólo si había vínculo | Marcar anulada | No modificar; limita extensión de A |

Nunca se extiende una configuración sobre otra, se cambia su `vigencia_desde`, duración o franjas, ni se garantiza continuidad total. Toda proyección se revalida contra el conjunto bloqueado antes de escribir.

## 18. Registrar bloqueo

AGENDA-07 tiene dos POST conceptuales: revisar (sin escritura) y confirmar. Ambos usan el mismo normalizador. Después de normalizar exige `inicio_at < fin_at` y, exclusivamente para crear un bloqueo nuevo, `fin_at > ahora`. Por tanto admite un bloqueo futuro o actualmente en curso (`inicio_at <= ahora < fin_at`) y rechaza uno completamente histórico (`fin_at <= ahora`).

La revisión calcula advertencias y emite un token opaco firmado/autenticado por el servidor. Representa canónicamente el payload del nuevo bloqueo; cada turno afectado mediante id, estado, inicio y fin; y cada bloqueo superpuesto mediante id, inicio, fin, `anulado_at` y, si se muestra, motivo. No se confía en un hash libre enviado por el cliente ni en `updated_at` como prueba única de estabilidad.

La confirmación vuelve a consultar dentro de transacción. Si cambió el fingerprint, no registra: devuelve la revisión actualizada y exige reconfirmación. La presencia estable de advertencias no bloquea el alta.

```text
BEGIN
  validar token de revisión y token anti doble-submit
  bloquear Kinesiologa propia
  fijar ahora; normalizar otra vez inicio, fin y motivo
  exigir inicio < fin y fin > ahora
  bloquear turnos actualmente afectados por id ascendente
  bloquear bloqueos no anulados superpuestos por id ascendente
  reconstruir snapshots semánticos y el fingerprint canónico de revisión
  verificar firma y comparar con el token revisado
  si difiere: ROLLBACK y volver a revisión con advertencias nuevas; exigir otra confirmación
  crear BloqueoAgenda independiente (aunque haya overlaps)
  auditar bloqueo_agenda_registrado con intervalo, motivo y conteos informativos
COMMIT
  consumir token de submit y aplicar Post/Redirect/Get
ante error: ROLLBACK; conservar captura/revisión
```

El lock de kinesióloga es un contrato que también deberán respetar futuras escrituras de turnos de esa profesional; sin esa cooperación no puede garantizarse que una inserción de turno inexistente al consultar no aparezca durante la confirmación.

## 19. Detectar turnos afectados

El dominio canónico futuro de `turnos.estado` queda congelado en: `PENDIENTE`, `CONFIRMADO`, `EN_ATENCION`, `ATENDIDO`, `AUSENTE` y `CANCELADO`. Para la advertencia al crear un bloqueo se usa exclusivamente:

```text
estadosOcupantesParaAdvertenciaBloqueo = [PENDIENTE, CONFIRMADO]
```

La consulta conceptual es:

```text
func turnosAfectados(kinesiologaId, [inicio, fin)):
    exigir inicio < fin
    consultar turnos de la misma kinesióloga
      donde inicio_at < fin
      y fin_at > inicio
      y estado pertenece a estadosOcupantesParaAdvertenciaBloqueo
      ordenar por inicio_at, id
    devolver snapshot canónico de id, estado, inicio_at y fin_at
```

La condición detecta cualquier intersección semiabierta; los intervalos contiguos no colisionan. Para AGENDA-07 se muestra existencia, cantidad y fecha/horario local. El snapshot semántico completo integra el token firmado de revisión, pero no necesita exponerse visualmente. No se modifica ningún turno.

`PENDIENTE` y `CONFIRMADO` representan citas todavía programadas que pueden requerir gestión manual. Se excluyen `EN_ATENCION`, porque la atención ya comenzó, y `ATENDIDO`, `AUSENTE` y `CANCELADO`, porque no son citas futuras pendientes de reprogramación por este bloqueo. “Reprogramado” no es un estado: reprogramar cambia las coordenadas temporales y conserva el estado lógico correspondiente. “Realizado” no se introduce como sinónimo; el estado canónico de una atención completada es `ATENDIDO`.

La tabla/modelo `Turno` todavía no existe. Esto mantiene una dependencia técnica de persistencia para ejecutar la consulta real, pero ya no existe una decisión funcional pendiente sobre estados.

## 20. Detectar bloqueos superpuestos

```text
func bloqueosSuperpuestos(kinesiologaId, [inicio, fin), excluirId = null):
    consultar bloqueos de la misma kinesióloga
      donde anulado_at es NULL
      y inicio_at < fin
      y fin_at > inicio
      y, si corresponde, id != excluirId
      ordenar por inicio_at, id
    devolver snapshot canónico de id, inicio_at, fin_at, anulado_at y motivo mostrado
```

AGENDA-07 recibe cantidad y lista. AGENDA-08/09 reciben además el resumen de otros bloqueos aún aplicables al intervalo del bloqueo consultado, sin afirmar disponibilidad. La consulta nunca fusiona, elimina ni impide crear.

## 21. Anular bloqueo

`AnularBloqueoAgendaRequest` exige motivo máximo 500 y un token opaco firmado del snapshot semántico mostrado en AGENDA-09. Ese snapshot contiene como mínimo id, `inicio_at`, `fin_at`, motivo, `anulado_at` y `motivo_anulacion`. La Action no acepta cambios de intervalo ni motivo original. La regla `fin_at > ahora` no se aplica a esta anulación: un bloqueo pasado ya registrado continúa siendo consultable y anulable mientras no haya sido anulado.

```text
BEGIN
  bloquear Kinesiologa propia
  recargar y bloquear Bloqueo por id
  comprobar ownership; reconstruir snapshot actual y verificar el token firmado
  si no coincide: ROLLBACK sin escribir y mostrar el estado actualizado
  si anulado_at no es NULL: ROLLBACK con bloqueo ya anulado
  consultar otros bloqueos no anulados superpuestos para el resultado informativo
  asignar anulado_at = ahora y motivo_anulacion; no tocar inicio, fin ni motivo
  auditar bloqueo_agenda_anulado con antes/después
COMMIT y comunicar si otros bloqueos siguen afectando
ante carrera o error: ROLLBACK sin segunda auditoría
```

La segunda anulación es inválida. No se comunica que el intervalo quedó disponible; sólo que este registro dejó de restringirlo.

## 22. Normalización de bloqueos de día completo y parciales

`BloqueoAgendaRequestBase` puede normalizar a un payload interno `{inicio_at, fin_at, motivo}`; el modo UX no se pasa a la Action como dato persistible.

- Día completo único: inicio local a 00:00; fin local a 00:00 del día siguiente.
- Rango completo inclusivo: inicio a 00:00 del primer día; fin a 00:00 del día posterior a la última fecha.
- Parcial: combinar cada fecha/hora una sola vez; un rango multidiario es continuo.
- Nunca usar `23:59:59`.

La normalización ocurre antes de evaluar la regla de creación `fin_at > ahora`. Así, “Todo el día de hoy” se convierte en `[hoy 00:00, mañana 00:00)` y sigue siendo válido durante el día. La Action recibe instantes ya normalizados, pero vuelve a exigir que ambos sean válidos en la zona configurada, `inicio < fin` y, al crear, `fin > ahora`.

Al consultar sólo puede inferirse razonablemente “días completos” si ambos extremos están a medianoche y el fin es posterior por días enteros. Esa inferencia describe el intervalo almacenado, no prueba qué modo eligió originalmente la persona. En cualquier otro caso se presenta como intervalo continuo.

## 23. Zona horaria y reloj del sistema

La zona real es `config('app.timezone') = America/Argentina/Buenos_Aires`. Ninguna clase o vista debe repetir ese literal.

- “Hoy” se obtiene una vez por operación con un reloj de aplicación basado en `CarbonImmutable::now(config('app.timezone'))->toDateString()`.
- “Ahora” se fija una vez al entrar a la transacción para evitar cruces internos de medianoche.
- Inputs se interpretan como fecha/hora civil de la zona configurada y se rechazan si el parseo estricto falla.
- Dado que el proyecto usa DATETIME y su zona de aplicación es local, Agenda persiste sus DATETIME en esa convención local y los muestra en la misma zona. No se usa la zona del navegador ni la del sistema operativo como fuente de verdad.
- Antes de producción debe verificarse que la sesión de MySQL y los timestamps generados por la aplicación mantengan esa convención; Eloquent debe escribir `anulado_at` y timestamps desde el reloj de aplicación, no depender de una zona implícita del servidor.

No se modifica configuración de entorno. Un `RelojSistema` mínimo inyectable sería útil sólo si evita llamadas dispersas a `now()`; alternativamente, pasar `$hoy/$ahora` explícitos desde Actions y Query Service es suficiente y más simple. La recomendación inicial es una clase pequeña `RelojAplicacion` para uniformidad y tests.

## 24. Autorización, middleware y Policies

Grupo de rutas: `auth`, `verified`, `perfil.completo`, `perfil.kinesiologo`. Se mantiene `verified` porque el grupo real de matrículas profesionales —la funcionalidad operativa exclusiva de kinesióloga más equivalente— usa `auth`, `perfil.completo` y `verified` en `routes/web.php`. Las rutas de completar perfil usan separadamente `auth` y `perfil.kinesiologo`, ya que deben permitir completar la precondición. `bootstrap/app.php` confirma los aliases `perfil.completo` y `perfil.kinesiologo`; el diseño de Agenda combina ambos con el contrato operativo verificado. El middleware excluye roles incorrectos y perfiles incompletos; las Policies excluyen además recursos ajenos.

`ConfiguracionAgendaPolicy`: `viewAny`, `create`, `cambiarDuracion` para kinesióloga autenticada con perfil; `view` exige ownership; `update` y `anular` exigen ownership y pueden ocultar botones según estado actual. `BloqueoAgendaPolicy`: `viewAny`, `create`; `view` y `anular` con ownership. ADMINISTRADOR y SUPERADMIN no reciben excepción.

La elegibilidad temporal de una Policy sirve para autorización/presentación, pero la Action la revalida bajo lock. El `kinesiologa_id` siempre se obtiene de `request->user()->kinesiologa`; un id enviado por cliente se ignora o se prohíbe. Route model binding más Policy impide manipular otra agenda; puede usarse binding scoped o devolver 404 tras fallar el scope, sin filtrar datos.

## 25. Form Requests

### Requests

| Request propuesto | Operación | Validación estructural | Validación transaccional |
|---|---|---|---|
| `StoreConfiguracionAgendaRequest` | AGENDA-02 | Fechas, duración, matriz completa | Contexto primera/hueco/sucesora, overlap y ajustes vecinos |
| `UpdateConfiguracionAgendaRequest` | AGENDA-03 | Igual a store + token de snapshot firmado | Snapshot actual, futura/no anulada, overlap excluyendo id, reconciliación |
| `StoreCambioDuracionAgendaRequest` | AGENDA-04 | Fecha `> hoy`, duración, matriz y token de snapshot de origen | Snapshot/origen aplicable, vecinos, overlap y cierre prospectivo |
| `BloqueoAgendaRequestBase` | Base de revisión/alta | Modo UX, campos condicionales, motivo, parseo/normalización, `inicio < fin` y `fin > ahora` tras normalizar | Confirmación repite reloj y reglas dentro de transacción |
| `RevisarBloqueoAgendaRequest` | AGENDA-07 revisión | Hereda base | Consultas actuales sin garantía de commit |
| `StoreBloqueoAgendaRequest` | AGENDA-07 confirmación | Hereda base + tokens | Recalcular advertencias/fingerprint bajo locks |
| `AnularConfiguracionAgendaRequest` | AGENDA-10 | Motivo y token de snapshot firmado | Snapshot actual, futura/no anulada; vecinos actuales |
| `AnularBloqueoAgendaRequest` | AGENDA-09 | Motivo y token de snapshot firmado | Snapshot actual, sigue no anulado y propio |
| `FiltrarBloqueosAgendaRequest` | AGENDA-01 | Filtro permitido, página | Estado temporal se calcula en query |

Los Requests normalizan strings, fechas y filas, usan reglas anidadas y `after()` para feedback de matriz. La autorización puede delegar en Policy, siguiendo el patrón de Requests existentes. Ninguna regla `unique` ni consulta previa reemplaza locks. Los campos de retorno se reducen a valores permitidos (`dashboard`, `detalle`, `historial`, página/filtros); nunca se acepta una URL arbitraria.

## 26. Actions

### Actions

| Action propuesta | Responsabilidad | Transacción | Locks | Auditoría |
|---|---|---:|---|---|
| `CrearConfiguracionAgendaAction` | Primera, hueco o sucesora; cabecera + matriz | Sí | Kinesiologa; configuraciones por id | Alta y reconciliación de A |
| `ActualizarConfiguracionAgendaFuturaAction` | Editar período/duración/matriz futura | Sí | Kinesiologa; configuraciones por id | Cambio semántico y vecinos |
| `CambiarDuracionAgendaAction` | Crear nueva vigencia desde matriz origen | Sí | Kinesiologa; configuraciones por id | Nueva duración y cierre de A |
| `AnularConfiguracionAgendaFuturaAction` | Anular B y reconciliar A/B/C | Sí | Kinesiologa; configuraciones por id | Anulación y reconciliación |
| `RegistrarBloqueoAgendaAction` | Revalidar `fin > ahora`, confirmar snapshots de advertencias y crear bloqueo | Sí | Kinesiologa; turnos y bloqueos por id | Alta de bloqueo |
| `AnularBloqueoAgendaAction` | Anulación lógica | Sí | Kinesiologa; bloqueo objetivo | Anulación de bloqueo |

Entradas: Model propio cuando hay target, payload validado/canónico, actor y tokens firmados de snapshot/revisión. Salidas: Model actualizado más un resultado pequeño de operación (`cambioReal`, reconciliaciones y conteos), no Responses HTTP.

Errores esperables: período superpuesto, registro iniciado/anulado, snapshot obsoleto, matriz incompatible, origen inexistente, bloqueo completamente histórico al intentar crearlo y revisión de bloqueo desactualizada. No se crea una Action CRUD genérica.

## 27. Controllers

- `AgendaController@index` — AGENDA-01; autoriza `viewAny`; usa `AgendaQueryService`; entrega dashboard y paginador de bloqueos.
- `ConfiguracionAgendaController@create/store` — AGENDA-02; Policy de clase, Request y Action de creación; redirect al dashboard.
- `ConfiguracionAgendaController@show` — AGENDA-05; Policy de instancia; detalle, estado y flags actuales.
- `ConfiguracionAgendaController@edit/update` — AGENDA-03; Policy; precarga/guarda futura; conserva origen permitido.
- `HistorialConfiguracionAgendaController@index` — AGENDA-06; sólo lectura paginada.
- `CambioDuracionAgendaController@create/store` — AGENDA-04; obtiene origen coherente y ejecuta Action específica.
- `AnulacionConfiguracionAgendaController@create/store` — AGENDA-10; muestra proyección A/B/C y confirma anulación.
- `BloqueoAgendaController@create/review/store/show` — AGENDA-07/08; revisión sin write y confirmación revalidada.
- `AnulacionBloqueoAgendaController@create/store` — AGENDA-09.

Todos son tradicionales y finos. Capturan excepciones esperables sólo para convertirlas en redirect con errores/feedback; no capturan fallos inesperados. Tras escritura usan Post/Redirect/Get.

## 28. Rutas conceptuales

### Rutas conceptuales

| Verbo | Operación | Pantalla UX | Controller conceptual | Autorización |
|---|---|---|---|---|
| GET `/turnos/agenda` | Dashboard | AGENDA-01 | `AgendaController@index` | `viewAny` |
| GET `/turnos/agenda/configuraciones/crear` | Form alta | AGENDA-02 | `ConfiguracionAgendaController@create` | `create` |
| POST `/turnos/agenda/configuraciones` | Crear | AGENDA-02 | `ConfiguracionAgendaController@store` | `create` |
| GET `/turnos/agenda/configuraciones/historial` | Historial | AGENDA-06 | `HistorialConfiguracionAgendaController@index` | `viewAny` |
| GET `/turnos/agenda/configuraciones/{configuracion}` | Detalle | AGENDA-05 | `ConfiguracionAgendaController@show` | `view` |
| GET `/turnos/agenda/configuraciones/{configuracion}/editar` | Form edición | AGENDA-03 | `ConfiguracionAgendaController@edit` | `update` |
| PUT `/turnos/agenda/configuraciones/{configuracion}` | Actualizar futura | AGENDA-03 | `ConfiguracionAgendaController@update` | `update` |
| GET `/turnos/agenda/duracion/crear` | Form nueva duración | AGENDA-04 | `CambioDuracionAgendaController@create` | `cambiarDuracion` |
| POST `/turnos/agenda/duracion` | Programar duración | AGENDA-04 | `CambioDuracionAgendaController@store` | `cambiarDuracion` |
| GET `/turnos/agenda/configuraciones/{configuracion}/anular` | Form anulación | AGENDA-10 | `AnulacionConfiguracionAgendaController@create` | `anular` |
| PATCH `/turnos/agenda/configuraciones/{configuracion}/anular` | Anular futura | AGENDA-10 | `AnulacionConfiguracionAgendaController@store` | `anular` |
| GET `/turnos/agenda/bloqueos/crear` | Captura | AGENDA-07 | `BloqueoAgendaController@create` | `create` |
| POST `/turnos/agenda/bloqueos/revisar` | Revisar | AGENDA-07 | `BloqueoAgendaController@review` | `create` |
| POST `/turnos/agenda/bloqueos` | Confirmar alta | AGENDA-07 | `BloqueoAgendaController@store` | `create` |
| GET `/turnos/agenda/bloqueos/{bloqueo}` | Detalle | AGENDA-08 | `BloqueoAgendaController@show` | `view` |
| GET `/turnos/agenda/bloqueos/{bloqueo}/anular` | Form anulación | AGENDA-09 | `AnulacionBloqueoAgendaController@create` | `anular` |
| PATCH `/turnos/agenda/bloqueos/{bloqueo}/anular` | Anular bloqueo | AGENDA-09 | `AnulacionBloqueoAgendaController@store` | `anular` |

Nombres sugeridos bajo `turnos.agenda.*`. Las rutas fijas `historial`, `crear` y `duracion` se declaran antes de bindings dinámicos. No hay rutas administrativas ni `destroy`.

## 29. Consultas y servicios de lectura

`AgendaQueryService` está justificado porque AGENDA-01 combina vigente, futuras, matriz, historial resumido y bloqueos paginados, mientras AGENDA-07 combina turnos y bloqueos. Encapsula composición y contratos, no escrituras.

Operaciones candidatas:

- `dashboard(Kinesiologa, hoy, ahora, filtros)`: vigente exacta o null, futuras no anuladas, próxima, resumen histórico y bloqueos paginados.
- `detalleConfiguracionPropia`, `historialConfiguraciones(...)->paginate(10)`.
- `configuracionAplicableEn`, `predecesoraDe`, `sucesoraDe` para lecturas; Actions repiten bajo lock.
- `bloqueosPorFiltro`: todos/próximos/en curso/pasados/anulados usando condiciones temporales, no columna de estado.
- `turnosAfectados` y `bloqueosSuperpuestos`.

Los scopes viven en Models cuando son filtros de una sola entidad. La agregación y presentación derivada viven en el Query Service. No se crea Repository ni se oculta Eloquent.

## 30. Transacciones, locks y concurrencia

Estrategia elegida: toda mutación de configuraciones, bloqueos y, como contrato futuro, turnos que alteren disponibilidad, bloquea primero la fila `kinesiologas` propia con `lockForUpdate()`. Luego bloquea colecciones necesarias en orden de `id`: configuraciones, turnos y bloqueos. Esto serializa por profesional, permite concurrencia entre profesionales y cubre el conjunto vacío.

### Concurrencia

| Escenario | Riesgo | Locks | Revalidación | Resultado seguro |
|---|---|---|---|---|
| Dos nuevas configuraciones | Ambas ven hueco y crean overlap | Kinesiologa; configuraciones por id | Hoy, contexto y overlap tras obtener lock | Una confirma; la otra ve la primera y falla o elige período válido |
| Editar futura mientras otro anula | Update revive/sobrescribe anulada | Kinesiologa; target dentro del conjunto | Snapshot semántico, `anulado_at`, `desde > hoy` | Sólo primera operación escribe; segunda detecta snapshot obsoleto |
| Anular futura mientras otro modifica | Reconciliación basada en período/matriz viejos | Mismo orden | Snapshot semántico completo, elegibilidad y vecinos | Segunda recalcula/rechaza; no pierde cambio, incluso si sólo cambiaron franjas |
| Dos cambios de duración | Dos sucesoras incompatibles | Kinesiologa; configuraciones | Origen, fecha, matriz y overlap | Se serializan; segunda se rechaza o parte del estado nuevo |
| Anular B mientras se crea C | A se abre sobre C o C usa B como vecina | Kinesiologa; todas las configuraciones | Recalcular A/B/C y overlap | Operación posterior incorpora la anterior sin overlap |
| Dos anulaciones del mismo bloqueo | Doble auditoría/motivo sobrescrito | Kinesiologa; bloqueo | Snapshot semántico y `anulado_at` | Primera anula; segunda detecta cambio y es inválida |
| Registrar bloqueo mientras cambian turnos | Warning obsoleto | Kinesiologa; turnos afectados por id | Reconsulta y fingerprint; futuro contrato de lock en Turnos | Si cambia la lista, exige nueva confirmación; no modifica turnos |
| Dos bloqueos superpuestos simultáneos | Una revisión no ve al otro | Kinesiologa; bloqueos superpuestos por id | Reconsulta tras serializar | Ambos pueden registrarse; el segundo informa el primero |

La fila de kinesióloga es superior a bloquear sólo configuraciones: cuando no hay configuraciones, no existe fila que bloquee; además, los locks de rango no se comportan igual en SQLite. El costo es aceptable porque el UC tiene frecuencia baja y el lock se limita a una profesional y a transacciones cortas.

Las consultas para formularios no mantienen locks. Sólo la confirmación abre transacción. No se hacen llamadas HTTP, render ni trabajo pesado dentro de ella. Si el motor detecta deadlock, Laravel puede reintentar la transacción un número pequeño y acotado, pero toda reejecución debe ser segura y la auditoría permanecer dentro de la misma transacción.

SQLite ignora o limita `FOR UPDATE`; PHPUnit valida reglas y orden lógico, pero la eficacia real del bloqueo necesita una prueba de integración concurrente en una base MySQL de testing aislada, nunca sobre la base de desarrollo.

## 31. Idempotencia y manejo de conflictos

- Update futuro semánticamente idéntico: no-op legítimo después de revalidar elegibilidad; no cambia `updated_at`, franjas ni auditoría.
- Segunda anulación de configuración o bloqueo: operación inválida, no no-op; informa estado actual y no audita.
- Doble submit de creación/configuración: el lock y overlap hacen que el segundo falle. En update, el token firmado de snapshot semántico detecta que el estado mostrado quedó obsoleto, incluso si el cambio concurrente afectó sólo franjas.
- Doble submit de bloqueo: como dos bloqueos idénticos son válidos en el dominio, no puede deduplicarse por contenido. Se usa nonce de revisión de un solo uso, Post/Redirect/Get, botón deshabilitado y bloqueo de sesión de Laravel para serializar requests de la misma sesión. Una repetición desde otra sesión no puede distinguirse de una creación intencional sin agregar una clave de idempotencia persistida, lo cual no está autorizado.
- Estado cambiado entre GET y POST: Policy se vuelve a evaluar y Action revalida bajo lock; se rechaza con datos actuales.
- Fingerprint de revisión cambiado: conflicto recuperable; no se escribe y se exige reconfirmar advertencias nuevas.

Snapshot e idempotencia son controles distintos. El snapshot es un valor opaco firmado/autenticado por el servidor sobre una representación canónica y detecta obsolescencia; no sustituye Policies, locks, validaciones, elegibilidad ni overlap. El nonce y Post/Redirect/Get controlan la repetición de una intención. No se agrega `version`, `lock_version`, UUID, tabla ni columna persistente. `updated_at` continúa como timestamp normal de Eloquent, pero no es el token ni la única fuente de verdad concurrente.

## 32. Auditoría

Módulo propuesto: `Agenda`. Los nombres exactos quedan congelados aquí para evitar variación durante implementación.

### Auditoría

| Operación | Entidad principal | Antes | Después | Evento conceptual |
|---|---|---|---|---|
| Crear configuración | Nueva configuración | Vacío | Período, duración y matriz | `configuracion_agenda_creada` |
| Actualizar futura | Configuración futura | Snapshot cabecera + matriz | Snapshot nuevo | `configuracion_futura_actualizada` |
| Cambiar duración | Nueva configuración | Origen referenciado | Nueva vigencia, duración y matriz | `duracion_agenda_programada` |
| Reconciliar vecina | Configuración A | `vigencia_hasta` anterior | `vigencia_hasta` nueva + causa/id | `configuracion_vigencia_reconciliada` |
| Anular futura | Configuración B | Anulación vacía | `anulado_at`, motivo | `configuracion_futura_anulada` |
| Registrar bloqueo | Nuevo bloqueo | Vacío | Intervalo y motivo | `bloqueo_agenda_registrado` |
| Anular bloqueo | Bloqueo | Anulación vacía | `anulado_at`, motivo | `bloqueo_agenda_anulado` |

Actor: `User` autenticado; auditable: el Model persistido. Si una operación toca B y A se registran dos auditorías dentro de la misma transacción: la intención principal sobre B/nueva y la reconciliación sobre A, enlazadas en snapshots mediante ids y causa. No se auditan lecturas, warnings ni no-op. No se guardan tokens, payloads completos no filtrados ni datos sensibles. Un fallo de auditoría revierte la escritura.

## 33. Contratos Controller → View para AGENDA-01 a AGENDA-10

| Pantalla | Lecturas | Escritura | Request | Action | Policy | Resultado |
|---|---|---|---|---|---|---|
| AGENDA-01 | Vigente nullable, matriz, futuras, próxima, resumen historial, bloqueos paginados, filtros | No | `FiltrarBloqueosAgendaRequest` | No | `viewAny` | Dashboard con estados y flags calculados |
| AGENDA-02 | Contexto primera/hueco/sucesora, defaults o matriz sugerida, hoy | Crear configuración + franjas | `StoreConfiguracionAgendaRequest` | `CrearConfiguracionAgendaAction` | `create` | Redirect AGENDA-01 |
| AGENDA-03 | Configuración futura propia, matriz, token de snapshot firmado, origen de retorno | Actualizar y reconciliar | `UpdateConfiguracionAgendaRequest` | `ActualizarConfiguracionAgendaFuturaAction` | `update` | Redirect al origen permitido |
| AGENDA-04 | Origen aplicable, duración actual, matriz copiada, token de snapshot firmado, incompatibilidades | Crear sucesora de duración | `StoreCambioDuracionAgendaRequest` | `CambiarDuracionAgendaAction` | `cambiarDuracion` | Redirect AGENDA-01 |
| AGENDA-05 | Configuración, matriz, estado, conteos, anulación, flags | No | Request común | No | `view` | Detalle sólo lectura |
| AGENDA-06 | Paginador de históricas/futuras anuladas con conteos | No | Request común | No | `viewAny` | Listado `paginate(10)` |
| AGENDA-07 | Captura; payload normalizado con `fin > ahora`; turnos `PENDIENTE/CONFIRMADO`; overlaps; token firmado de revisión y nonce | Registrar bloqueo al confirmar | `Revisar...` / `StoreBloqueo...` | `RegistrarBloqueoAgendaAction` | `create` | Revisión o redirect AGENDA-01 |
| AGENDA-08 | Bloqueo, estado, anulación, overlaps restantes, flag legal | No | Request común | No | `view` | Detalle sólo lectura |
| AGENDA-09 | Bloqueo propio, token de snapshot firmado, motivo vacío, retorno | Anular bloqueo | `AnularBloqueoAgendaRequest` | `AnularBloqueoAgendaAction` | `anular` | Redirect listado/detalle seguro |
| AGENDA-10 | B y proyección A/C, matriz, token de snapshot firmado, consecuencias | Anular B y reconciliar A | `AnularConfiguracionAgendaRequest` | `AnularConfiguracionAgendaFuturaAction` | `anular` | Redirect AGENDA-01 |

Blade recibe estados textuales, flags `puedeEditar/puedeAnular`, resúmenes e incompatibilidades ya calculados. Nunca recibe `kinesiologa_id` editable, nombres de estado para persistir, fin normalizado confiado al cliente, ni decisiones de reconciliación. Los tokens ocultos sólo detectan obsolescencia; no autorizan.

## 34. Paginación y contexto de retorno

Historial de configuraciones y listado de bloqueos usan `paginate(10)`. El dashboard no carga colecciones completas: muestra futuras acotadas y resumen histórico, y pagina bloqueos con `pageName('bloqueos_page')`. El historial usa su propia `page`.

Filtros y página se conservan mediante `withQueryString()` y parámetros permitidos. AGENDA-05/08/09 conservan un descriptor de origen validado y reconstruyen rutas internas; no aceptan `return_url`. Tras eliminar una página lógica por cambio de filtro/estado, se redirige a la última página válida o primera, nunca a una URL vacía.

## 35. Estrategia de errores

- Errores de forma/campos y overlap detectado al confirmar: `ValidationException::withMessages`, retorno con input y errores.
- Recurso ajeno: Policy `403` o binding propio `404`, de forma consistente y sin exponer datos.
- Id inexistente: `404`.
- Ya iniciada, ya anulada, snapshot obsoleto o revisión cambiada: excepción de dominio capturada por Controller; redirect con warning/errores y estado refrescado, sin 500.
- Nuevo bloqueo con `fin_at <= ahora` después de normalizar: error de validación asociado al intervalo; se conserva la captura y no se abre una excepción para bloquear históricos ya registrados.
- Deadlock agotado o fallo de infraestructura: se registra por manejo global, rollback y feedback genérico; no se presenta como éxito.
- Matriz/origen incompatible: error asociado a duración, fecha o filas concretas.

Las excepciones propias llevan información estable, no Responses. No se crea una jerarquía compleja: unas pocas clases (`ConfiguracionNoElegible`, `ConflictoTemporalAgenda`, `RevisionBloqueoDesactualizada`) bastan; el resto usa validación Laravel.

## 36. Componentes técnicos candidatos

- `DiaSemanaEnum`: traducción ISO `1..7`, labels localizados y orden estable.
- `AgendaQueryService`: única composición de lecturas del módulo.
- `ValidadorMatrizAgenda`: normalización y errores cruzados reutilizables.
- `RelojAplicacion`: fuente inyectable de hoy/ahora configurados.
- Componente Alpine `matrizAgenda`: agregar/quitar filas, cálculo de duración y feedback, sin sustituir backend.
- Blade locales de Agenda: matriz editable, matriz lectura, badge temporal, resumen configuración, tabla/cards de bloqueos y advertencias de revisión. Se extraen sólo al comprobar contratos compartidos.
- `.js-confirm-action` existente para AGENDA-09/10 y guardados finales cuando el formulario ya contiene datos; no capturar motivos en SweetAlert2.

No se generalizan componentes de Catálogos ni se agrega librería. El normalizador de bloqueos puede vivir en el Request base; no requiere DTO por cada pantalla. El payload canónico de matriz sí justifica una estructura pequeña por su reutilización en tres operaciones.

## 37. Estrategia de pruebas

### Pruebas

| Área | Caso | Tipo | Prioridad |
|---|---|---|---|
| Migrations | Campos, FKs restrictivas, índices y ausencia de UK/estado | Feature SQLite + inspección MySQL | Alta |
| Models | Relaciones, casts, enum de día, estados con fecha explícita | Unit/Feature | Alta |
| Consultas | Aplicable en bordes inclusivos, hueco, anulada excluida | Feature | Alta |
| Overlap | Abiertos, contiguos, contenido total, propia excluida | Unit + Feature | Alta |
| Requests | Duración 15/180/múltiplo; fechas hoy/pasado; motivos | Feature | Alta |
| Matriz | Vacía, filas parciales, contiguas, superpuestas, franja corta | Unit + Request Feature | Alta |
| Crear | Primera hoy/futura, hueco hoy, sucesora `> hoy`, rollback | Action Feature | Alta |
| Editar | Sólo futura, no-op, snapshot obsoleto por cabecera o sólo por franjas, matriz reemplazada | Action Feature | Alta |
| Duración | Nueva vigencia, snapshot de origen obsoleto —incluidas franjas—, `F-1`, incompatibles, C posterior | Action Feature | Alta |
| Anular configuración | Snapshot obsoleto, A/B/C, sin A, sin C, hueco deliberado, carrera | Action Feature | Alta |
| Bloqueo | Todo el día hoy, futuro y en curso válidos; `fin <= ahora` rechazado; parcial continuo; overlaps permitidos | Unit + Feature | Alta |
| Turnos afectados | Intersección semiabierta; incluye sólo `PENDIENTE/CONFIRMADO`; excluye los otros cuatro estados | Feature | Alta tras implementar persistencia Turno |
| Revisión bloqueo | Token firmado con payload y snapshots semánticos estable/cambiado; no write en revisión | Feature | Alta |
| Anular bloqueo | Snapshot obsoleto, primera válida, segunda inválida, pasado anulable, overlaps restantes | Action Feature | Alta |
| Policies | Propia, ajena, paciente, admin, superadmin, perfil | Feature | Alta |
| Controllers | AGENDA-01..10, redirects, old input, filtros, acceso directo | Feature | Alta |
| Auditoría | Antes/después, dos eventos de reconciliación, rollback, no-op | Feature | Alta |
| UI | Vacíos, matriz, warnings, markup desktop/mobile, a11y básico | Feature/JS | Media |
| Concurrencia | Ocho carreras de sección 30 | MySQL integration | Alta |

Las pruebas Unit se reservan para funciones puras (intervalos, matriz, estado). Las que usan Eloquent, Policies, HTTP, auditoría o transacciones son Feature, coherente con el repositorio. No se debilitan reglas para acomodar SQLite; los locks se prueban adicionalmente en MySQL aislado.

## 38. Datos de prueba y control del tiempo

Factories futuras: `ConfiguracionAgendaFactory` con estados `vigente`, `futura`, `historica`, `anulada`; `FranjaHorariaAgendaFactory`; `BloqueoAgendaFactory` con `proximo`, `enCurso`, `pasado`, `anulado`; y `TurnoFactory` cuando exista su modelo. Builders de tests deben crear una configuración completa con al menos una franja, no fixtures gigantes.

Usar `Carbon::setTestNow()` o helpers `travelTo()` ya presentes y restaurar el reloj en teardown. Todas las fechas se calculan respecto de una fecha congelada en la zona configurada. Helpers útiles: `periodo(desdeOffset, hastaOffset)`, `matrizSemanal(...)` y escenarios A/B/C explícitos. No usar fechas absolutas salvo pruebas de bordes de zona/medianoche.

Para concurrencia MySQL, dos conexiones/transacciones coordinadas sobre una base de testing desechable verifican bloqueo y resultado final; no se ejecutan sobre MySQL de desarrollo.

## 39. Orden de implementación propuesto

### Orden de implementación

| Subpaso | Alcance | Archivos previstos | Tests | Criterio de cierre |
|---|---|---|---|---|
| 1 | Esquema Agenda | 3 migrations nuevas | Migration Feature MySQL/SQLite | Esquema, FKs e índices exactos |
| 2 | Models, enum, factories y relaciones | Models, `DiaSemanaEnum`, factories | Relaciones/casts | Sin estado persistido ni delete ordinario |
| 3 | Reglas puras y consultas temporales | Validador, reloj, Query Service | Unit + Feature | Bordes inclusivos, huecos y overlaps verdes |
| 4 | Policies y middleware/ruta conceptual mínima | Policies y tests; luego rutas en subpaso propio | Policy Feature | Sólo kinesióloga propietaria |
| 5 | Requests de configuración/matriz | Requests y tests | Request Feature | Mensajes y payload canónico completos |
| 6 | Actions de configuraciones | 4 Actions, excepciones | Action/auditoría/concurrencia | A/B/C, rollback y no-op cubiertos |
| 7 | Persistencia compartida de Turno con dominio ya congelado | Migration/Model/Enum/Factory de Turno en alcance acordado | Modelo, seis estados y consulta `PENDIENTE/CONFIRMADO` | Advertencia implementable sin desarrollar los demás flujos de Turnos |
| 8 | Requests/Actions de bloqueos | Normalizador, 2 Actions | Action Feature | Review, alta y anulación consistentes |
| 9 | Controllers y rutas de lectura/escritura | Controllers, `routes/web.php` | Controller Feature | AGENDA-01..10 alcanzables y autorizadas |
| 10 | Vistas base de dashboard/detalles/historial | Blade locales | Feature markup | Estados vacíos y paginación |
| 11 | Matriz editable | Blade/Alpine local | JS/Feature | Múltiples franjas, errores y old input |
| 12 | Revisión de bloqueos | AGENDA-07 + tokens/fingerprint | Feature | Advertencias reales y reconfirmación |
| 13 | Anulaciones/confirmaciones | AGENDA-09/10 | Feature | Motivos persistidos y foco/retorno correctos |
| 14 | Responsive y accesibilidad | Blade/JS/Tailwind existentes | Markup/manual | Contrato UX desktop/mobile/a11y cumplido |
| 15 | Regresión | Sin ampliar alcance | PHPUnit, Pint acotado, build y suite indicada | Puerta completa verde y diff revisado |

Cada subpaso debe detenerse con sus verificaciones; no adelanta el siguiente. El riesgo principal del paso 7 es la dependencia técnica de Turno; el de 6/8 es concurrencia; el de 11/14 es preservar accesibilidad y old input.

## 40. Riesgos técnicos

1. `turnos` no existe: su tabla/Model/Enum compartidos son una dependencia técnica para ejecutar advertencias reales, aunque los seis estados canónicos y el subconjunto afectado `PENDIENTE/CONFIRMADO` ya están congelados.
2. SQLite no demuestra locks InnoDB: requiere prueba MySQL aislada antes de certificar concurrencia.
3. Toda futura escritura de turnos debe respetar el lock por kinesióloga; si no, la revisión de bloqueos puede quedar obsoleta entre consulta e insert.
4. Un DATETIME no almacena zona: debe sostenerse la convención de `config('app.timezone')` y verificar la sesión MySQL.
5. La edición de una futura puede tocar una predecesora. Snapshots y auditorías separadas son obligatorios para no ocultar esa reconciliación.
6. Un doble submit de bloqueo desde sesiones diferentes es indistinguible de dos bloqueos idénticos deliberados bajo el esquema congelado.
7. Datos corruptos preexistentes con overlap no deben resolverse eligiendo silenciosamente una fila; la implementación debe fallar de forma observable.

## 41. Decisiones descartadas

- Bloquear sólo configuraciones existentes: falla con conjunto vacío.
- UK `(kinesiologa_id, vigencia_desde)` o una sola agenda por profesional: contradice el Diccionario y no resuelve overlap.
- Estados, slots, `tipo` o `todo_el_dia` persistidos: datos derivados/no autorizados.
- `configuracion_agenda_id` en turnos: acoplamiento expresamente prohibido.
- Update directo de duración vigente: reescribe historia.
- Cerrar/reabrir siempre la predecesora: eliminaría huecos deliberados.
- Fusionar bloqueos o impedir su overlap: contradice el dominio.
- Repository, CQRS, eventos/jobs/scheduler para cambiar estados: complejidad sin necesidad.
- Modal para formularios extensos o captura de motivo en SweetAlert2: contradice AGENDA-02/03/04/07/09/10.
- Confiar sólo en Form Request, JavaScript o Policy para invariantes concurrentes: deja carreras.
- Deduplicar bloqueos por intervalo/motivo: impediría registros independientes válidos.
- Crear una tabla `turnos` parcial sólo para este UC: arriesga congelar dominios de otros UCs sin autoridad suficiente.

## 42. Preguntas abiertas reales

No se identifican preguntas abiertas funcionales que impidan congelar este diseño técnico. El dominio canónico de Turno y el subconjunto usado por la advertencia de bloqueo están resueltos; la ausencia actual de tabla/Model/Enum de Turno es una dependencia de implementación, no una pregunta funcional.

## 43. Criterios para iniciar implementación

La implementación puede comenzar cuando:

1. Este documento sea revisado y congelado.
2. Se planifique la persistencia compartida de Turno —con el dominio canónico ya congelado— antes del subpaso que implementará advertencias reales de bloqueos.
3. Se confirme el orden de migrations nuevas sin reescribir historia.
4. Se mantenga la rama y un working tree conocido.
5. Cada subpaso tenga archivos, tests y puerta de cierre propios.
6. El lock por `kinesiologas` se adopte como contrato común de todas las escrituras que alteren disponibilidad/turnos de una profesional.
7. Se disponga de una base MySQL de testing aislada para certificar las carreras críticas, además de SQLite.
8. Se preserve sin cambios AGENDA-01 a AGENDA-10 y todas las reglas congeladas enumeradas en la sección 1.

Este diseño no autoriza todavía migrations, Models, Enums, Requests, Actions, Policies, Services, Controllers, rutas, vistas, JavaScript ni tests.
