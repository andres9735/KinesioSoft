# UC–07 Solicitar turno V2 — Cierre definitivo

| Dato                        | Resultado vigente                                                                                      |
| --------------------------- | ------------------------------------------------------------------------------------------------------ |
| Estado                      | **UC-07 SOLICITAR TURNO V2 = CERRADO**                                                                 |
| Fecha documental            | 24/10/2026; actualización tras MC-03: 25/10/2026, America/Buenos_Aires                                 |
| Rama                        | `feature/turnos-solicitar-turno`                                                                       |
| Base oficial                | `9d9b1510402e64c310f02ec1014b24333c13ed0c`                                                             |
| HEAD técnico definitivo     | `ba97c4b8e0a1df53c1c456da424b549db9798828` — `fix: unificar foco visual de pasos del wizard V2`        |
| Ejecución delta             | **7.29–7.52 COMPLETADOS**                                                                              |
| Puertas                     | **V2-P1–V2-P6 PASS**                                                                                   |
| Regresión global definitiva | **2231 tests / 14391 assertions PASS**                                                                 |
| JavaScript / build          | **150 Node PASS / build PASS**                                                                         |
| Versionado documental       | Cierre preparado en 7.52 y versionado manualmente por el usuario como cierre definitivo del incremento |

## 1. Vigencia, historia y fuentes

**V2 es la versión funcional vigente y cerrada.** UC–07 tuvo una implementación V1 completa y certificada sobre `3a95e84`. La devolución docente motivó el delta V2: disponibilidad y selección de inicio antes de profesional, nuevo orden del wizard y descripción individual por adjunto. V2 supersede el flujo V1, preservando la infraestructura que continuó siendo correcta.

El [anexo histórico V1](#anexo-histórico-v1) conserva el cierre de 7.28, sus resultados, componentes, decisiones físicas y limitaciones. Sus contratos, cifras y estados se refieren exclusivamente a V1; no son instrucciones activas, trabajo por ejecutar ni el baseline final V2. Las referencias V1 y enunciados temporales se conservan como trazabilidad histórica.

Fuentes consultadas: [README local](../_codex_sources/turnos/README_Codex_sources.md), [UC–07 vigente](../_codex_sources/turnos/UC-07_Solicitar_turno.txt), [D/DT V2](../_codex_sources/turnos/DECISIONES_UC07_DISENO_TECNICO_V2.0.md), `AGENTS.md` y [planificación, Parte II](turnos-solicitar-turno-planificacion.md). Este documento consolida resultados, sin cambiar decisiones congeladas. Las fuentes locales permanecen excluidas de Git y no fueron modificadas.

Los resultados globales definitivos proceden de la reejecución de 7.51 después de MC-03 sobre `ba97c4b`, registrada en la conversación de ejecución. Los resultados de gates anteriores y de MariaDB se toman de sus reportes certificados incorporados al prompt autorizado de 7.52; donde se cita un artefacto local se verificó su contenido. No se inventan archivos de logs para corridas reportadas únicamente en conversación, ni se reproducen totales focales PHP sin respaldo consultado. En 7.52 no se reejecutaron suites, migraciones, navegador, HTTP ni operaciones de DB.

## 2. Flujo funcional definitivo

```text
Mis turnos → Solicitar nuevo turno
→ Paso 1: Fecha y horario
→ Paso 2: Elegir profesional
→ Paso 3: Motivo y documentación
→ Paso 4: Revisar y confirmar
→ Turno PRIMERA_CONSULTA / PENDIENTE
→ feedback de éxito → Mis turnos
```

Primera consulta inicia un nuevo episodio/ciclo, no necesariamente la primera atención histórica. UC–07 no crea Padecimiento, Consulta, diagnóstico ni entidad intermedia SolicitudTurno. Seleccionar fecha, inicio o profesional no reserva agenda. Pendiente ocupa agenda inmediatamente al consolidarse.

### Paso 1 — Fecha y horario

`DisponibilidadAgregadaPrimeraConsultaService` consume el motor individual canónico `DisponibilidadPrimeraConsultaService`, Agenda y `RelojAplicacion`: profesionales elegibles → slots individuales → fechas → inicios deduplicados. No duplica grilla, overlap ni estados ocupantes ni realiza SQL por slot.

Fechas pasadas deshabilitadas; no consultada es diferente de consultada sin disponibilidad. Se elige fecha y luego `inicio_at`, antes de profesional. No se muestra un `fin_at` universal: distintas profesionales pueden compartir inicio con finales propios. UI de siete días por ventana; sin máximo funcional de anticipación. Hasta 31 fechas inclusivas por request es un límite técnico configurable, no horizonte de reserva. Mismo día exige `inicio_at > ahora`, sin buffer.

### Paso 2 — Elegir profesional

GET por `inicio_at`: sólo profesionales elegibles y disponibles para ese inicio, cada una con su propio `fin_at`. Selección explícita, sin autoselección aunque exista una sola opción. La duración es propia de la profesional/configuración; el fin informativo de UI se deriva nuevamente al confirmar.

### Paso 3 — Motivo y documentación

Motivo requerido, máximo **500 caracteres**. Documentación opcional: hasta **5 archivos**, PDF/JPEG/JPG/PNG, **10 MiB individual** y **25 MiB total**. Backend valida uploads, MIME real y bytes mediante la política canónica.

Cada archivo aceptado exige descripción individual con trim, longitud significativa **1..500**. Inválidos se rechazan individualmente, conservando válidos, sin contar ni sumar tamaño. Un válido sin descripción permanece seleccionado y bloquea avance. Se puede agregar en selecciones sucesivas, quitar o reemplazar; reemplazar crea nueva identidad local y exige revisar la descripción. No hay preupload ni draft persistente: File en memoria hasta el POST final.

MC-01 es pulido UX: el input nativo se vacía al transferir los File al estado, pero la presentación usa `archivos.length`. Sin aceptados informa “Ningún archivo seleccionado”; con aceptados comunica **“Agregar más archivos”** y ayuda contextual. Conserva input real accesible, selección múltiple, drag/drop, contador inferior y foco visible mediante la etiqueta asociada.

### Paso 4 — Revisar y confirmar

Primera consulta, profesional, fecha, intervalo real, motivo íntegro, documentos y descripción por documento. Estado inicial **Pendiente**, ocupación inmediata de agenda y confirmación única. La UI bloquea doble envío/navegación incompatible durante el request y sólo anuncia éxito ante un contrato válido.

## 3. D-01 y Mis turnos

Máximo una Primera consulta futura que reserve agenda por paciente, independientemente de profesional o dimensión clínica. Definición canónica: `PRIMERA_CONSULTA`, estados **PENDIENTE/CONFIRMADO** e `inicio_at > RelojAplicacion::ahora()`. Históricos, igualdad con ahora, otros pacientes, Seguimiento y estados no ocupantes no satisfacen D-01.

Revalidación al ingresar y al confirmar. Si bloquea el GET, el Controller renderiza el estado específico **sin stepper operativo, UUID utilizable, calendario ni consultas de disponibilidad**, con CTA de servidor **Ir a Mis turnos**. No depende del warning global genérico.

D-01 concurrente devuelve `409 primera_consulta_activa`: terminal, sin R1/R2/R3, GET recovery, resultado incierto ni retry POST. Ofrece Mis turnos. Replay consolidado se resuelve antes de exigir condiciones de una creación nueva.

Mis turnos deriva el paciente del usuario autenticado: propiedad/aislamiento, paginación y visibilidad inmediata del turno con tipo, estado, profesional, fecha e intervalo persistido. No expone motivo ni documentos clínicos en el listado. El CTA Solicitar nuevo turno vuelve al backend autoritativo para D-01. No incorpora cancelación, reprogramación o asistencia.

## 4. HTTP y multipart V2

| Endpoint real                                     | Entrada / respuesta                                                                                                        |
| ------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| GET `/turnos/solicitar/disponibilidad`            | `desde`, `hasta`; 200 con ventana y `dias[].fecha`, `dias[].horarios[].inicio_at`; sin profesional previa ni fin universal |
| GET `/turnos/solicitar/profesionales-disponibles` | `inicio_at`; 200 con `inicio_at` y `profesionales[]` (id, nombre público, fin propio), incluso lista vacía                 |
| POST `/turnos/solicitar`                          | Alta 201; replay 200; validación 422; conflictos conocidos 409                                                             |
| GET `/turnos/mis-turnos`                          | Listado propio y destino posterior                                                                                         |
| GET `/documentos-clinicos/{documento}/descargar`  | Acceso privado, autenticación y Policy; descarga efectiva auditada                                                         |

Payload funcional definitivo:

```text
clave_idempotencia
kinesiologa_id
inicio_at
motivo_solicitud
adjuntos[n][archivo]
adjuntos[n][descripcion]
```

No se usa `adjuntos[]` V1 ni arrays paralelos. `fecha`, `fin_at`, `duracion`, `tipo`, `estado`, `paciente_id` y `user_id` no son fuentes autoritativas del cliente. El servidor deriva paciente, tipo, estado, fin, cargador, valores documentales iniciales, ruta, MIME y tamaño. CSRF es transporte; no se copia a evidencia documental.

422 se asocia a motivo, conjunto o `adjuntos.n.archivo`/`adjuntos.n.descripcion`, preservando entradas compatibles. Los códigos estables siguen siendo `primera_consulta_activa`, `profesional_no_elegible` y `slot_no_disponible`; no se decide por mensajes humanos ni se disfraza un error técnico como vacío/indisponibilidad.

## 5. Persistencia, atomicidad y conservación documental

Único cambio físico V2, migration aditiva versionada en 7.30:

`2026_10_22_000001_add_descripcion_to_documentos_clinicos_table.php`

```text
documentos_clinicos.descripcion VARCHAR(500) NULL
```

Nullable permite compatibilidad histórica y otros orígenes; UC–07 V2 exige descripción significativa por nuevo adjunto. Sin backfill artificial ni reescritura de migrations históricas. Model/factory permiten NULL histórico; descripción integra el snapshot inmutable del original junto a contenido, metadata y asociación documento-turno.

Antes de 7.50 se detectó pendiente y se aplicó con autorización explícita, por path exacto, exclusivamente sobre `kinesiosoft_db`. Quedaron **47 Ran / 0 Pending**, `varchar(500)` nullable físico. Los **23 documentos históricos** conservaron `descripcion = NULL`, según el reporte del desbloqueo previo a 7.50. No se creó durante aceptación ni se reaplicó al cerrar.

Valores iniciales: `origen=SOLICITUD_TURNO`, `estado_validacion=PENDIENTE`, `tipo_documento_clinico=SIN_CLASIFICAR`. Cargar no equivale a validar clínicamente. Nombre y descripción nunca son ruta, identificador ni lock key. Se conserva storage privado y descarga por Policy.

`ConfirmarPrimeraConsultaAction` consolida turno, motivo, documentos/descripciones, asociaciones y auditoría en una operación funcional. Transacción y locks en orden **kinesióloga → paciente**, con relectura/revalidación posterior. Escrituras que compiten por Agenda respetan el mutex de kinesióloga; no se toman locks de reserva durante navegación.

Filesystem mantiene compensación, coordinación `GET_LOCK`/`RELEASE_LOCK` y reconciliación segura certificadas. Rollback confirmado compensa sólo archivos nuevos propios; commit incierto no elimina a ciegas, sino verifica UUID/referencias y conserva si no puede determinar el resultado. Reconciliación: dry-run por defecto, gracia y referencia DB inmediata, sin seguir symlinks ni agregar scheduler. Se mantiene la limitación de privilegios Windows para certificar creación física de symlinks, sin debilitar la protección productiva.

`AuditoriaService` audita creación, carga por documento y descarga efectiva con whitelist, sin binarios, motivo completo ni descripción clínica completa. Replay no duplica auditorías de creación/carga. Sin solicitudes intermedias, slots, holds ni tabla documental por padecimiento.

## 6. Recovery, incertidumbre, idempotencia y éxito

| Caso revalidado por GET                                | Conservación / destino                                                           |
| ------------------------------------------------------ | -------------------------------------------------------------------------------- |
| R1: profesional perdida, mismo inicio con alternativas | Conserva fecha/inicio; limpia profesional; Paso 2 sin autoselección              |
| R2: inicio perdido, fecha con otros horarios           | Conserva fecha; limpia inicio/profesional; Paso 1 sin autoseleccionar hora       |
| R3: fecha sin horarios                                 | Limpia fecha/inicio/profesional; Paso 1                                          |
| D-01 adquirido                                         | Terminal hacia Mis turnos; sin recovery de disponibilidad                        |
| Error técnico GET                                      | No prueba pérdida; estado técnico y retry de consulta, sin nuevo POST automático |

R1/R2/R3 preservan motivo, File válidos, descripciones y **misma UUID**. Recovery consulta primero profesionales para ese inicio; sin alternativas consulta disponibilidad para distinguir R2/R3. Descarta respuestas obsoletas y no reinicia todo el formulario.

409 conocido es distinto de un POST de resultado incierto por red/respuesta ambigua. La incertidumbre conserva captura y ofrece retry **manual idempotente** con la misma UUID, que representa la intención, no fecha/hora/profesional.

`201` significa nueva consolidación; `200 + es_replay=true` significa replay exitoso del mismo turno, sin documentos/asociaciones/auditorías duplicados. Ambos usan un único SweetAlert **Turno registrado correctamente**, Primera consulta, **Pendiente**, ocupación de agenda y **Entendido**. La X equivale a Entendido: navegación posterior a la URL de Mis turnos configurada por servidor, sin timeout arbitrario ni retorno a confirmar. Éxito terminal, sin email/SMS/WhatsApp/push.

## 7. Stitch, responsive, accesibilidad y MC-01/MC-02/MC-03

Verificadas **14 referencias: seis desktop y ocho excepciones**. No existe referencia mobile V2 canónica; responsive sigue siendo obligatorio. Una columna móvil, archivo+descripción vertical, revisión adaptable, sin scroll horizontal operacional, D-01 sin stepper, recovery accesible y éxito Pendiente/Entendido. Foto opcional con avatar fallback; “Seleccionada” no implica asignación administrativa.

7.47 incluyó render sintético, pruebas de vistas/Node y revisión de presentación. `storage/framework/testing/uc07-747-176b5a1/resultado.json` contiene **59 comprobaciones visuales / 27 capturas**, cero POST; no sustituye aceptación HTTP/DB. 7.50 recorrió la aplicación real en desktop, **375×667 y 320×568**, con teclado, foco, recovery y modal. No se declara certificación WCAG formal.

MC-02 corrigió inicialmente el heading no interactivo del Paso 2, conservando `enfocarPaso()`, `x-ref="tituloPaso2"` y `tabindex="-1"`, retirando sólo su ring. No eliminó foco visible de controles ni modificó el stepper; en ese momento otros headings quedaron intactos. MC-01 corrigió exclusivamente la presentación del selector, descrita en Paso 3.

La microcorrección pasó Node **150 PASS**, focal PHP **52 tests / 718 assertions**, Pint del test modificado, sintaxis PHP, build y diff-check. La primera focal falló en una assertion nueva sobre un atributo booleano descartado por el parser HTML4; se corrigió la comprobación del test al binding drop sin alterar producción ni assertions históricas. No se reabrió Chrome por rutina. Después se versionó en `2b46d24` y se repitió la regresión global completa.

Después de la primera preparación documental de 7.52, la revisión manual detectó el mismo ring residual (`focus:ring-*`) en los headings de los Pasos 1, 3 y 4. **MC-03 — unificación del foco visual de los headings del wizard**, versionada en `ba97c4b` (`fix: unificar foco visual de pasos del wizard V2`), completó el patrón de los cuatro destinos `tituloPasoN` de `enfocarPaso()`: conservó `tabindex="-1"`, foco programático y semántica accesible; eliminó el ring/outline accidental sólo de esos headings no interactivos. Foco visible de controles y stepper intactos, sin backend ni JavaScript productivo. Es pulido UX/accesibilidad, no una regla de negocio nueva.

Ese commit modificó únicamente:

- `resources/views/turnos/solicitar/_fecha-horario.blade.php`.
- `resources/views/turnos/solicitar/_motivo-documentacion.blade.php`.
- `resources/views/turnos/solicitar/_revision.blade.php`.
- `tests/Feature/Turnos/TurnosAccessibilityViewTest.php`.

Antes del commit MC-03: focal `SolicitarTurnoCreateTest|TurnosAccessibilityViewTest` **52 tests / 738 assertions PASS**, 5,68 s; Node **150 PASS / 0 FAIL / 0 SKIP**; build **PASS, 94 módulos, 15,27 s**; Pint focal, `php -l` y diff-check PASS. La corrección quedó cubierta estructuralmente y por regresión automatizada; no se inició Chrome ni se presenta como nueva inspección visual browser o auditoría WCAG. Tras versionar MC-03 se reejecutó 7.51 y luego se actualizó este cierre, sin repetir P1–P5.

## 8. Gates V2

| Gate  | Estado   | Alcance certificado                                                                                                                           |
| ----- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| V2-P1 | **PASS** | 7.30–7.33: descripción, Model/factory, Request, pipeline, Action, atomicidad/replay, auditoría privada y MariaDB focal correspondiente        |
| V2-P2 | **PASS** | 7.34–7.37: agregado, GET disponibilidad, GET profesionales, Requests/rutas e integración backend V2                                           |
| V2-P3 | **PASS** | Hasta 7.44: estado/Pasos 1–4, multipart, R1/R2/R3, errores técnicos, captura/UUID, descarte de respuestas tardías y D-01 separado             |
| V2-P4 | **PASS** | Hasta 7.47: Stitch, D-01, éxito, Mis turnos, responsive/accesibilidad focal, Node y render sintético                                          |
| V2-P5 | **PASS** | 7.48–7.50: focal, MariaDB, Apache multipart y aceptación integral real, con intercepts declarados                                             |
| V2-P6 | **PASS** | 7.51 reejecutado sobre `ba97c4b` tras MC-03: PHP global, Node, build y documentación preexistente preservada, sin nuevos cambios versionables |

P1–P7 V1 del anexo son antecedentes distintos de V2-P1–V2-P6. SQLite no sustituye pruebas físicas de otra capa.

### 8.1 MariaDB/InnoDB — 7.49

Reporte certificado 7.49 incorporado al prompt autorizado de 7.52: **MariaDB 10.4.32**, mysql, **InnoDB**, **REPEATABLE-READ**, `explicit_defaults_for_timestamp=0`. Base exclusiva de pruebas: **`kinesiosoft_uc07_test`**, sin credenciales.

`sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.

| Suite real                                 |  Tests | Assertions |
| ------------------------------------------ | -----: | ---------: |
| `ConfirmarPrimeraConsultaConcurrenciaTest` |     14 |        474 |
| `PrimeraConsultaHttpConcurrencyTest`       |     10 |        611 |
| `AdvisoryLocksDocumentalesTest`            |      4 |         46 |
| `ReconciliacionDocumentalTest`             |      2 |         88 |
| **Total combinado PASS**                   | **30** |   **1219** |

Son cifras del reporte certificado de esa corrida, no una ejecución de 7.52 ni conteos deducidos del código. Cubrió procesos/conexiones independientes, barreras/waits reales: mismo slot, D-01 entre profesionales, misma/distinta UUID, Agenda, multipart, timeout/deadlock, advisory locks y reconciliador concurrente. Archivo artificialmente antiguo protegido durante escritura activa; tras COMMIT conservado por referencia; tras ROLLBACK con residuo, eliminable posteriormente de forma segura. El postflight reportó ausencia de workers/locks y residuos propios. No se repitió MariaDB en el cierre.

### 8.2 Apache y aceptación integral — 7.50

Cadena real: **Chrome → Apache/XAMPP → Laravel → MariaDB `kinesiosoft_db` → filesystem privado**. URL: `http://127.0.0.1/Proyecto2026/KinesioSoft/public`. Apache **2.4.58**, PHP **8.2.12**, SAPI **apache2handler**; configuración efectiva **40M / 40M / 20** para upload_max_filesize/post_max_size/max_file_uploads. Sin cambios preventivos de configuración.

| Checkpoint                                      | Resultado real                                                                                                     |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Mis turnos → Pasos 1–4 → confirmar → Mis turnos | PASS; un POST201, feedback y turno visible una sola vez                                                            |
| Mismo inicio, duraciones propias                | PASS: 10:00–10:30 y 10:00–10:45 antes de elección explícita                                                        |
| Dos archivos y descripciones distintas          | PASS; multipart anidado, DB/asociaciones/storage coherentes                                                        |
| Inválido, retiro y reemplazo                    | PASS; válidos conservados, persistencia igual al resumen                                                           |
| Motivo/descripción faltantes, corrección/foco   | PASS; bloqueo de avance antes de revisar                                                                           |
| D-01 posterior y concurrente                    | PASS; entrada sin wizard/GET; POST409 terminal sin recovery ni segundo POST                                        |
| R1/R2/R3 sobre fixtures propios                 | PASS; POST409, GET reevaluación y conservación UUID/File/descripciones                                             |
| Error técnico GET                               | PASS; intercept controlado distinto de empty, retry sólo GET                                                       |
| Desktop/mobile, teclado/foco/modal              | PASS smoke, sin overflow horizontal operacional                                                                    |
| 10 MiB individual y 25 MiB total exactos        | PASS; POST201 Apache y tamaños/hash físicos conformes                                                              |
| Excedentes de un byte                           | Rechazo frontend; no se presentan como nuevos rechazos server-side 422                                             |
| DB/storage/auditoría                            | Cuatro turnos incluyendo competidor D-01; seis documentos/asociaciones/archivos, hashes conformes y cero huérfanos |

**Período sin disponibilidad: PASS de presentación/UX mediante intercept controlado de GET; NO demuestra que MariaDB estuviera globalmente sin disponibilidad.** La repetición para comprobar navegación también fue autorizada. R1/R2/R3 provocaron conflictos reales sólo en fixtures sintéticos. No se apagó Apache ni MariaDB.

Evidencia ignorada en `storage/framework/testing/uc07-750-62237b5/`: `auditoria-final.json` (83 verificaciones), `verificacion.json`, `apache-final.json`, capturas, `network-*.jsonl`, snapshots y `postflight.json`. Network confirmó multipart; observación pasiva del FormData verificó pares archivo/descripcion, sin copiar cookies/CSRF ni cuerpos completos al cierre. SHA-256 y tamaños físicos de los seis archivos coinciden con los originales. Chrome se cerró tras verificar PID/perfil/puerto exclusivo 9340; sin listener posterior y Apache activo.

Replay y respuesta perdida no se repitieron manualmente en 7.50 conforme al alcance: conservan certificación backend/concurrencia previa. La matriz extensa V1 de 7.25/P6 permanece histórica en el anexo; no se presenta como una nueva matriz V2 íntegramente ejecutada.

### 8.3 Regresión global definitiva — 7.51

Reejecutada **sin filtros ni cambios productivos posteriores al commit**, después de versionar MC-03, sobre `ba97c4b8e0a1df53c1c456da424b549db9798828`:

```text
php artisan test
2231 tests PASS
14391 assertions
158,06 s
0 failures / 0 errors / 0 skipped / 0 risky / 0 warnings
exit code 0
```

Entorno efectivo comprobado con el cargador de PHPUnit y Laravel: `APP_ENV=testing`, sqlite, `:memory:`, PDO sqlite, `config_cached=false`. Sin utilizar `kinesiosoft_db`. La corrida incluyó y aprobó MC-01/MC-02 y la cobertura ampliada de MC-03 en `TurnosAccessibilityViewTest`: los cuatro headings siguen enfocables mediante sus referencias y `tabindex="-1"`, sin ring accidental según las assertions estructurales, preservando foco interactivo y stepper.

```text
node --check resources/js/turnos/solicitar-turno.js → PASS
node --check tests/JavaScript/solicitar-turno.test.js → PASS
node --test tests/JavaScript/solicitar-turno.test.js → 150 PASS / 0 FAIL / 0 SKIP; 455,409 ms; exit code 0
npm run build → PASS; 94 módulos; 3,55 s; exit code 0
```

Node/build encontraron `spawn EPERM` en sandbox; las ejecuciones autorizadas fuera del sandbox pasaron. Browserslist/caniuse-lite desactualizado fue informativo, sin actualizar dependencias. Pint y sintaxis PHP de MC-03 pasaron antes del commit; sin correcciones PHP durante la reejecución no se repitieron Pint ni `php -l`, conforme al prompt de 7.51. Diff-check limpio, sin nuevos cambios versionables, stage ni commit de certificación. Los cambios documentales preexistentes de 7.52 se preservaron byte a byte, comprobado mediante SHA-256 antes y después de la reejecución.

La corrida anterior sobre `62237b5` (2229 tests / 14347 assertions) es histórica. El baseline final vigente es **2231 / 14391** sobre `ba97c4b`; el total de tests se mantuvo respecto de la corrida previa a MC-03 y las assertions aumentaron en 20. No se volvió a ejecutar MariaDB ni navegador por cambios exclusivos de presentación.

## 9. Hitos Git del delta V2

Verificados mediante `git log 3a95e84..HEAD`. Certificaciones sin cambios no se presentan como commits nuevos.

| Hito                                           | Commit                                                     |
| ---------------------------------------------- | ---------------------------------------------------------- |
| Plan congelado / instrucciones                 | `956e68e` / `03672e0`                                      |
| Descripción / Request estructurado             | `95c1eb0` / `0c0233f`                                      |
| Pipeline / Action, atomicidad y replay         | `6e72bb5` / `9e27d26`                                      |
| Agregado / disponibilidad HTTP / profesionales | `0930cec` / `f0be979` / `fd127a9`                          |
| Integración backend V2-P2                      | `3c302de`                                                  |
| Configuración / estado wizard                  | `102f0e5` / `c52fbe2`                                      |
| Pasos 1 / 2 / 3 / 4 y multipart                | `602b2a4` / `f2984f9` / `bc5b413` / `3f16463`              |
| Recovery / D-01 y éxito                        | `b1102a9` / `4effe61`                                      |
| Mis turnos / responsive y accesibilidad        | `176b5a1` / `fa75cd9`                                      |
| Integración test / MariaDB V2                  | `eeeaaab` / `62237b5`                                      |
| MC-01/MC-02, hito histórico                    | `2b46d24`                                                  |
| MC-03, baseline definitivo                     | `ba97c4b`                                                  |
| 7.50 y reejecución 7.51                        | Certificaciones operativas, sin commit propio              |
| 7.52                                           | Cierre y estados documentales, sin stage/commit automático |

## 10. Fixtures, límites y cierre formal

Los fixtures se identifican con `UC07_750_A1F8129F`; quedaron conservados con sus evidencias ignoradas. No se modificaron personas/agendas reales ni se eliminaron registros preexistentes. UC07_725/UC07_P6/UC07_726 también se conservaron. SQLite global no depende de ellos. El cierre no autoriza limpieza: cualquier operación posterior exige delimitación y autorización propias.

Fuera de alcance siguen cancelación/reprogramación/asistencia, seguimiento solicitado por paciente, Padecimiento/Consulta/diagnóstico/plan, gestión clínica documental integral, notificaciones externas, equipos y scheduler de reconciliación. Son fronteras funcionales, no subpasos V2 sin completar. No se absorbió deuda histórica de Pint global ajena al incremento ni se afirma certificación normativa WCAG o compatibilidad universal.

**7.29–7.52 COMPLETADOS. V2-P1–V2-P6 PASS. UC-07 SOLICITAR TURNO V2 = CERRADO.** Documentación preparada sin stage para revisión/versionado del usuario; sin elegir siguiente caso de uso, preparar otro incremento ni repetir pruebas u operaciones de entorno durante 7.52.

## Anexo histórico V1

> Texto del cierre original de 7.28, fechado 18/10/2026 y referido a `3a95e84`. Se preserva su evidencia; sólo se subordinó el nivel de encabezados. Contratos V1 (orden, adjuntos[], recovery y cifras) supersedidos por V2 arriba. Referencias a documentos V1 y estados temporales de autorización/versionado describen aquel momento y no constituyen instrucciones actuales.

| Dato                               | Valor                                                                                                         |
| ---------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Caso de uso                        | UC–07 Solicitar turno                                                                                         |
| Rama                               | `feature/turnos-solicitar-turno`                                                                              |
| Commit base oficial                | `9d9b1510402e64c310f02ec1014b24333c13ed0c`                                                                    |
| HEAD técnico certificado           | `3a95e84b26a43fa90f663372ba0ba6c3192ff685` — `fix: conservar slot compatible al volver en solicitud de turno` |
| Fecha de cierre técnico/documental | 18/10/2026, referencia temporal America/Buenos_Aires                                                          |
| Estado                             | **IMPLEMENTADO Y CERTIFICADO**                                                                                |
| Puertas                            | **P1–P7 PASS**                                                                                                |
| Subpaso documental                 | 7.28; sin stage, commit ni push                                                                               |

`3a95e84` es el HEAD técnico previo a este documento. No es un commit de cierre documental: ese commit todavía no existe al redactar esta versión.

### 1. Propósito, autoridad y evidencia

Este cierre registra qué quedó implementado, sus componentes, la trazabilidad de decisiones, las pruebas certificadas y los límites del incremento. No reemplaza al caso de uso, las decisiones D/DT/UX, el Diccionario de Datos ni la planificación; tampoco introduce una especificación nueva.

La revisión documental contrastó, en este orden, el [Diccionario vigente][diccionario], la [fuente funcional UC–07][uc07], las [decisiones técnicas D/DT][tecnico], las [decisiones UX][ux], la [planificación ejecutable][plan], el código de `3a95e84`, Git y las evidencias locales ya certificadas. Las fuentes de `docs/_codex_sources/turnos/` son contexto local excluido de Git. Las referencias visuales no se utilizaron como autoridad de dominio o persistencia.

Las evidencias operativas citadas permanecen ignoradas bajo `storage/framework/testing/`; sus nombres de carpeta identifican el baseline del harness y no necesariamente un commit de un cambio posterior. Se consultaron en modo lectura. No se copiaron credenciales, datos personales, motivos clínicos, dumps ni IDs de fixtures a este cierre. En 7.28 no se ejecutaron suites, migraciones, flujos HTTP ni operaciones sobre las bases de datos.

### 2. Alcance final y frontera funcional

El paciente autenticado, verificado y con perfil requerido completo ingresa desde **Mis turnos**, informa un motivo obligatorio y documentación clínica opcional, elige una kinesióloga elegible, consulta disponibilidad, selecciona un intervalo, revisa el conjunto y confirma. La operación crea un `Turno` real de tipo `PRIMERA_CONSULTA`, estado `PENDIENTE`, con motivo, documentos, asociaciones y auditoría consolidados. Después del commit recibe feedback inmediato y puede localizar el turno en Mis turnos.

Primera consulta significa inicio de un nuevo episodio/ciclo; un paciente histórico puede volver a utilizar el flujo si D-01 lo permite. UC–07 registra el motivo expresado por el paciente, sin crear un episodio clínico persistido. **No existe una entidad persistente `SolicitudTurno`**, una aprobación intermedia ni un hold por seleccionar un horario. `PENDIENTE` ya reserva agenda.

Quedan fuera de alcance cancelación, reprogramación, confirmación posterior de asistencia, registro de asistencia/ausencia, reasignación, Consulta clínica, Padecimiento, diagnóstico, evaluación, plan terapéutico, programación de seguimiento, sobreturno, urgencia, emergencia y notificaciones externas. Tampoco se implementan ofertas de adelanto, reservas de equipos ni gestión clínica integral de documentos por padecimiento. Son fronteras del incremento o integraciones futuras de otros UC, no defectos pendientes de UC–07.

### 3. Trazabilidad funcional D-01…D-16

Los nombres de tests de esta matriz corresponden a archivos reales del incremento. Las familias de pruebas y sus rutas representativas se indican en la sección 15.

| Decisión | Resultado final                                                                                                                          | Implementación/evidencia                                                                                                                             |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| D-01     | Como máximo una Primera consulta futura que reserve agenda por paciente, independientemente de profesional o dimensión clínica.          | [ElegibilidadPrimeraConsultaService][d01], lectura bloqueante en la [Action][action]; `ElegibilidadPrimeraConsultaTest` y carreras D-01 Action/HTTP. |
| D-02     | Un paciente histórico puede iniciar un nuevo ciclo; el flujo sólo conserva el motivo.                                                    | Alta directa en la Action, sin crear Padecimiento/Consulta; aceptación `historico-mismo-dia.json` de 7.26.                                           |
| D-03     | La disponibilidad se consulta después de capturar motivo/archivos y elegir profesional; el vacío conserva el estado compatible.          | [Wizard JS][wizard], Blade de los pasos 1–3; pruebas Node y aceptación `ui-LOCAL.json`.                                                              |
| D-04     | Elegibilidad profesional dinámica separada de disponibilidad temporal.                                                                   | [ElegibilidadKinesiologaService][elegibilidad] y `User::puedeAtenderComoKinesiologo()`; `ElegibilidadKinesiologaTest`.                               |
| D-05     | Grilla determinista por franja, sin desplazarla por ocupación.                                                                           | [DisponibilidadPrimeraConsultaService][disponibilidad] y `GrillaPrimeraConsultaTest`.                                                                |
| D-06     | Intersecciones semiabiertas; bloqueos efectivos y estados canónicos ocupantes. Cancelado libera.                                         | [AgendaQueryService][agenda], `EstadoTurnoEnum::estadosQueReservanAgenda()`; tests de Agenda y disponibilidad.                                       |
| D-07     | Sólo `inicio_at > ahora`; igualdad y pasado no son reservables. Sin buffer.                                                              | `RelojAplicacion`, evaluación compartida de candidatos y revalidación en la Action; disponibilidad/Action y aceptación de mismo día.                 |
| D-08     | Sin horizonte funcional máximo; siete días por ventana UI y hasta 31 por consulta backend, configurable.                                 | Configuración `config/turnos.php`, Request y motor de disponibilidad; aceptación hasta 13/12/2026.                                                   |
| D-09     | Adjuntos opcionales, máximo 5; 10 MiB individual y 25 MiB total; PDF/JPEG/PNG según MIME real.                                           | [Política documental][politica], Request, storage privado; límites exactos en tests y Apache 7.25/P6.                                                |
| D-10     | Motivo y original documental protegidos después de confirmar; el flujo no reemplaza retroactivamente el conjunto.                        | Eventos de `Turno`, `DocumentoClinico` y `DocumentoClinicoOrigenTurno`; tests de Models y atomicidad.                                                |
| D-11     | Ver un slot no lo reserva; confirmación autoritativa, mutex, D-01 e idempotencia.                                                        | Action y suite MariaDB con procesos independientes; doble submit y recuperación en navegador/Node.                                                   |
| D-12     | Turno, documentos, asociaciones y auditorías forman un agregado todo-o-nada; filesystem con compensación y tratamiento de incertidumbre. | Action, operación de almacenamiento y reconciliador; `AtomicidadPrimeraConsultaTest`, carreras y compensación Apache.                                |
| D-13     | Se crea directamente un `Turno` `PENDIENTE`, que ocupa agenda.                                                                           | Action y `SolicitarTurnoHttpTest`; no tabla ni Model de solicitud intermedia.                                                                        |
| D-14     | `PRIMERA_CONSULTA` fijado por servidor, separado del estado operativo.                                                                   | `TipoTurnoEnum`, cast de Turno y Action; tests de enum/Model/HTTP.                                                                                   |
| D-15     | Feedback visual después de consolidar, seguido de acceso al turno registrado en Mis turnos. Sin email/SMS/push.                          | Éxito 201 y replay 200, SweetAlert2 y `redirect_url`; pruebas Node, navegador y aceptación.                                                          |
| D-16     | Entrada, captura, selección, revisión, confirmación y visibilidad posterior mínimas, sin absorber otros UC.                              | Cinco rutas específicas, Mis turnos acotado y revisión de alcance en integración/aceptación.                                                         |

### 4. Trazabilidad técnica DT-01…DT-07

| Decisión técnica | Implementación final                                                                                                                                                  | Evidencia                                                                                                                 |
| ---------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| DT-01            | `turnos.tipo` continúa como string(30); `TipoTurnoEnum` y cast Eloquent. Fuente única de estados ocupantes en `EstadoTurnoEnum`.                                      | Enums, `Turno`, factories consumidoras; `TipoTurnoEnumTest`, `EstadoTurnoEnumTest`, `TurnoModelTest`, Agenda.             |
| DT-02            | `ElegibilidadKinesiologaService` reutiliza el predicado canónico de User y prepara relaciones autoritativas para evaluación bloqueante. Sin flag `habilitada`.        | User, servicio y tests de elegibilidad; lecturas actuales verificadas en MariaDB.                                         |
| DT-03            | `DisponibilidadPrimeraConsultaService` consume Agenda y `RelojAplicacion`; slots derivados y mismo motor para mostrar/revalidar. Sin slots/holds persistidos.         | Tests de grilla/disponibilidad/Agenda, Action y concurrencia.                                                             |
| DT-04            | Dos tablas documentales, Models, política central, storage privado, metadata original e inmutabilidad.                                                                | Tres migrations del incremento, esquema/Models, política/storage, descarga y matriz Apache.                               |
| DT-05            | `ConfirmarPrimeraConsultaAction` con transacción, mutex de kinesióloga, lock de paciente y lecturas bloqueantes posteriores.                                          | `ConfirmarPrimeraConsultaConcurrenciaTest`, `PrimeraConsultaHttpConcurrencyTest`, MariaDB/InnoDB.                         |
| DT-06            | UUID por intención, UNIQUE nullable, replay previo y transaccional, sin retry automático del callback; archivos compensables, advisory locks y reconciliación segura. | `ReplayPrimeraConsultaService`, operación documental, atomicidad, tests de locks/reconciliador y resultado incierto real. |
| DT-07            | Controllers/Requests, middleware de paciente, contratos 200/201/409/422, Mis turnos y descarga privada con Policy/auditoría.                                          | Rutas reales, tests HTTP/Policy/descarga/integración, wizard y aceptación.                                                |

### 5. Componentes principales y responsabilidades

| Responsabilidad                  | Componente canónico                                                                                                             |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Elegibilidad profesional         | `User::puedeAtenderComoKinesiologo()` y `ElegibilidadKinesiologaService`                                                        |
| Regla D-01                       | `ElegibilidadPrimeraConsultaService`                                                                                            |
| Grilla y disponibilidad          | `DisponibilidadPrimeraConsultaService`, consumiendo Agenda existente y `RelojAplicacion`                                        |
| Política de adjuntos             | `PoliticaDocumentosClinicosService` y `config/documentos_clinicos.php`                                                          |
| Escritura privada y compensación | `AlmacenamientoDocumentosClinicosService` y `Documentos/OperacionAlmacenamientoDocumental`                                      |
| Registro documental y asociación | `GestionDocumentoClinicoService`, `DocumentoClinico`, `DocumentoClinicoOrigenTurno`                                             |
| Confirmación del agregado        | `ConfirmarPrimeraConsultaAction`, `ResultadoConfirmacionPrimeraConsulta`, `ReplayPrimeraConsultaService`                        |
| HTTP de solicitud                | `SolicitarTurnoController`, `ConsultarDisponibilidadPrimeraConsultaRequest`, `ConfirmarPrimeraConsultaRequest`                  |
| Listado propio y destino         | `MisTurnosController`, vistas `resources/views/turnos/mis-turnos/`                                                              |
| Descarga                         | `DescargarDocumentoClinicoController`, `DocumentoClinicoPolicy`                                                                 |
| Frontend                         | Vistas `resources/views/turnos/solicitar/`, módulo `resources/js/turnos/solicitar-turno.js`, Alpine/Vite/SweetAlert2 existentes |
| Mantenimiento documental         | `ReconciliadorDocumentosClinicosService`, `ReconciliarDocumentosClinicosCommand`, coordinador documental MariaDB                |

Se conserva el patrón Controller → Form Request → Action → transacción → Models → `AuditoriaService`, con servicios específicos reutilizables. El harness de procesos, barreras y base de testing no forma parte de la arquitectura productiva.

### 6. Persistencia y migraciones finales

#### 6.1 Turnos

La columna preexistente `tipo` conserva su tipo físico string(30). Los cases respaldados por string son `TipoTurnoEnum::PRIMERA_CONSULTA = 'PRIMERA_CONSULTA'` y `SEGUIMIENTO = 'SEGUIMIENTO'`; Turno castea tipo y estado a sus enums. No se introdujo native ENUM ni CHECK de tipo.

Se agregó `clave_idempotencia` mediante `uuid()->nullable()->unique()`: representación Laravel UUID portable, `CHAR(36)` en MariaDB, sin default generado, sin PK/FK. Se admiten múltiples NULL y se rechaza físicamente una UUID duplicada.

| Índice nuevo                                     | Columnas                               | Unicidad y finalidad                                   |
| ------------------------------------------------ | -------------------------------------- | ------------------------------------------------------ |
| `turnos_clave_idempotencia_unique`               | `clave_idempotencia`                   | UNIQUE; impedir duplicación de la intención persistida |
| `turnos_paciente_id_tipo_estado_inicio_at_index` | `paciente_id, tipo, estado, inicio_at` | No único; soporte de consulta D-01                     |

El índice temporal preexistente `(kinesiologa_id, inicio_at, fin_at)` y las FKs a paciente/profesional se conservaron. No se utilizó UNIQUE sobre inicio ni sobre profesional/inicio para resolver overlaps. En la inspección física de 7.24, InnoDB dejó de mostrar el índice implícito redundante de `paciente_id` al quedar cubierto por D-01; la FK permaneció intacta. Es un detalle físico documentado, no una modificación del contrato de Agenda.

#### 6.2 Documentos clínicos

La definición física complementada por la microdecisión aprobada MD-7.03-01 quedó materializada en las migrations, sin nuevas entidades clínicas:

| Columna de `documentos_clinicos`                        | Tipo/longitud                                      | Restricción                                                |
| ------------------------------------------------------- | -------------------------------------------------- | ---------------------------------------------------------- |
| `id`                                                    | `id()`, BIGINT UNSIGNED autoincremental en MariaDB | PK, NOT NULL                                               |
| `paciente_id`                                           | `foreignId()`, BIGINT UNSIGNED                     | NOT NULL, FK a `pacientes.id`                              |
| `cargado_por_user_id`                                   | `foreignId()`, BIGINT UNSIGNED                     | NOT NULL, FK a `users.id`                                  |
| `origen`, `estado_validacion`, `tipo_documento_clinico` | Cada una string(30)                                | NOT NULL, sin defaults SQL de dominio                      |
| `nombre_original`                                       | string(255)                                        | NOT NULL                                                   |
| `ruta_storage`                                          | string(255)                                        | NOT NULL, UNIQUE `documentos_clinicos_ruta_storage_unique` |
| `mime_type`                                             | string(100)                                        | NOT NULL; separado del tipo clínico                        |
| `tamanio_bytes`                                         | `unsignedBigInteger()`, BIGINT UNSIGNED            | NOT NULL, sin default arbitrario                           |
| `created_at`, `updated_at`                              | Cada una `timestamp()->useCurrent()`               | NOT NULL, DEFAULT CURRENT_TIMESTAMP                        |

Los timestamps siguen el patrón certificado con MariaDB 10.4 y `explicit_defaults_for_timestamp=OFF`. La aplicación asigna los valores documentales iniciales; no hay defaults SQL `SOLICITUD_TURNO`, `PENDIENTE` o `SIN_CLASIFICAR`. No se guarda binario, URL pública ni FK directa a turno, padecimiento, profesional o historia clínica en la tabla maestra.

`documentos_clinicos_origen_turno` contiene exactamente dos columnas NOT NULL compatibles con BIGINT UNSIGNED:

- `documento_clinico_id`: PK compartida y FK a `documentos_clinicos.id`.
- `turno_id`: FK a `turnos.id`, sin UNIQUE.

No tiene id artificial, timestamps ni metadata duplicada. Las cuatro FKs nuevas usan **RESTRICT ON DELETE y RESTRICT ON UPDATE**. Sus nombres convencionales son `documentos_clinicos_paciente_id_foreign`, `documentos_clinicos_cargado_por_user_id_foreign`, `documentos_clinicos_origen_turno_documento_clinico_id_foreign` y `documentos_clinicos_origen_turno_turno_id_foreign`; InnoDB mantiene los índices de soporte correspondientes.

Cardinalidades: paciente 1 → 0..N documentos; usuario 1 → 0..N documentos cargados; documento 1 → 0..1 origen-turno; turno 1 → 0..N documentos de origen. No se crearon `documentos_clinicos_padecimiento`, slots, holds ni solicitudes intermedias.

#### 6.3 Tres migrations y aplicación autorizada

Todas están en `database/migrations/`; Git confirma sus commits de origen:

| Migration exacta                                                       | Origen    | Propósito / reversión                                                          |
| ---------------------------------------------------------------------- | --------- | ------------------------------------------------------------------------------ |
| `2026_10_16_000001_add_idempotencia_and_d01_index_to_turnos_table.php` | `10906e2` | Columna UUID y dos índices; down elimina sólo esos índices y la columna nueva. |
| `2026_10_16_000002_create_documentos_clinicos_table.php`               | `8c2c835` | Tabla maestra documental; down elimina exclusivamente esa tabla.               |
| `2026_10_16_000003_create_documentos_clinicos_origen_turno_table.php`  | `8c2c835` | Satélite dependiente; down elimina exclusivamente el satélite.                 |

No se reescribieron migrations históricas. El rollback natural revierte primero el satélite y luego la maestra; no fue una operación de cierre sobre desarrollo.

En 7.24 se aplicaron exclusivamente estas tres migrations, por paths individuales, después del preflight/pretend y de autorización explícita sobre el entorno local `kinesiosoft_db`. Quedaron **46 Ran / 0 Pending**, batches 21, 22 y 23 para estas migrations. La metadata física MariaDB resultó conforme; no se insertaron fixtures para comprobarla.

La conservación antes/después se comprobó con counts e igual digest SHA-256 sobre las mismas columnas preexistentes de Turno. Ese snapshot tenía **cero turnos**: prueba que la aplicación DDL no agregó/alteró filas en ese conjunto vacío; no se presenta como ensayo sobre una población histórica no vacía. Los counts de users/pacientes/kinesiólogas permanecieron iguales. Evidencias: `uc07-724-729b0dd/{migrate-status-after.txt,schema-verification.json,digest-before.json,digest-after.json}` bajo el root local de testing. No se ejecutó ninguna migration en 7.28.

### 7. Elegibilidad, D-01 y disponibilidad

#### 7.1 Elegibilidad profesional

`User::puedeAtenderComoKinesiologo()` exige rol `KINESIOLOGO`, cuenta activa, email verificado, datos personales completos y perfil profesional completo. Los datos personales canónicos son DNI, fecha de nacimiento, sexo y teléfono. El perfil profesional requiere kinesióloga asociada con título, al menos una matrícula activa y una especialidad asociada.

No se agregó un flag persistente `habilitada`, vencimiento de matrícula ni requisito de actividad actual del catálogo histórico de título/especialidad. La profesional puede ser elegible y carecer de disponibilidad. Se reutiliza el mismo predicado para listar, consultar y confirmar; la confirmación prepara relaciones mediante lecturas bloqueantes actuales, sin confiar en relaciones cargadas antes de esperar los locks.

#### 7.2 Una sola definición de D-01

`ElegibilidadPrimeraConsultaService::primerasConsultasFuturasActivasDe(Paciente)` devuelve el Builder con cuatro predicados:

1. `paciente_id` del paciente autenticado evaluado.
2. `tipo = TipoTurnoEnum::PRIMERA_CONSULTA`.
3. `estado IN EstadoTurnoEnum::estadosQueReservanAgenda()`.
4. `inicio_at > RelojAplicacion::ahora()`.

El helper canónico contiene exactamente `PENDIENTE` y `CONFIRMADO`. `EN_ATENCION`, `ATENDIDO`, `AUSENTE` y `CANCELADO` no bloquean D-01; tampoco Seguimiento, otro paciente, un inicio pasado o exactamente igual a ahora. No intervienen profesional, diagnóstico, motivo, documentos ni padecimiento.

El chequeo preliminar usa `exists()` sobre ese Builder en la entrada y en el estado del CTA de Mis turnos. La Action reutiliza los mismos predicados con `lockForUpdate()->get()` después de bloquear paciente/profesional. **No se utiliza `lockForUpdate()->exists()` como garantía concurrente.** El índice D-01 facilita la consulta; la regla semántica completa depende además de los locks y revalidaciones.

Una intención distinta en otra pestaña o con otra profesional queda bloqueada si la primera consolida. En POST se informa `409 primera_consulta_activa`; la UI muestra feedback bloqueante y dirige a Mis turnos. Un replay válido del propio turno se resuelve antes de D-01.

#### 7.3 Algoritmo de disponibilidad

El motor resuelve la configuración no anulada aplicable a **cada fecha objetivo**, con sus franjas y duración. No extrapola una configuración donde no existe una aplicable; la configuración abierta continúa según las reglas temporales de Agenda.

Para cada franja del día genera candidatos desde `hora_inicio`, avanzando exactamente `duracion_turno_minutos`. Cada intervalo debe estar completamente contenido: puede terminar justo en `hora_fin` y se descarta el remanente insuficiente. Las franjas se procesan independientemente, sin fusionarlas ni desplazar/recompactar la grilla por ocupaciones.

Sobre esos candidatos aplica intersección semiabierta: `inicioA < finB && finA > inicioB`. Dos intervalos que sólo se tocan no se solapan. Filtra bloqueos no anulados, turnos en estados canónicos ocupantes y candidatos cuyo inicio no sea estrictamente posterior al reloj. No hay buffer. La confirmación vuelve a aplicar el mismo motor; deriva `fin_at` desde la configuración y no acepta ese dato como autoridad del cliente.

La consulta obtiene ocupaciones y bloqueos por ventana, sin SQL por slot; las configuraciones se resuelven por fecha. La respuesta del paciente contiene sólo intervalos disponibles, sin causas de ocupación ni identidades. Una configuración persistida incoherente produce fallo técnico, no un falso vacío o conflicto genérico.

La UI navega por semanas de siete días. El backend limita cada request a un máximo configurable de **31 fechas inclusivas**. Es un límite de tamaño de consulta, no de anticipación: la aceptación llegó al 13/12/2026 recorriendo semanas. Las comparaciones de dominio reutilizan `RelojAplicacion` y la timezone de configuración.

### 8. Confirmación, concurrencia e idempotencia

#### 8.1 Transacción y lecturas actuales

Toda confirmación pasa por `ConfirmarPrimeraConsultaAction`. Después del replay rápido, una creación nueva entra en `DB::transaction(..., attempts: 1)` y adquiere los locks de negocio en orden **kinesióloga → paciente**. El primero actúa como mutex de agenda y el segundo protege D-01 entre profesionales diferentes.

Después de los locks relee UUID consolidada, D-01, usuario/roles/perfil profesional, configuración aplicable, franjas, turnos ocupantes y bloqueos. Se usan lecturas bloqueantes explícitas y ordenadas en las consultas pertinentes. No se presume que bloquear el padre bloquee automáticamente relaciones/eager loads ni que `refresh()` resuelva por sí solo el snapshot de REPEATABLE-READ.

La Action comprueba grilla exacta, futuro estricto, ocupación y motivo; fija paciente desde el actor, tipo/estado y fin del intervalo. Guarda turno, documentos, asociaciones y auditorías antes de retornar éxito. Las operaciones de Agenda que afectan ocupación deben respetar el mismo convenio de mutex por kinesióloga.

Las pruebas reales usaron MariaDB 10.4.32/InnoDB, **REPEATABLE-READ**, procesos PHP y conexiones independientes, observador y barreras deterministas, sin transacción envolvente de PHPUnit. Observaron esperas InnoDB y la lectura del estado consolidado después de esperar. Incluyeron mismo slot, D-01 entre profesionales, misma UUID, UUID distintas, cambio concurrente de Agenda/bloqueos y multipart. Deadlock y timeout fueron fallos controlados de prueba, con rollback/compensación y retry explícito, no comportamiento habitual esperado. SQLite no se tomó como certificación de locking.

#### 8.2 UUID y replay

El GET `create` genera una UUID en servidor y la entrega al wizard. Identifica una intención, no un horario: se mantiene al navegar, cambiar profesional/slot, corregir entradas y reintentar. Una nueva entrada efectiva al flujo puede recibir otra UUID.

La UNIQUE nullable impide duplicar la clave física. `ReplayPrimeraConsultaService` usa el PDO de escritura y valida que el turno pertenezca al mismo paciente y contexto `PRIMERA_CONSULTA`; una clave ajena/incompatible se rechaza sin revelar el turno ajeno. Un replay devuelve el original, sin recrear documentos ni auditar ficticiamente una creación.

Request y Action separan identidad/UUID, replay consolidado y validación de nueva creación. Un replay legítimo no vuelve a exigir archivos, motivo ni condiciones mutables actuales de profesional, fecha o D-01. La UUID se rechequea con lectura bloqueante dentro de la transacción antes de crear. Las colisiones reconocidas se resuelven contra el turno consolidado; otros errores de integridad no se convierten en indisponibilidad.

Una segunda UUID representa otra intención y queda sometida a D-01 y ocupación. El doble click se limita en UI, pero la exclusión de duplicados y carreras también está garantizada en backend. No hay retry automático del callback que escribe archivos.

#### 8.3 Atomicidad DB + filesystem y resultado incierto

No existe transacción distribuida DB/filesystem. La operación documental valida el conjunto, genera una ruta privada por archivo y adquiere su advisory lock **antes** de hacerlo visible. Registra cada ruta propia antes de abrir/escribir, incluyendo posibles escrituras parciales del adapter. El nombre original es metadata, nunca una ruta controlada por cliente.

Con rollback confirmado y ausencia de referencias, se compensan únicamente archivos nuevos de esa operación y se liberan los locks. No se borran archivos anteriores. Una falla de cleanup/release se informa conservando la causa original; un fallo de release después de commit confirmado no dispara compensación del agregado consolidado.

Ante error alrededor de commit, la Action verifica consolidación por UUID y referencias del conjunto documental en la sesión PDO exacta, sin reconectar ni reutilizar un snapshot transaccional activo. Si confirma el agregado, conserva los archivos y devuelve el original como replay. Si comprueba rollback/sin referencias propias, compensa. Si no puede determinarlo de manera fiable, conserva los archivos, libera la coordinación y expresa resultado incierto; no los elimina a ciegas. Un residuo no referenciado puede reconciliarse posteriormente de forma segura.

Cuando el backend consolida pero la respuesta se pierde, la UI conserva UUID, motivo, File objects, profesional y slot y ofrece retry manual. El segundo intento usa la misma UUID y obtiene replay 200. La aceptación comprobó commit real previo y un único turno/conjunto documental final; no se sustituyó por un POST que nunca alcanzara al backend.

### 9. Documentos clínicos: política, original y acceso

#### 9.1 Política y storage

Los adjuntos son opcionales: cero es válido, con máximo **5 archivos**, **10 MiB (10 × 1024² bytes)** por archivo y **25 MiB (25 × 1024² bytes)** por confirmación. Se aceptan PDF, JPEG/JPG y PNG mediante los MIME `application/pdf`, `image/jpeg`, `image/png`. Se comprueban `UploadedFile`, estado real del upload, tamaños en bytes y MIME detectado desde el contenido; extensión o MIME declarado por el cliente no son autoridad.

`PoliticaDocumentosClinicosService` separa errores globales de colección e índices individuales. El Request los traduce a `adjuntos` / `adjuntos.N`; el servicio de almacenamiento vuelve a aplicar la política. La validación del navegador es auxiliar.

El disco local `documentos_clinicos` está en `storage/app/private/documentos_clinicos`, con visibilidad privada, `serve=false` y errores de escritura explícitos. Los originales se guardan bajo `originales/<UUID>` generada por backend, sin URL pública ni pre-upload/staging persistente.

`GestionDocumentoClinicoService` registra dentro de la transacción del turno el paciente, usuario cargador y asociación. Usa las constantes reales de `DocumentoClinico`: `ORIGEN_SOLICITUD_TURNO = SOLICITUD_TURNO`, `ESTADO_VALIDACION_PENDIENTE = PENDIENTE` y `TIPO_SIN_CLASIFICAR = SIN_CLASIFICAR`. Cargar un archivo no equivale a validarlo clínicamente.

#### 9.2 Protección del original

Los eventos Eloquent de instancia rechazan con `LogicException` cambios posteriores de paciente/cargador/origen/nombre/ruta/MIME/tamaño del documento y su eliminación. El satélite rechaza actualización y eliminación. Se permite evolución futura de `estado_validacion` y `tipo_documento_clinico` sin sobrescribir el original; UC–07 no incorpora el flujo clínico que la gestionará.

Para un Turno cuyo **raw original persistido** sea `PRIMERA_CONSULTA`, MD-7.04-01 protege tanto `motivo_solicitud` como `tipo`. `getRawOriginal('tipo')` evita que el valor dirty entrante habilite el bypass por cambio simultáneo o dos updates sucesivos. No se hizo tipo globalmente inmutable para Seguimiento ni se bloquearon otros atributos por esta regla.

La protección se implementa mediante eventos de Models y caminos de escritura del incremento; no mediante trigger ni garantía universal ante SQL directo, updates masivos o eventos silenciados. Esos mecanismos no son vías autorizadas para modificar el original. Los tests de Models certifican invariantes y la revisión de consumidores evita introducir esos bypasses en UC–07. La confirmación es el único flujo publicado de alta del conjunto; no hay endpoint para reemplazarlo retroactivamente.

#### 9.3 Descarga privada y autorización

El endpoint de descarga aplica middleware y `DocumentoClinicoPolicy::download`: paciente propietario o kinesióloga asociada al turno de origen. ADMINISTRADOR/SUPERADMIN no reciben lectura clínica sólo por su rol. La descarga no usa `perfil.paciente`, porque debe poder autorizar al profesional correspondiente. Mis turnos, en cambio, siempre parte del paciente del usuario autenticado.

Después de autorizar, `DescargarDocumentoClinicoController` valida el namespace de ruta y abre un stream privado; no expone el path físico. Sirve attachment con nombre seguro, MIME/tamaño, `private, no-store` y `nosniff`. Audita el acceso después de obtener un recurso legible. Una denegación o archivo inexistente no produce auditoría ficticia de descarga; HEAD no se procesa como descarga efectiva. Se verificaron acceso propio, profesional correspondiente y denegación ajena en tests, además de descarga Apache con bytes/hash y Content-Disposition.

#### 9.4 Reconciliación segura de huérfanos

`ReconciliarDocumentosClinicosCommand` expone `php artisan documentos-clinicos:reconciliar-huerfanos`: **dry-run por defecto**. Sólo `--delete` solicita eliminación. Se documenta su operación; no se ejecutó durante el cierre ni se agregó scheduler.

`ReconciliadorDocumentosClinicosService` opera exclusivamente en el namespace clínico configurado, sobre archivos regulares de nombre UUID y antigüedad de al menos **24 horas** por defecto (`reconciliacion.gracia_horas`, configurable). No recorre enlaces ni subdirectorios ajenos; comprueba componentes, lstat y contención real del path. La protección de symlinks está implementada; el test físico depende de privilegios de plataforma y no se afirma creación física de symlinks donde Windows la impidió.

El reconciliador exige una sesión sin transacción envolvente, con autocommit en MariaDB. Antes de considerar borrado intenta adquirir **el mismo advisory lock de la ruta de forma no bloqueante**. Revalida archivo/edad, consulta inmediatamente referencia DB en el PDO capturado y elimina sólo si sigue siendo candidato no referenciado. DB no disponible, cambio de sesión o coordinación incierta abortan el recorrido; registra conteos/etapa de error y el Command retorna fallo. No es un recolector general de storage.

`CoordinadorBloqueoDocumentalMariaDb` usa `GET_LOCK`/`RELEASE_LOCK` sobre la misma sesión PDO capturada; el nombre deriva del namespace aplicación/base y ruta, sin datos clínicos. El lock de sesión sobrevive commit/rollback, por lo que se libera explícitamente después de resolver el efecto documental. No se sustituyó esta estrategia por tablas o locks persistidos.

La carrera real de `ReconciliacionDocumentalTest` mantuvo al escritor activo, envejeció artificialmente el archivo 25 horas y ejecutó el reconciliador desde otra conexión: el archivo siguió protegido por el lock. Después de COMMIT se conservó por referencia. Después de ROLLBACK con residuo por compensación fallida se eliminó en una ejecución posterior segura. Se comprobó liberación explícita incluso con el worker todavía vivo y ausencia de locks/workers remanentes al terminar.

### 10. Rutas, middleware y contratos HTTP

Contrastado con [routes/web.php][rutas]. Las cinco rutas pertenecen al grupo web de Laravel, incluido el tratamiento CSRF del POST, además del middleware explícito indicado:

| Método / URI                                     | Nombre real                       | Controller                                      | Middleware explícito                                       |
| ------------------------------------------------ | --------------------------------- | ----------------------------------------------- | ---------------------------------------------------------- |
| GET `/turnos/mis-turnos`                         | `turnos.mis-turnos.index`         | `MisTurnosController::index`                    | `auth`, `verified`, `perfil.completo`, `perfil.paciente`   |
| GET `/turnos/solicitar`                          | `turnos.solicitar.create`         | `SolicitarTurnoController::create`              | `auth`, `verified`, `perfil.completo`, `perfil.paciente`   |
| GET `/turnos/solicitar/disponibilidad`           | `turnos.solicitar.disponibilidad` | `SolicitarTurnoController::disponibilidad`      | `auth`, `verified`, `perfil.completo`, `perfil.paciente`   |
| POST `/turnos/solicitar`                         | `turnos.solicitar.store`          | `SolicitarTurnoController::store`               | `auth`, `verified`, `perfil.completo`, `perfil.paciente`   |
| GET `/documentos-clinicos/{documento}/descargar` | `documentos-clinicos.download`    | `DescargarDocumentoClinicoController::__invoke` | `auth`, `verified`, `perfil.completo` + Policy del recurso |

Disponibilidad acepta `kinesiologa_id`, `desde`, `hasta`; devuelve 200 con `slots` incluso cuando el conjunto está vacío. Confirmación admite únicamente `motivo_solicitud`, `kinesiologa_id`, `inicio_at`, `clave_idempotencia`, `adjuntos[]` y el token de transporte CSRF. El servidor deriva paciente, tipo, estado, fin, cargador, valores documentales, ruta, MIME y tamaño. Para creación nueva el motivo es obligatorio, máximo 500 caracteres. No se promueven campos ajenos a la whitelist a datos validados.

| Resultado                                      | HTTP / cuerpo relevante                             |
| ---------------------------------------------- | --------------------------------------------------- |
| Disponibilidad válida                          | 200, `slots: [...]` o `[]`                          |
| Entrada/archivos inválidos                     | 422, `errors` por campo/índice                      |
| D-01 incumplida                                | 409, `code: primera_consulta_activa`, `message`     |
| Profesional no elegible                        | 409, `code: profesional_no_elegible`, `message`     |
| Slot vencido/fuera de grilla/ocupado/bloqueado | 409, `code: slot_no_disponible`, `message`          |
| Alta nueva                                     | 201, `turno_id`, `es_replay: false`, `redirect_url` |
| Replay consolidado                             | 200, mismo turno, `es_replay: true`, `redirect_url` |

El frontend ramifica por códigos, no por mensajes humanos. Errores técnicos/SQL inesperados no se disfrazan como 409 o vacío. Auth/sesión expirada se distinguen de 422 y conflictos. `redirect_url` incluye la pista del turno para localizarlo dentro del listado propio paginado, sin permitir consultar turnos ajenos.

### 11. Auditoría, seguridad y privacidad

Se reutiliza `AuditoriaService`, módulo `Turnos`, con tres eventos reales y whitelist explícita:

| Evento                          | Momento / metadata permitida                                                                             |
| ------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `turno_primera_consulta_creado` | Dentro de la transacción; turno, paciente, profesional, tipo, estado e intervalo                         |
| `documento_clinico_cargado`     | Dentro de la misma transacción; documento, turno, paciente, origen, estado/tipo documental, MIME y bytes |
| `documento_clinico_descargado`  | Después de autorizar y abrir un recurso legible; documento, paciente, origen y turno                     |

No se duplican binarios, contenido del documento ni motivo clínico completo. Replay no repite auditorías de creación/carga. Middleware y Policy delimitan entrada y recursos; el paciente sólo ve sus turnos, no causas internas de ocupación/locking ni documentos ajenos. Las verificaciones respaldan estos controles concretos, sin formular una afirmación general de seguridad total.

### 12. Wizard final y recuperaciones UX

#### 12.1 Recorrido y captura local — UX-01

El wizard Blade/Tailwind/Alpine es una sola página de cuatro pasos:

1. **Motivo y documentación**: texto y archivos opcionales; agregar/quitar/reemplazar antes de confirmar.
2. **Elegir profesional**: selección entre elegibles; vacío contextual si no hay ninguna.
3. **Fecha y horario**: semanas, estados loading/vacío/error y selección de un intervalo real.
4. **Revisar y confirmar**: Primera consulta, profesional, fecha, horario, motivo completo, conjunto exacto de documentos y estado inicial Pendiente; acción **Confirmar turno**.

El estado y los File objects permanecen en memoria del navegador hasta el POST final mediante `fetch` + `FormData`; sólo se envían los archivos vigentes como `adjuntos[]`, sin Base64 ni draft server-side. La UUID proviene del GET y se conserva durante las recuperaciones. Cambiar profesional invalida disponibilidad/slot, sin borrar motivo ni archivos. Las consultas usan cancelación/secuencia y descartan respuestas obsoletas incluso si llegan fuera de orden.

Durante el POST se bloquean navegación, mutaciones y doble envío. Una respuesta 201/200 debe cumplir el contrato antes de anunciar éxito. Se muestra un único feedback global, se desactiva el aviso de abandono y se navega a `redirect_url`; replay usa la misma semántica de éxito. Mis turnos presenta tipo, profesional, fecha/hora y estado, con próximos/históricos paginados de diez en diez y localización del turno recién creado. No incorpora acciones de otros UC.

#### 12.2 Recuperación contextual — UX-02

| Condición                                                | Estado que se conserva                                        | Invalidación / destino                                                                                                   |
| -------------------------------------------------------- | ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| D-01 durante el flujo, `409 primera_consulta_activa`     | No se ofrece corregir esa intención dentro del wizard         | Feedback bloqueante y Mis turnos; sin segundo turno ni doble aviso de abandono                                           |
| `409 slot_no_disponible`                                 | Motivo, archivos, UUID, profesional y ventana                 | Limpia slot, vuelve al Paso 3 y refresca; mensaje: “Ese horario acaba de dejar de estar disponible. Elegí otro horario.” |
| `409 profesional_no_elegible`                            | Motivo, archivos y UUID                                       | Invalida profesional/slots/slot, Paso 2; no permite reutilizar la profesional obsoleta                                   |
| 422 motivo                                               | Resto compatible, archivos y UUID                             | Paso 1, error inline y foco contextual                                                                                   |
| 422 documentos global o `adjuntos.N`                     | UUID y conjunto local; otros File válidos permanecen intactos | Paso 1; error global o marca del índice de esa tentativa, sin culpar un archivo arbitrario ni avanzar con inválidos      |
| Error de disponibilidad                                  | Motivo, archivos, profesional, ventana y UUID                 | Paso 3 con retry; no se interpreta como semana vacía ni se conserva ciegamente un slot                                   |
| Red sin consolidación observable                         | Estado local y UUID                                           | Mensaje prudente y retry manual; no anuncia éxito                                                                        |
| Respuesta perdida después de commit / resultado incierto | UUID, motivo, File objects, profesional y slot                | Retry manual con la misma UUID; la UI no presupone si se registró                                                        |
| Sesión expirada 401/403/419                              | Estado disponible mientras la página permanezca abierta       | Bloquea confirmación y pide reingreso; sin auto-retry ni falso éxito                                                     |
| 2XX malformado, HTML u otro fallo técnico                | Formulario y UUID                                             | Resultado incierto/técnico; sin SweetAlert de éxito ni redirect                                                          |
| Replay válido 200                                        | Turno original consolidado                                    | Éxito normal y retorno a Mis turnos, sin duplicados                                                                      |

El abandono voluntario con datos significativos solicita confirmación; no hay guardado de borrador. El redirect exitoso desactiva ese aviso. Los mensajes de recuperación no revelan causas internas ni información de terceros.

#### 12.3 Corrección detectada en 7.26

La única corrección funcional de la aceptación final quedó en **`3a95e84 — fix: conservar slot compatible al volver en solicitud de turno`**. Al volver **3 → 2 → 3** con la misma profesional se reconsulta y se restaura la selección únicamente si **el mismo inicio y fin** siguen en la respuesta vigente. Si el intervalo desapareció se invalida; si la consulta falla no se conserva ciegamente. Cambiar de profesional sigue invalidando el slot.

El commit modifica sólo el módulo JS y su test. Agregó tres casos de regresión: **vigente, retirado y error**. La suite Node final quedó en 60 PASS; no se cambió dominio, transporte documental ni backend para esta corrección.

#### 12.4 Responsive y accesibilidad comprobados — UX-03

Se verificaron desktop y tamaños **375×667** y **320×568**, sin overflow horizontal operativo del recorrido. Las comprobaciones de 7.22/7.26 abarcaron navegación por teclado, foco visible al cambiar pasos y ante errores, controles seleccionables, labels/fieldset/legend, errores asociados, `aria-invalid`, estados de carga comunicados y selección no dependiente únicamente del color. El feedback global y contextual conservó un destino de foco utilizable.

La evidencia real está en `uc07-722-6579882/{responsive.json,keyboard-final.json}` y en el recorrido móvil de 7.26, además de `TurnosAccessibilityViewTest` y Node. Esto describe las comprobaciones realizadas; **no declara conformidad normativa WCAG completa**.

### 13. Apache multipart y aceptación final

#### 13.1 Transporte real certificado en 7.25/P6

Se verificó mediante Apache **2.4.58** y PHP **8.2.12**, SAPI **apache2handler**. Los valores efectivos fueron `upload_max_filesize=40M`, `post_max_size=40M`, `max_file_uploads=20`. Son condiciones del entorno de transporte probado, no límites funcionales de UC–07 ni cambios requeridos de php.ini.

La matriz de 7.25 cubrió 0/1/5 archivos, sexto archivo, 10 MiB exactos y +1, 25 MiB exactos y +1, formato/MIME falso, retiro/reemplazo, request por encima de post_max_size, descarga privada y compensación tras fallo. Para éxito se compararon bytes origen, tamaño DB, bytes físicos, hashes, asociación y auditoría; para rechazos se comprobó ausencia de alta parcial/auditoría falsa y retry seguro. Hubo cero huérfanos al finalizar.

P6 tuvo ejecuciones formales propias de integración, política/atomicidad, MariaDB/reconciliador y esta muestra Apache:

| Escenario Apache P6                         | Resultado certificado                                     |
| ------------------------------------------- | --------------------------------------------------------- |
| Archivo pequeño válido                      | 201, documento/asociación/archivo y hash conformes        |
| Total exacto 25 MiB                         | 201, conjunto completo y tamaños/hash conformes           |
| Total 25 MiB + 1 byte                       | 422, sin persistencia nueva; corrección con la misma UUID |
| Request de 50 MiB, superior a post_max_size | 413 seguro; no éxito silencioso sin archivos              |
| Replay de intención consolidada             | 200, sin duplicados                                       |
| Descarga propietario / actor ajeno          | 200 con bytes/hash y Content-Disposition / 403            |

Las evidencias locales son `uc07-725-729b0dd/{verificacion-final.json,config-apache.json}` y `uc07-p6-729b0dd/{resultado.json,config-apache.json,apache-*.json}`. P7 no repitió la matriz Apache; no hubo cambios posteriores que alteraran el transporte multipart. En 7.28 no se volvió a ejecutar.

#### 13.2 Aceptación 7.26

Todos los grupos siguientes quedaron **PASS** según `uc07-726-729b0dd/resultado.json`, los artefactos indicados allí y `verificacion-final.json`:

| Grupo de escenarios                                | Resultado y alcance de evidencia                                                                                                                                                                                  |
| -------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Paciente nuevo/libre, resumen, éxito y Mis turnos  | Recorrido completo, conjunto resumido igual al persistido y turno localizable; `exito-FLUJO.json`.                                                                                                                |
| Paciente histórico / nuevo ciclo                   | Nueva Primera consulta sin Padecimiento/Consulta ni impedimento por antecedentes terminales; `historico-mismo-dia.json`.                                                                                          |
| D-01 entrada y durante flujo                       | Entrada bloqueada y conflicto contextual sin segunda alta; `d01-entrada.json`, `d01-durante-flujo.json`.                                                                                                          |
| Sin profesionales                                  | Blade vigente renderizado con `profesionales=[]` en proceso aislado y entregado sólo al contexto Chrome autorizado, sin POST. **No se dejó toda la BD de desarrollo sin profesionales.**                          |
| Profesional sin disponibilidad y semana vacía      | Vacío válido, captura conservada, cambio de semana/profesional disponible; `ui-LOCAL.json`.                                                                                                                       |
| Búsqueda lejana                                    | Navegación semanal hasta **13/12/2026**, a más de 31 días, con confirmación; demuestra que 31 no es horizonte funcional.                                                                                          |
| Mismo día                                          | Confirmación de un slot futuro según reloj real del 18/10/2026; no se falseó el reloj para certificarlo.                                                                                                          |
| Cambio de profesional, volver y abandono           | Invalidación cuando corresponde, conservación de captura, revalidación 3→2→3 corregida y advertencia de abandono; `navegacion-compatible.json`, `abandono.json`.                                                  |
| Slot perdido / profesional no elegible             | 409 contextual, invalidación y paso correctos, sin falso éxito; `ui-FLUJO.json`.                                                                                                                                  |
| 422 motivo/documentos                              | Errores de motivo, documental indexado y global; conserva UUID y File válidos; `ui-FLUJO.json`.                                                                                                                   |
| Error de disponibilidad y respuesta fuera de orden | Retry diferenciado de vacío; descarte de respuesta A posterior a B y finally obsoleto. La evidencia de carrera de 7.20 se reutilizó de forma explícitamente autorizada, correlacionada con la suite Node vigente. |
| Respuesta incierta y replay                        | Backend realmente consolidó; se perdió la respuesta y el retry con la misma UUID devolvió replay sin duplicar; `respuesta-perdida.json`, `incierto-replay.json`.                                                  |
| Red sin commit y sesión expirada                   | Sin éxito ficticio ni auto-retry de sesión; estado local preservado; `red-antes-commit.json`, `sesion-expirada.json`.                                                                                             |
| Desktop/mobile y teclado/foco                      | Recorridos reales, listados revalidados, errores asociados y ausencia de overflow operativo; evidencia 7.22/7.26.                                                                                                 |
| Privacidad y atomicidad                            | Asociación al paciente/turno correctos, archivos/hash y whitelist de auditoría; sin exponer información ajena ni dejar agregados parciales.                                                                       |

El escenario “sin profesionales” y las intercepciones controladas de fallos son preparaciones de prueba declaradas, no arquitectura de producción. La corrección de navegación se verificó antes de cerrar 7.26 y quedó incluida en el HEAD técnico de P7.

### 14. Puertas y resultado técnico final

#### 14.1 Puertas P1–P7

| Puerta | Momento                          | Alcance certificado                                                          | Estado |
| ------ | -------------------------------- | ---------------------------------------------------------------------------- | ------ |
| P1     | Después de 7.04                  | Enums, persistencia, Models/inmutabilidad y consumidores de Agenda afectados | PASS   |
| P2     | Después de 7.10                  | Elegibilidad, política/storage documental, grilla, disponibilidad y Agenda   | PASS   |
| P3     | Después de 7.13                  | SQLite completa y concurrencia real de la Action en MariaDB                  | PASS   |
| P4     | Después de 7.18                  | Requests, middleware, Policy, rutas y contratos HTTP                         | PASS   |
| P5     | Después de 7.22                  | Vistas/HTTP, build, interacción real, responsive y accesibilidad comprobada  | PASS   |
| P6     | Después de 7.25                  | Integración, carreras HTTP, reconciliador concurrente y Apache multipart     | PASS   |
| P7     | 7.27, después de aceptación 7.26 | PHP global, Node focal, MariaDB real, build, Pint acotado y diff             | PASS   |

7.23 certificó integración y concurrencia HTTP; 7.24 aplicó/verificó esquema; 7.25 certificó transporte Apache; 7.26 cerró aceptación funcional/UX. No se inventan totales parciales de P1–P5 ni se sustituyen pruebas reales por una suite de otra capa.

#### 14.2 P7 exacto y comparación con baseline

| Verificación                                                                   | Resultado certificado                                                                             |
| ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------- |
| Baseline previo a UC–07                                                        | **1376 tests / 8842 assertions**                                                                  |
| Final SQLite, `php artisan test` completo sin filtro                           | **2069 tests / 12725 assertions**, PASS, 193,45 s                                                 |
| Delta                                                                          | **+693 tests / +3883 assertions**                                                                 |
| PHP: failures/errors/skipped/incomplete/risky/warnings/deprecations informadas | **0**                                                                                             |
| `node --check resources/js/turnos/solicitar-turno.js`                          | PASS                                                                                              |
| `node --test tests/JavaScript/solicitar-turno.test.js`                         | **60 / 60 PASS**, 0 FAIL, 0 skipped; 282,238 ms                                                   |
| `php vendor/bin/phpunit -c phpunit.mysql.xml`                                  | **28 tests / 1064 assertions PASS**, 02:20.248; 0 failures/errors/skips/incomplete/risky/warnings |
| `npm run build`                                                                | PASS; Vite **14,09 s**                                                                            |
| Pint del scope explícito UC–07                                                 | **82 PHP PASS**: 37 app, 3 migrations, 1 routes, 41 tests                                         |
| `git diff --check`                                                             | PASS                                                                                              |

La suite normal se verificó en `APP_ENV=testing`, SQLite `:memory:`, con Unit y Feature, caché/sesión array y sin conexión a MariaDB de desarrollo. La suite MariaDB separada se ejecutó con autorización específica P7, `APP_ENV=testing`, driver mysql, `127.0.0.1:3306`, base exclusiva **`kinesiosoft_uc07_test`**, MariaDB **10.4.32**, InnoDB y **REPEATABLE-READ**. Preparación y limpieza destructivas quedaron confinadas a esa base/filesystem de pruebas mediante guardas de configuración e identidad real.

La corrida MariaDB pasó a la primera. El postflight verificó **94 sesiones propias, cero activas, cero transacciones y cero directorios de escenario remanentes**, y dejó la base de testing preparada sin fixtures. No se utilizaron fixtures de desarrollo para preparar esa suite.

Los primeros intentos de Node y build en sandbox fallaron por `spawn EPERM`; fueron clasificados como entorno y repetidos fuera del sandbox después de autorizaciones específicas nuevas, con PASS. El único aviso de build fue Browserslist/caniuse-lite desactualizado; no se actualizaron dependencias. **No hubo cambios productivos/versionables durante 7.27**, tests debilitados ni repetición de carreras “hasta verde”. Pint se ejecutó por cuatro grupos de paths explícitos, no globalmente.

Evidencia local P7, relativa a `storage/framework/testing/uc07-727-3a95e84/`:

- `preflight-sqlite.json`, `sqlite.log`, `sqlite.junit.xml` y `sqlite.result.json`: configuración, totales y corrida sin filtros, con JUnit y visualización de incidencias.
- `node-syntax.result.json`, `node.log`, `node.result.json` y logs `*.sandbox-eperm.*`: resultado y limitación de entorno inicial.
- `plan-mariadb.json`: plan previo autorizado; su marca de propuesta no es un resultado. `mysql-preflight.json`, `mysql.log`, `mysql.result.json`, `mysql-run.json` y `mysql-postflight.json` registran la ejecución efectiva y remiten a la evidencia de los 28 casos en `uc07-mysql/`.
- `build.log`, `build.result.json`, `pint-scope.json` y `pint-{app,migrations,routes,tests}.result.json`: build y alcance/comandos exactos de formato derivados de Git.
- `conservacion-desarrollo.json`: comprobación final de sólo lectura de fixtures y archivos de aceptación.

#### 14.3 Estado del entorno sintético de aceptación

Los fixtures **UC07_725 / UC07_P6 / UC07_726** quedaron conservados. La lectura final posterior a P7 registró **17 turnos, 21 documentos, 21 asociaciones, 21 archivos y 36 auditorías de éxito**, con cero huérfanos/faltantes y tamaños/hash de los 21 archivos conformes a su evidencia previa.

Estos números son el **estado del entorno sintético de aceptación**, no cardinalidades contractuales ni datos permanentes del sistema. Sus IDs no forman parte del diseño. No se limpiaron ni recrearon durante P7 o 7.28; cualquier limpieza será una operación separada con autorización específica.

### 15. Pruebas representativas y límites de certificación

| Familia                            | Archivos representativos reales                                                                                                                                                                                                          |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tipos/estados/esquema              | `tests/Unit/Enums/{TipoTurnoEnumTest,EstadoTurnoEnumTest}.php`, `tests/Feature/Database/{TurnoSchemaTest,DocumentosClinicosSchemaTest,AgendaSchemaTest}.php`                                                                             |
| Original y relaciones              | `tests/Feature/Models/{TurnoModelTest,DocumentoClinicoModelTest,DocumentoClinicoOrigenTurnoModelTest}.php`                                                                                                                               |
| Reglas/disponibilidad              | `tests/Feature/Services/Turnos/{ElegibilidadKinesiologaTest,ElegibilidadPrimeraConsultaTest,GrillaPrimeraConsultaTest,DisponibilidadPrimeraConsultaTest}.php`                                                                            |
| Política/almacenamiento/atomicidad | `tests/Feature/Services/Turnos/{PoliticaDocumentosClinicosTest,AlmacenamientoDocumentosClinicosTest}.php`, `tests/Feature/Actions/Turnos/AtomicidadPrimeraConsultaTest.php`                                                              |
| HTTP/aislamiento/UX                | `tests/Feature/Turnos/{SolicitarTurnoHttpTest,PrimeraConsultaIntegracionTest,MisTurnosTest,SolicitarTurnoCreateTest,TurnosRoutesTest,TurnosAccessibilityViewTest}.php`, `tests/Feature/Requests/ConfirmarPrimeraConsultaRequestTest.php` |
| Descarga                           | `tests/Feature/DescargarDocumentoClinicoTest.php`, `tests/Feature/Policies/DocumentoClinicoPolicyTest.php`                                                                                                                               |
| Concurrencia real                  | `tests/Integration/MySql/{ConfirmarPrimeraConsultaConcurrenciaTest,PrimeraConsultaHttpConcurrencyTest,AdvisoryLocksDocumentalesTest,ReconciliacionDocumentalTest}.php`                                                                   |
| Reconciliador y frontend           | `tests/Feature/Services/Turnos/ReconciliarDocumentosClinicosTest.php`, `tests/JavaScript/solicitar-turno.test.js`                                                                                                                        |

Las llaves en las rutas de esta tabla son agrupaciones de nombres de archivos, no comandos. Las evidencias de navegador y Apache están identificadas en sus secciones; Node no se presenta como reemplazo de navegador, ni SQLite como prueba de locks InnoDB.

Límites deliberados: no hold temporal; no horizonte funcional máximo; no notificación externa; sin cancelación/reprogramación/seguimiento ni Padecimiento/Consulta en este flujo; sin scheduler de reconciliación ni gestión clínica documental completa. No se declara conformidad WCAG integral ni certificación de todas las plataformas/SAPIs. El aviso de Browserslist fue informativo. Existe deuda histórica de Pint global fuera de UC–07: el PASS corresponde únicamente al scope PHP solicitado del incremento.

### 16. Hitos reales en Git

Secuencia reconstruida con `git log --oneline --decorate 9d9b1510402e64c310f02ec1014b24333c13ed0c..HEAD`, contrastando commits pertinentes con `git show`. Las filas agrupadas resumen hitos, no reasignan un subpaso a un commit distinto.

| Hito                                                          | Commits comprobados                                                        |
| ------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Base oficial: cierre de Agenda                                | `9d9b151` — docs: registrar cierre de configuración de agenda              |
| Preparación y planificación congelada                         | `808794f`, `037dbba`                                                       |
| Tipos, idempotencia, esquema y Models/inmutabilidad           | `ecabc9d`, `10906e2`, `8c2c835`, `2d476bd`                                 |
| Elegibilidad profesional y D-01                               | `4eb3b00`, `38dd618`                                                       |
| Política y storage privado                                    | `72594be`, `b04eec6`                                                       |
| Grilla y disponibilidad                                       | `598d7df`, `a531ae7`                                                       |
| Núcleo transaccional y atomicidad documental                  | `f863489`, `eac8a12`                                                       |
| Concurrencia real y reconciliación                            | `9ea8cec`, `1794768`                                                       |
| Requests, descarga, Mis turnos y HTTP                         | `59ce17c`, `dec7d84`, `cb4a46a`, `b77ab8b`                                 |
| Wizard, disponibilidad frontend, confirmación y accesibilidad | `d54343f`, `4fddbc4`, `6579882`, `649a816`                                 |
| Integración y concurrencia HTTP                               | `729b0dd` — test: certificar integracion y concurrencia http de turnos     |
| Corrección de aceptación 7.26                                 | `3a95e84` — fix: conservar slot compatible al volver en solicitud de turno |
| P7 / 7.27                                                     | PASS sobre `3a95e84`, sin cambio versionable ni commit propio              |
| 7.28                                                          | Este documento; commit documental todavía no creado                        |

La aplicación del esquema 7.24 y las certificaciones operativas 7.25/P6/7.26 se respaldan en evidencia local y autorizaciones; no se les inventa un commit de código. Las fuentes congeladas y la planificación no se reescribieron para acomodar el cierre.

### 17. Estado metodológico y cierre

| Paso                                 | Estado final                                |
| ------------------------------------ | ------------------------------------------- |
| 1 — Alcance funcional                | Aprobado y congelado                        |
| 2 — Relevamiento técnico             | Completo                                    |
| 3 — Gap analysis                     | Completo                                    |
| 4 — Diseño técnico/físico            | Aprobado y congelado                        |
| 5 — Diseño UX                        | Aprobado y congelado                        |
| 6 — Referencia visual                | Cerrado por suficiencia                     |
| 7 — Planificación ejecutable         | Cerrado, aprobado y congelado               |
| 8 — Prompts blindados                | Ejecutados por subpaso autorizado           |
| 9 — Implementación, pruebas y cierre | **COMPLETO con P7 PASS y 7.28 documentado** |

El incremento queda implementado y certificado dentro de D-16, con las limitaciones de prueba expresamente indicadas. Este cierre no autoriza modificar AGENTS, preparar otro incremento, limpiar fixtures, stagear, commitear ni publicar. Esas decisiones siguen siendo separadas. La revisión de 7.28 es exclusivamente documental y no repite las puertas certificadas.

[diccionario]: ../_codex_sources/turnos/Diccionario_de_Datos_vigente.txt
[uc07]: ../_codex_sources/turnos/UC-07_Solicitar_turno.txt
[tecnico]: ../_codex_sources/turnos/DECISIONES_UC07_DISENO_TECNICO_V1.0.md
[ux]: ../_codex_sources/turnos/DECISIONES_UC07_DISENO_UX_V1.0.md
[plan]: turnos-solicitar-turno-planificacion.md
[d01]: ../../app/Services/Turnos/ElegibilidadPrimeraConsultaService.php
[action]: ../../app/Actions/Turnos/ConfirmarPrimeraConsultaAction.php
[elegibilidad]: ../../app/Services/Turnos/ElegibilidadKinesiologaService.php
[disponibilidad]: ../../app/Services/Turnos/DisponibilidadPrimeraConsultaService.php
[agenda]: ../../app/Services/Agenda/AgendaQueryService.php
[politica]: ../../app/Services/Turnos/PoliticaDocumentosClinicosService.php
[wizard]: ../../resources/js/turnos/solicitar-turno.js
[rutas]: ../../routes/web.php
