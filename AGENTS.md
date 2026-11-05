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

## Incremento activo — Agenda Diaria Automática

El incremento activo comprende exclusivamente:

- **UC–05 — Consultar agenda de atención.**
- **UC–27 — Notificar próximos turnos automáticamente.**
- **UC–28 — Resolver asistencia de turno vía enlace.**
- **UC–29 — Generar agenda diaria automáticamente.**

Rama esperada:

`feature/turnos-agenda-diaria-automatica`

Commit base del incremento:

`ddcbdb9acfdf0ef87b3220179ecdcfe2dd249082` — `docs: cerrar incremento UC-06 gestionar mis turnos`.

### Estado y alcance operativo

- **Etapas 1 a 5: APROBADAS Y CERRADAS.**
- **Etapa 6: IMPLEMENTACIÓN INCREMENTAL ACTIVA**, limitada al subpaso expresamente solicitado; no implica funcionalidades implementadas ni autorización general para ejecutar los siguientes.
- **6.01: COMPLETADO** — preparación y verificación inicial.
- **6.02: COMPLETADO** — integridad y trazabilidad de las fuentes locales verificadas.
- **6.03: COMPLETADO** — rama independiente creada sobre el commit base indicado.
- **6.04: COMPLETADO** — instrucciones operativas actualizadas; sin pruebas ni cambios funcionales.
- **6.05: COMPLETADO Y CERTIFICADO** — baseline ordinario actual de PHP/SQLite, JavaScript y build; MariaDB pendiente de certificación independiente.
- **6.06: COMPLETADO — checkpoint documental preparado y versionado mediante el commit de cierre de este subpaso.**
- **6.07: COMPLETADO Y VERIFICADO** — consulta interna compartida de turnos por profesional y jornada.
- **6.08: COMPLETADO Y VERIFICADO** — consulta por período y representación administrativa mínima, sin escrituras.
- **6.09: COMPLETADO Y VERIFICADO** — frontera de autorización profesional, separada de la lectura interna de UC–29.
- **6.10: COMPLETADO Y VERIFICADO** — ruta, controlador y validación de consulta HTTP profesional de UC–05.
- **6.11: COMPLETADO Y VERIFICADO** — agenda diaria y navegación entre fechas con datos administrativos autorizados.
- **6.12: COMPLETADO Y VERIFICADO** — búsqueda por paciente, filtros combinables, períodos y paginación de resultados.
- **6.13: IMPLEMENTACIÓN Y VERIFICACIONES COMPLETADAS** — detalle administrativo autorizado, sin acceso clínico ni escrituras funcionales.
- **6.14: IMPLEMENTACIÓN Y VERIFICACIONES COMPLETADAS** — impresión y PDF auténtico de resultados autorizados, con contexto y momento de emisión.
- **6.15: IMPLEMENTACIÓN Y VERIFICACIONES COMPLETADAS** — refinamiento UX y accesibilidad automatizada; revisión visual manual pendiente.
- **6.16: IMPLEMENTACIÓN Y VERIFICACIONES COMPLETADAS** — regresión funcional y de seguridad conjunta, con ensayos locales de rendimiento y volumen y sus reservas explícitas.

**Bloque B (6.07–6.09): CHECKPOINT CERTIFICADO** para los contratos internos de lectura y autorización implementados. Regresión conjunta focalizada: **102 tests / 534 assertions PASS**, con SQLite `:memory:`; sintaxis y Pint `--test` de los cuatro PHP satisfactorios. Certificación: `docs/implementacion/turnos-agenda-diaria-607-609-bloque-b.md`. No constituye implementación completa de UC–05/UC–29 ni certificación MariaDB.

**Checkpoint UC–05 (6.10–6.12): DOCUMENTADO Y VERSIONADO mediante su commit de cierre.** Certificación: `docs/implementacion/turnos-agenda-diaria-610-612-uc05.md`. Se reutiliza la ejecución definitiva de 6.12: **70 tests / 618 assertions PASS**, exit code 0, del archivo HTTP completo, incluida la ruta de UC–05. En este checkpoint no se repiten PHPUnit, JavaScript, build, MariaDB ni regresión global. La paginación actual se realiza en memoria; la verificación de rendimiento y volumen conforme a los requisitos aprobados queda pendiente para **6.16**, sin optimizaciones preventivas. No constituye cierre completo de UC–05.

**UC–05: BLOQUE IMPLEMENTADO Y CERTIFICADO EN LAS CONDICIONES LOCALES ENSAYADAS**, con dependencias y reservas documentadas. Checkpoint 6.13–6.16 registrado mediante su commit de cierre; certificado: `docs/implementacion/turnos-agenda-diaria-613-616-uc05.md`. Regresión definitiva: **181 tests / 1785 assertions PASS, exit 0**. CA-05-09/23/24 conservan dependencias no resueltas; CA-05-30–32 se contrastaron sólo en el ensayo SQLite preparado. Paginación y exportación permanecen en memoria; el PDF procesa tablas por bloques sin recortar resultados. Revisión visual manual, carga fría, MariaDB, producción y suite global no certificados por este bloque. Las menciones pendientes de los checkpoints anteriores describen su estado de emisión; no sustituyen estas reservas ni sus evidencias históricas.

**Último subpaso completado: 6.16.**

**Próximo subpaso para preparar: 6.17 — UC–29.**

**Modalidad acelerada experimental aprobada para UC–29:** primera ejecución **6.17–6.18**; segunda ejecución **6.19–6.21**. **Prioridad de optimización: cuota semanal.** Esta modalidad no modifica los contratos congelados ni reduce las verificaciones de seguridad necesarias.

6.17 no está autorizado para ejecución automática durante este checkpoint. Cada ejecución requiere su solicitud y alcance específicos. Staging, commits, cambios de rama, operaciones sobre remotos y operaciones destructivas no quedan autorizados por este seguimiento.

### Fuentes autoritativas de Agenda Diaria Automática V1.0

Ubicación local:

`docs/_codex_sources/turnos/agenda_diaria_automatica/`

Los tres originales DOCX son:

- `Planificacion Agenda Diaria Automatica(1).docx` — compendio de decisiones aprobadas de las Etapas 1 a 5.
- `KinesioSoft_Etapa3_302_GrupoA_UC27_UC28_APROBADO_V1.0.docx` — especificaciones extendidas de UC–27 y UC–28.
- `KinesioSoft_GrupoB_UC29_UC05_Especificaciones_Extendidas_V1.0_Revision.docx` — especificaciones extendidas de UC–29 y UC–05, aprobadas en la conversación posterior de revisión.

Completan el paquete los tres TXT homónimos, auxiliares para búsqueda y análisis, `SHA256_6_02.txt` y `LEEME_6_02.txt`. Los DOCX prevalecen como fuentes originales frente a diferencias de representación en los TXT.

El compendio contiene las decisiones funcionales D-01 a D-13, la identificación 3.01, la matriz consolidada 3.03 y el cierre 3.04, junto con:

- **114 criterios de aceptación:** UC–27: 29; UC–28: 34; UC–29: 19; UC–05: 32.
- **18 dependencias funcionales** y **10 escenarios integrales**.
- **AT-01 a AT-12:** arquitectura y contratos compartidos, Etapa 5.01.
- **GA-01 a GA-13:** diseño técnico del Grupo A, Etapa 5.02.
- **GB-01 a GB-12:** diseño técnico del Grupo B, Etapa 5.03.
- **UXP-01 a UXP-16:** UX, pruebas y planificación incremental, Etapa 5.04. QA-01 a QA-04 corresponden respectivamente a UXP-09 a UXP-12; no son decisiones adicionales.

Estas fuentes están **APROBADAS Y CONGELADAS**, disponibles localmente y excluidas de Git mediante `.git/info/exclude`. No modificar sus contenidos ni las exclusiones. No reconstruir criterios, decisiones o especificaciones desde resúmenes; ante dudas, consultar las fuentes completas.

Las referencias provisionales UC–XX y la leyenda histórica de propuesta pendiente de 3.03 se interpretan conforme a la consolidación 3.01 y al cierre aprobado 3.04. No corregir retrospectivamente los originales. Las decisiones de UC–06, UC–07 y Configuración de Agenda conservan autoridad dentro de sus propios contratos; no sustituyen las fuentes del incremento activo. Los identificadores de decisiones se interpretan dentro del incremento al que pertenecen.

### Contratos protegidos durante el nuevo incremento

Son obligatorios los contratos completos de las fuentes aprobadas; las siguientes referencias operativas no los sustituyen:

- **UC–06:** preservar los seis estados y transiciones canónicas; confirmar Pendiente estrictamente antes del inicio y cancelar Pendiente/Confirmado con al menos 12 horas corridas, incluido el límite exacto. Reutilizar `ConfirmarAsistenciaTurnoAction` y `CancelarTurnoAction`, con propiedad y autorización del paciente, `TransaccionTurnoService`, locks Kinesióloga → Paciente → Turno, revalidación temporal posterior a los locks con `RelojAplicacion`, idempotencia y auditoría atómica. Conservar el registro durable de intención de evaluación del cupo y su unicidad, sin atribuirle un consumidor externo implementado.
- **UC–07:** preservar creación y programación de Primera consulta, disponibilidad y elegibilidad canónicas, idempotencia, reserva de agenda y persistencia de originales con protección documental. No duplicar ni reejecutar la reserva desde los procesos nuevos.
- **Configuración de Agenda:** conservar vigencias, duración, franjas, bloqueos, políticas y operaciones profesionales existentes. Mantener separadas la configuración de disponibilidad y UC–05 Consultar agenda de atención; no recalcular turnos históricos según configuraciones posteriores.
- **Agenda Diaria Automática:** preservar `Turno` y la programación consolidada como fuente autoritativa, separar las responsabilidades de los cuatro casos de uso y mantener autorización específica por canal. Reutilizar las operaciones existentes, conservar la independencia de UC–05 respecto del éxito de UC–29 y diferenciar las identidades de recordatorios, intentos y ejecuciones. Proteger los datos personales y clínicos y verificar sobre MariaDB real las garantías que dependan de concurrencia, conforme a AT/GA/GB/UXP.

No incorporar por inferencia operaciones clínicas, reprogramación profesional, reservas de equipos ni procesamiento de candidatos u ofertas de otros procesos. Conservar sus dependencias y límites de evidencia explícitos. Las instrucciones históricas de los incrementos cerrados no obligan a trabajar en sus ramas ni habilitan reejecutar sus subpasos.

### Aclaración complementaria de seguridad — APROBADA Y CONGELADA

La aclaración de 6.04 complementa **AT-10, GA-07 y GA-08**: se autoriza exclusivamente incluir la credencial opaca necesaria dentro de la URL de acceso seguro enviada al paciente por correo electrónico.

Esta excepción no autoriza:

- Mostrar la credencial separadamente de la URL necesaria.
- Almacenar el token original en texto plano.
- Incorporar credenciales, firmas o secretos a logs, auditorías o mensajes de error.
- Exponerlas mediante redirecciones, referencias externas o registros técnicos evitables.
- Incluir datos clínicos innecesarios en el correo o en la página de resolución.

El mecanismo deberá conservar autenticidad, alcance limitado, vigencia, revocación, verificación en servidor y protección frente a filtraciones accidentales. La inclusión de la credencial en la URL no sustituye las validaciones de propiedad, programación, estado y temporalidad de las Actions compartidas.

Esta aclaración resuelve la observación de coherencia registrada en 6.02 y complementa las decisiones congeladas sin modificar sus demás contratos. No editar retrospectivamente el compendio aprobado.

### Baseline ordinario actual y antecedentes

**Baseline global de referencia — CERTIFICADO en 6.05 (ejecución previa):**

- PHP: **2760 tests / 18474 assertions PASS**.
- JavaScript: **196 PASS**.
- Build: **PASS, 95 módulos**.

Certificación detallada: `docs/implementacion/turnos-agenda-diaria-605-baseline.md`. PHP 8.2.12 / PHPUnit 11.5.55, Node.js 25.8.1 y Vite 7.3.1; ejecuciones satisfactorias con código de salida 0. Durante 6.05 se verificó el aislamiento efectivo de SQLite `:memory:`, sin PDO MySQL y con storage y vistas exclusivos, sin cambios funcionales.

Se conserva esta certificación como referencia histórica vigente de partida. El checkpoint del bloque B ejecutó únicamente su regresión focalizada y no repitió el baseline global, JavaScript ni build; sus resultados no se suman a los de 6.05 ni los recertifican globalmente.

**MariaDB: certificación actual pendiente.** No se ejecutaron `phpunit.mysql.xml` ni `tests/Integration/Uc06MariaDb/phpunit.xml`. Mantener diferenciadas las pruebas SQLite y las integraciones MariaDB; SQLite no certifica locking real. Las certificaciones históricas de UC–06 y UC–07 conservan su alcance original y no sustituyen una ejecución actual ni autorizan automáticamente reinicializaciones u otras operaciones destructivas en bases de pruebas.

En 6.06 sólo se registra la evidencia existente: no repetir pruebas, builds, migraciones o seeders ni utilizar bases de datos. Los 134 temporales ignorados de 6.05 se conservan sin limpieza ni incorporación a Git; su ubicación y los límites de certificación constan en el documento citado. El checkpoint queda versionado mediante el commit de cierre de 6.06. Las referencias del certificado a preparación pendiente describen su estado de emisión previo al commit; el seguimiento operativo vigente es el indicado en este bloque.

## Antecedente cerrado — UC–06 Gestionar Mis turnos

**UC–06: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora la certificación de 6.18**, hasta su alcance implementado y certificado, con dependencias externas y límites de evidencia expresamente identificados.

**Al cierre de UC–06 no había un nuevo incremento iniciado ni autorizado.** Agenda Diaria Automática y los demás procesos externos requieren alcance, preparación y autorización independientes; no se inician por ese cierre. El estado operativo vigente de Agenda Diaria Automática se registra en el bloque inicial de este archivo.

Rama histórica de UC–06:

`feature/turnos-gestionar-mis-turnos`

Commit base histórico de UC–06:

`6c9dcd63160ca60603547feab7eb6afe525ee653` — `docs: cerrar UC-07 Solicitar turno V2`.

UC–07 Solicitar turno V2 está **CERRADO** y constituye infraestructura previa que debe preservarse junto con Agenda. Su detalle posterior se conserva como antecedente, sin subpasos pendientes ni autorización para reejecutarlo.

### Fuentes autoritativas de UC–06 V1.0

Las seis fuentes están **APROBADAS Y CONGELADAS** y disponibles localmente en:

- `docs/_codex_sources/turnos/uc06/UC06_DECISIONES_FUNCIONALES_D01_D18_V1.0.md`.
- `docs/_codex_sources/turnos/uc06/UC06_ESPECIFICACION_FUNCIONAL_V1.0.md`.
- `docs/_codex_sources/turnos/uc06/UC06_MATRIZ_ARMONIZACION_DEPENDENCIAS_V1.0.md`.
- `docs/_codex_sources/turnos/uc06/UC06_DISENO_TECNICO_CONSOLIDADO_V1.0.md`.
- `docs/_codex_sources/turnos/uc06/UC06_DISENO_UX_CONSOLIDADO_V1.0.md`.
- `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md`.

Estas fuentes permanecen excluidas de Git mediante la regla existente de `.git/info/exclude` y deben conservarse disponibles para Codex, sin modificar sus contenidos ni las exclusiones.

Las decisiones D-01–D-18 gobiernan el comportamiento funcional, desarrollado por la especificación; la matriz delimita armonización y dependencias, y los diseños DT/UX y la planificación se subordinan a esas reglas. La documentación histórica de UC–06 y los README previos no sustituyen estas fuentes ni determinan el incremento activo.

Los bloques de UC–07 de este archivo describen el cierre y las garantías a preservar. Las armonizaciones expresamente aprobadas por UC–06, como la ocupación por En atención y la autorización individual de Turno, sólo podrán ejecutarse en sus subpasos autorizados; no implican reabrir decisiones ni alterar reglas ajenas al delta aprobado.

### Planificación y estado de UC–06

La planificación incremental V1.0 contiene 18 subpasos en cinco fases:

| Fase | Subpasos | Alcance |
| --- | --- | --- |
| A | 6.01–6.03 | Preparación documental, rama y baseline. |
| B | 6.04–6.08 | Consultas y detalle. |
| C | 6.09–6.13 | Confirmación, cancelación e integridad. |
| D | 6.14–6.15 | Interfaz y experiencia de usuario. |
| E | 6.16–6.18 | Integraciones, regresión y cierre. |

- **6.01: COMPLETADO Y CERTIFICADO**; fuentes incorporadas localmente, con integridad y exclusión de Git verificadas.
- **6.02: COMPLETADO**; preparación de rama y actualización de este archivo, versionadas en `f59ca2b`.
- **6.03: COMPLETADO Y CERTIFICADO**; baseline técnico inicial de UC–06 satisfactorio.
- **6.04: IMPLEMENTADO, VERIFICADO Y CERRADO** en `97b9ac3`; estados y ocupación de agenda conforme a DT-01.
- **6.05: IMPLEMENTADO, VERIFICADO Y CERRADO** en `f1c103a`; clasificación de Próximos e Historial conforme a D-14 y DT-02.
- **6.06: IMPLEMENTADO, VERIFICADO Y CERRADO** en `458a308`; filtros y búsqueda conforme a DT-11 y UX-02.
- **6.07: IMPLEMENTADO, VERIFICADO Y CERRADO** en `ecef8f9`; autorización por turno conforme a D-09 y DT-04.
- **6.08: IMPLEMENTADO, CORREGIDO, CERTIFICADO Y CERRADO** en `10a2b55`; detalle autorizado y seguridad documental D-09/R4 dentro del alcance disponible, con C-02 / DT-03 pendiente según el bloque de cierre.
- **6.09: IMPLEMENTADO, VERIFICADO Y CERRADO** en `a1c662d`; infraestructura transaccional compartida conforme a DT-05 y DT-06, con concurrencia real MariaDB pendiente de 6.13.
- **6.10: IMPLEMENTADO, VERIFICADO Y CERRADO** en `cb607b2`; Confirmar asistencia conforme a D-06, D-09–D-12 y DT-05–DT-08.
- **6.11: IMPLEMENTADO, VERIFICADO Y CERRADO** en `eea2d15`; infraestructura de intención durable, única e idempotente de evaluación del cupo conforme a DT-09.
- **6.12: IMPLEMENTADO, VERIFICADO Y CERRADO** en `9bd7586`; cancelación transaccional e idempotente, con auditoría e intención durable atómicas conforme a D-04 y DT-05–DT-09.
- **6.13: IMPLEMENTADO, VERIFICADO Y CERRADO** en `616085b57b425e873ff0e957b0d558cb26add136`.
- **6.14: IMPLEMENTADO, VERIFICADO Y CERRADO** en `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`.
- **6.15: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**.
- **6.16: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**, hasta el alcance disponible y con dependencias externas pendientes explícitas.
- **6.17: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**, con regresión ordinaria integral PASS y evidencia MariaDB/visual previa reutilizada explícitamente.
- **6.18: COMPLETADO Y CERRADO mediante el commit que incorpora el informe definitivo y la matriz de los 40 criterios**, sin anticipar su hash. No quedan subpasos de UC–06 autorizados para ejecución automática.

El cierre de 6.12, versionado en `9bd7586`, comprendió la revisión de los cuatro archivos certificados, la actualización de seguimiento en `AGENTS.md` y el único commit expresamente solicitado. Esa autorización no habilitaba repetir pruebas, builds o migraciones ni iniciar 6.13.

El cierre formal de 6.13 quedó versionado en `616085b57b425e873ff0e957b0d558cb26add136`; el de 6.14, en `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`; el de 6.15, en `cdf196bc0bc98f84bfb93967bba019c1db6a595a`; el de 6.16, en `d73af11ef98d77ebb81de59a56ada35d9af4639c`; y el de 6.17, en `ee3d2577c2899c791e842e53e1f45b542ae2ab98`. El cierre definitivo de 6.18 y de UC–06 queda formalizado mediante el commit que incorpora su informe. Estos estados no autorizan por sí solos staging, commit, push, merge, PR, despliegues, nuevas ejecuciones, limpieza de datos ni iniciar otro incremento.

### UC–06 — Baseline inicial certificado (6.03)

**Estado: CERTIFICADO.** Referencia técnica previa al cierre documental: `f59ca2b4f8f081576032d646ef7f7a73f830fbbd`, sin modificaciones funcionales.

- PHPUnit, `php artisan test`: **2231 tests / 14391 assertions PASS**.
- JavaScript: **155 tests PASS**, correspondientes a **150 del wizard** y **5 de bloqueo-form**.
- Verificaciones de sintaxis JavaScript del wizard y su archivo de pruebas: **PASS**.
- `npm run build`: **PASS, 94 módulos**.
- MariaDB, `php vendor/bin/phpunit -c phpunit.mysql.xml`: **30 tests / 1219 assertions PASS**.
- MariaDB: base aislada **`kinesiosoft_uc07_test`**, preflight satisfactorio y autorización explícita del usuario para su reinicialización.
- **No se detectaron regresiones en las suites ejecutadas**; Git sin modificaciones funcionales. Este cierre modifica únicamente `AGENTS.md`.

La certificación MariaDB, incluido el preflight y la autorización para reinicialización, se registra conforme al resultado satisfactorio informado por el usuario al autorizar este cierre. En este cierre documental no se reejecutan pruebas, builds ni migraciones.

Este es el baseline inicial certificado de UC–06. Los resultados de UC–07 conservados abajo permanecen como antecedentes históricos independientes.

### UC–06 — Verificación y cierre de 6.04

- Pruebas focalizadas: **274 tests / 1401 assertions PASS**.
- Regresión completa, `php artisan test`: **2239 tests / 14414 assertions PASS**.
- Sintaxis PHP y Pint sobre los diez archivos PHP modificados: **PASS**.
- `git diff --check`: **PASS**.
- La revisión de cierre conserva el mismo código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md` antes del commit.
- JavaScript (**155 PASS**), sintaxis JavaScript, build (**PASS, 94 módulos**) y MariaDB (**30 tests / 1219 assertions PASS**) conservan sus resultados históricos de 6.03; **no se repitieron durante 6.04** y no constituyen una recertificación de esos alcances para el cambio actual.

### UC–06 — Verificación y cierre de 6.05

- Pruebas focalizadas: **348 tests / 2497 assertions PASS**.
- Regresión completa, `php artisan test`: **2279 tests / 14693 assertions PASS**, con SQLite `:memory:`.
- Sintaxis PHP, Pint acotado en modo `--test` y `git diff --check`: **PASS**.
- El código revisado coincide con el certificado; no se modificó después de las pruebas ni se repitieron durante este cierre.
- JavaScript, sintaxis JavaScript, build y MariaDB conservan la certificación histórica de 6.03 indicada arriba; **no se repitieron durante 6.05**.

### UC–06 — Verificación y cierre de 6.06

- Mis turnos, rutas y accesibilidad: **141 tests / 1195 assertions PASS**.
- Focalizadas ampliadas: **393 tests / 2815 assertions PASS**.
- Regresión completa, `php artisan test`: **2324 tests / 15019 assertions PASS**, con SQLite `:memory:`.
- Sintaxis PHP, Pint acotado en modo `--test` y `git diff --check`: **PASS**.
- `npm run build`: **PASS, 94 módulos**.
- Los seis archivos revisados coinciden con el código certificado; no se modificaron después de las pruebas ni se repitieron pruebas o build durante este cierre.
- JavaScript, sintaxis JavaScript y MariaDB conservan la certificación histórica de 6.03 indicada arriba; **no se repitieron durante 6.06**.

### UC–06 — Verificación y cierre de 6.07

- `TurnoPolicyTest`: **60 tests / 276 assertions PASS**.
- Focalizadas ampliadas: **544 tests / 3467 assertions PASS**.
- Regresión completa, `php artisan test`: **2384 tests / 15295 assertions PASS**, con SQLite `:memory:`.
- Sintaxis PHP, Pint acotado en modo `--test` y `git diff --check`: **PASS**.
- Los dos archivos revisados coinciden con el código certificado, sin modificaciones posteriores a las pruebas; no se repitieron pruebas durante este cierre.
- JavaScript y MariaDB conservan sus resultados históricos de 6.03; build conserva su resultado de 6.06 (**PASS, 94 módulos**). **No se repitieron durante 6.07**.

### UC–06 — Verificación y cierre de 6.08

- Política documental, descarga privada y detalle de Mis turnos: **233 tests / 1680 assertions PASS**.
- Focalizadas de UC–06, UC–07 y Agenda: **334 tests / 2307 assertions PASS**.
- Regresión completa, `php artisan test`: **2453 tests / 15907 assertions PASS**, con SQLite `:memory:`, PDO SQLite y configuración sin caché.
- Corrección **D-09/R4 CERTIFICADA**: la autorización documental del paciente revalida propiedad, origen permitido y asociación persistida con Primera consulta del mismo paciente; los rechazos por URL directa se verificaron mediante HTTP antes de abrir storage, preservando los accesos legítimos de la profesional asignada y los permisos administrativos existentes.
- Sintaxis PHP, Pint acotado en modo `--test` y `git diff --check`: **PASS**; verificaciones finales con código de salida 0.
- Último `npm run build`: **PASS, 94 módulos**, durante la implementación de 6.08 antes de la corrección documental. No se repitió después de esa corrección, que no afectó recursos frontend, ni durante este cierre.
- Los trece archivos revisados coinciden por hashes con el código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md`, sin repetir pruebas.
- JavaScript, sintaxis JavaScript y MariaDB conservan sus resultados históricos de 6.03; **no se repitieron durante 6.08**.
- **Dependencia C-02 / DT-03 PENDIENTE:** integración clínica de datos reales de planes y sesiones de Seguimiento todavía sin infraestructura externa disponible ni certificación. El detalle muestra únicamente los datos administrativos reales y el estado vacío correspondiente; no declara completada esa integración.

### UC–06 — Verificación y cierre de 6.09

- Pruebas específicas de infraestructura: **23 tests / 230 assertions PASS**.
- Focalizadas de UC–06, UC–07 y Agenda: **616 tests / 4313 assertions PASS**.
- Regresión completa, `php artisan test`: **2476 tests / 16137 assertions PASS**, con SQLite `:memory:`, PDO SQLite y configuración sin caché.
- Sintaxis PHP, Pint acotado en modo `--test` y `git diff --check`: **PASS**, con código de salida 0.
- Infraestructura sin transiciones ni efectos funcionales propios: mutex y relectura en orden kinesióloga → paciente → turno, rechazo de recursos reasignados y callback transaccional sin reintentos automáticos. El consumidor debe autorizar y revalidar sus reglas dentro del callback.
- Los tres archivos coinciden por hashes con el código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md`, sin repetir pruebas.
- JavaScript y sintaxis JavaScript conservan los resultados históricos de 6.03; build conserva su último **PASS, 94 módulos** de 6.08; MariaDB conserva su certificación histórica de 6.03. **No se repitieron durante 6.09**.
- **Concurrencia real MariaDB PENDIENTE de 6.13:** SQLite verifica el contrato de solicitudes de locks, relectura y rollback; no certifica concurrencia real, esperas por bloqueos ni ausencia de deadlocks en MariaDB.
- **Dependencia C-02 / DT-03 PENDIENTE:** la integración clínica externa de planes y sesiones reales de Seguimiento continúa sin infraestructura disponible ni certificación.

### UC–06 — Verificación y cierre de 6.10

- Pruebas específicas de Confirmar asistencia: **58 tests / 389 assertions PASS**.
- Focalizadas de autorización, transacciones, UC–07 y Agenda: **629 tests / 4225 assertions PASS**.
- Regresión completa, `php artisan test`: **2534 tests / 16526 assertions PASS**, con SQLite `:memory:`, PDO SQLite y configuración sin caché.
- Sintaxis PHP, Pint acotado en modo `--test`, `git diff --check` y verificación de whitespace de los archivos nuevos: **PASS**, con código de salida 0 en las verificaciones finales.
- **Idempotencia y auditoría verificadas en el alcance de 6.10:** primera confirmación Pendiente → Confirmado sólo con inicio estrictamente futuro, sin anticipación mínima; replay autorizado sin nuevas escrituras ni auditorías, incluso después del inicio, sin reactivar estados posteriores. Programación y ocupación de agenda preservadas.
- Autorización y revalidación sobre el turno releído dentro de la transacción compartida, con locks kinesióloga → paciente → turno. Transición y auditoría obligatoria atómicas; los fallos impiden efectos parciales.
- Los seis archivos coinciden por hashes con el código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md`, sin repetir pruebas.
- JavaScript y sintaxis JavaScript conservan los resultados históricos de 6.03; build conserva su último **PASS, 94 módulos** de 6.08; MariaDB conserva su certificación histórica de 6.03. **No se repitieron durante 6.10**.
- **Concurrencia real MariaDB PENDIENTE de 6.13:** las pruebas SQLite no certifican carreras, esperas por bloqueos ni ausencia de deadlocks en MariaDB.
- **Dependencia C-02 / DT-03 PENDIENTE:** integración clínica externa de planes y sesiones reales de Seguimiento todavía sin infraestructura disponible ni certificación.
- **Enlaces seguros y comunicaciones externas PENDIENTES:** el contexto de canal no valida enlaces ni implementa su resolución o envío de comunicaciones; su autorización específica corresponde al canal antes de invocar la Action compartida.

### UC–06 — Verificación y cierre de 6.11

- Pruebas específicas de intención durable: **46 tests / 435 assertions PASS**.
- Focalizadas de UC–06, UC–07, Agenda y auditoría: **703 tests / 4842 assertions PASS**.
- Regresión completa, `php artisan test`: **2580 tests / 16961 assertions PASS**, con SQLite `:memory:`, PDO SQLite y configuración sin caché.
- Sintaxis PHP, Pint acotado en modo `--test`, `git diff --check` y verificación de whitespace de los archivos nuevos: **PASS**, con código de salida 0 en las verificaciones finales.
- Infraestructura persistente implementada: intención vinculada a la auditoría de cancelación mediante `auditoria_cancelacion_id` con `UNIQUE`, claves foráneas restrictivas y contexto mínimo del horario liberado, sin contenido clínico. Cancelaciones distintas pueden compartir profesional y horario.
- Registro idempotente dentro de una transacción activa en la misma conexión; rollback conjunto con la auditoría verificado. El replay no duplica ni reabre intenciones. `procesado_at` nulo identifica pendiente y una fecha identifica procesamiento efectivo futuro, sin implementar un consumidor.
- Los cuatro archivos coinciden por hashes con el código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md`, sin repetir pruebas.
- Migración aditiva preparada para una única tabla InnoDB; esquema y restricciones verificados en SQLite y DDL compilado para MariaDB 10.4.32 mediante `pretend`, **sin conexión al servidor ni ejecución real de la migración sobre MariaDB**.
- **Certificación real MariaDB PENDIENTE de 6.13:** la compilación del DDL y las pruebas SQLite no certifican ejecución, carreras, esperas por bloqueos ni deadlocks en MariaDB.
- **Integración funcional con cancelación PENDIENTE de 6.12:** esta infraestructura no cancela turnos ni modifica su ocupación. Procesos externos de evaluación, candidatos, ofertas y comunicaciones permanecen pendientes y no fueron implementados.
- **Dependencia C-02 / DT-03 PENDIENTE:** la integración clínica externa de planes y sesiones reales de Seguimiento continúa sin infraestructura disponible ni certificación.
- JavaScript y sintaxis JavaScript conservan los resultados históricos de 6.03; build conserva su último **PASS, 94 módulos** de 6.08; MariaDB conserva su certificación histórica de 6.03. **No se repitieron durante 6.11**.

### UC–06 — Verificación y cierre de 6.12

- Pruebas específicas de cancelación: **72 tests / 799 assertions PASS**.
- Focalizadas de UC–06, UC–07, Agenda y auditoría: **775 tests / 5641 assertions PASS**.
- Regresión completa, `php artisan test`: **2652 tests / 17760 assertions PASS**, con SQLite `:memory:`, PDO SQLite y configuración sin caché.
- Sintaxis PHP, Pint acotado en modo `--test`, diff y whitespace de los archivos nuevos: **PASS**. `git diff --check` y `git diff --cached --check` se verifican en este cierre.
- Cancelación transaccional e idempotente implementada para Primera consulta y Seguimiento: Pendiente/Confirmado → Cancelado, con límite inclusivo de **12 horas corridas** revalidado después de los locks kinesióloga → paciente → turno y autorización sobre el turno persistido.
- Estado, auditoría obligatoria e intención durable vinculada a la auditoría se registran atómicamente en la misma conexión y transacción. Los fallos revierten los tres efectos y preservan la ocupación previa; no se ejecutan procesos externos.
- Replay autorizado sin nuevas escrituras, auditorías, liberaciones ni intenciones, incluso después del inicio; no reactiva otros estados. Se preservan programación, motivo original y documentos; el motivo administrativo es opcional y se conserva únicamente en la auditoría de la cancelación efectiva.
- Los cuatro archivos coinciden por hashes con el código certificado, sin modificaciones posteriores a las pruebas; durante este cierre sólo se actualiza `AGENTS.md`, sin repetir pruebas.
- **Certificación de concurrencia real MariaDB PENDIENTE de 6.13:** SQLite verifica contratos de locks, relectura y rollback, pero no certifica carreras, esperas por bloqueos ni ausencia de deadlocks en MariaDB.
- **Dependencia C-02 / DT-03 PENDIENTE:** no se inventan planes ni sesiones reales de Seguimiento, no se consumen prestaciones ni se modifican otros turnos; la integración clínica externa continúa sin infraestructura disponible ni certificación.
- **Integraciones externas, enlaces seguros y comunicaciones PENDIENTES:** la intención durable no implementa evaluación efectiva, candidatos, ofertas ni notificaciones; el contexto de canal requiere validación específica previa y no implementa enlaces. La interfaz de autogestión permanece pendiente de sus subpasos posteriores.
- JavaScript y sintaxis JavaScript conservan los resultados históricos de 6.03; build conserva su último **PASS, 94 módulos** de 6.08; MariaDB conserva su certificación histórica de 6.03. **No se repitieron durante 6.12**.

### UC–06 — Certificación y cierre definitivo de 6.13

- **Estado: IMPLEMENTADO, VERIFICADO Y CERRADO en `616085b57b425e873ff0e957b0d558cb26add136`.**
- Certificación detallada: `docs/implementacion/turnos-gestionar-mis-turnos-613-certificacion.md`, con alcance, incidencias, entorno, SHA-256 certificados y limitaciones.
- Regresión ordinaria de Fase C: dirigidas **259 tests / 2040 assertions PASS**; completa **2712 tests / 17947 assertions PASS**, con `testing`, PDO SQLite `:memory:` y configuración sin caché; suite MariaDB especial excluida.
- Lint de **11 archivos PHP**, Pint acotado en modo `--test` y `git diff --check`: **PASS**. Las huellas del servicio, soporte y conjunto de once archivos permanecen certificadas.
- MariaDB **10.4.32 / InnoDB / REPEATABLE-READ**, destino exclusivo `127.0.0.1:3306/kinesiosoft_uc06_613_test`: **V1 2 tests / 49 assertions PASS**, **V2-A 1 test / 21 assertions PASS** y **V2-B 27 tests / 609 assertions PASS**.
- V2-B certificó **25 esperas reales, 8 carreras y 26 límites entre casos**, mutex compartidos, transiciones, revalidación, idempotencia, recuperación UNIQUE, timeout 1205, deadlock 1213 y rollback. Auditoría posterior **PASS**: sin residuos ni conexiones ajenas; esquema intacto, con 48 migraciones y 44 tablas.
- Incidencias corregidas: fuga de conexiones del harness (cierre explícito de conexiones propias incluso ante excepciones) y recuperación UNIQUE productiva (lectura actual mediante `lockForUpdate()` en la misma conexión/transacción, conservando `firstOrCreate()` y su savepoint). Guards y assertions originales preservados.
- MariaDB y SQLite son certificaciones separadas; no sumar sus cantidades ni las validaciones V1/V2-A/V2-B como una única ejecución. Las dirigidas forman parte del alcance de la regresión ordinaria completa. SQLite no certifica locking real y el ensayo de deadlock no implica ausencia universal de deadlocks.
- **C-02 / DT-03 e integraciones externas PENDIENTES:** planes y sesiones clínicas reales, evaluación efectiva de cupos, candidatos, ofertas, enlaces seguros y comunicaciones continúan fuera de esta certificación.
- Los antecedentes de UC–07 y de 6.03–6.12 se conservan, incluidas las menciones históricas de MariaDB entonces pendiente. JavaScript y build conservan sus resultados históricos; esta fase documental no repite pruebas, builds ni migraciones.

### UC–06 — Certificación técnica y manual de 6.14

- **Estado: IMPLEMENTADO, VERIFICADO Y CERRADO** en `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`.
- Certificación detallada: `docs/implementacion/turnos-gestionar-mis-turnos-614-certificacion.md`.
- Alcance: Confirmar asistencia y Cancelar turno desde el detalle de Mis turnos, referencia cifrada de programación, diálogos explícitos y recuperación del detalle vigente. Se reutilizan las Actions y las garantías transaccionales certificadas en 6.13, sin modificar esa infraestructura.
- Pruebas PHP acotadas: **1207 tests / 8555 assertions PASS**; regresión UC–07: **62 tests / 586 assertions PASS**. JavaScript: **26 pruebas nuevas y 155 existentes PASS**. Lint y Pint acotados: **PASS**; build: **PASS, 95 módulos**. Son resultados previamente aprobados; no se reejecutan en esta fase documental ni constituyen una nueva suite PHP global.
- Revisión estática **R1 satisfactoria** y **validación manual principal satisfactoria**, conforme a la certificación aprobada por el usuario, sobre SQLite temporal aislado con **48 migraciones y 44 tablas**. Auditoría final informada: **5 registros en auditorías, 3 intenciones y 12 turnos sintéticos**, conservados fuera del repositorio, sin limpieza en esta fase.
- La validación manual no recertifica concurrencia MariaDB ni repite exhaustivamente los bordes temporales, CSRF, propiedad e idempotencia cubiertos automáticamente. La certificación MariaDB de 6.13 permanece histórica e intacta, sin nueva ejecución.
- Observaciones UX registradas al cerrar **6.14**, atendidas en **6.15**: CSP de la fuente externa en el entorno temporal y diferenciación del indicador de progreso durante el diálogo respecto del envío efectivo; el alcance y la certificación se detallan en el bloque siguiente.
- **C-02 / DT-03 e integraciones externas PENDIENTES:** planes y sesiones clínicas reales, evaluación efectiva de cupos, candidatos, ofertas, enlaces seguros y comunicaciones no se consideran resueltos por 6.14.
- La transición posterior se registra en el bloque de **6.15** siguiente, conservando íntegros los resultados y limitaciones de **6.14**.

### UC–06 — Certificación y cierre de 6.15: UX final

- **6.15: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.** No se anticipa el hash de ese commit.
- Certificación detallada: `docs/implementacion/turnos-gestionar-mis-turnos-615-certificacion.md`.
- Alcance literal de la planificación V1.0: **Completar mensajes, estados vacíos, responsive, navegación por teclado, accesibilidad y preservación del contexto.**
- Mejoras acotadas a Mis turnos: tipografía local sin solicitudes a `fonts.bunny.net` ni relajación de CSP, salto al contenido, foco accesible, recuperación de filtros/paginación y estados vacíos; indicador de progreso limitado al transporte y mensajes diferenciados para rechazos HTTP conocidos y resultados inciertos. Se conservan las Actions, autorización, privacidad y garantías transaccionales certificadas.
- PHP dirigido: **290 tests / 2402 assertions PASS**, con SQLite `:memory:` y configuración de testing aislada. JavaScript: **41 pruebas de Mis turnos y 155 existentes PASS**. Lint, Pint acotado y `git diff --check`: **PASS**; build: **PASS, 95 módulos**. Son certificaciones separadas, sin nueva regresión integral ni repetición de MariaDB de 6.13.
- Validación manual: **cuatro comprobaciones UX satisfactorias**, conforme al resultado informado por el usuario; SQLite temporal aislado con **48 migraciones y 44 tablas**, auditoría final **PASS**. No se incorporan al repositorio auxiliares, sesiones, claves ni evidencia temporal.
- La revisión manual es acotada y no constituye una auditoría exhaustiva de accesibilidad o navegadores ni una certificación de concurrencia MariaDB. **C-02 / DT-03 e integraciones externas permanecen PENDIENTES**; no son defectos de UX resueltos por 6.15. La regresión integral corresponde a 6.17.
- La transición posterior se registra en el bloque de **6.16** siguiente. Se conserva la certificación de UX de 6.15, sin repetir sus pruebas visuales o build.

### UC–06 — Verificación y cierre de integraciones de 6.16

- **6.16: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**, sin anticipar su hash y sin declarar implementados consumidores externos pendientes.
- Alcance literal V1.0: **Verificar contratos con UC-07, Agenda, Seguimientos, UC-08, enlaces, recordatorios y adelantamiento; distinguir lo implementado de lo pendiente.** Certificación: `docs/implementacion/turnos-gestionar-mis-turnos-616-certificacion.md`.
- Pruebas PHP dirigidas: **45 tests / 617 assertions PASS**, PHP 8.2.12 / PHPUnit 11.5.55; `testing`, configuración sin caché y SQLite `:memory:` efectivo, con PDO MySQL ausente. Lint del test ampliado, Pint acotado `--test` y `git diff --check`: **PASS**.
- Se añadió un único recorrido HTTP a la prueba existente de integración: UC-07 → confirmar → cancelar/replay → reservar nuevamente el cupo, verificando D-01, ocupación, originales privados, auditoría e intención única pendiente. Se reutilizaron pruebas administrativas de Seguimiento, estados profesionales, programación obsoleta, canal prevalidado, autorización, CSRF y rollback. No se modificaron Actions ni infraestructura certificada.
- **Implementado y verificable:** integración administrativa con UC-07 y Agenda, autorización, privacidad documental, auditoría e idempotencia; confirmación conserva ocupación y cancelación la libera. El registro durable de intención está operativo, separado de su procesamiento futuro.
- **Preparado sin consumidor operativo:** contexto `ENLACE_SEGURO` de las Actions y registro de intención con `procesado_at`. El enum no valida enlaces; la referencia cifrada del portal no es un enlace externo. No se certifica procesamiento asíncrono por existir una intención pendiente.
- **Pendientes externos:** C-02 / DT-03 (planes y sesiones reales), inicio/restauración clínica de UC-08, reprogramación profesional completa, resolución/vigencia/revocación de enlaces, recordatorios/Agenda Diaria Automática, evaluación efectiva, candidatos, ofertas y aceptación. UC-06 respeta estados profesionales y preserva otros turnos; eso no certifica los flujos clínicos ausentes ni las decisiones DX-01–DX-07.
- La certificación MariaDB de 6.13 y las validaciones visuales de 6.14–6.15 se reutilizan como antecedentes separados, sin repetición durante 6.16. La regresión integral y la transición posterior se registran en el bloque de **6.17** siguiente; se conservan los resultados y límites de 6.16.

### UC–06 — Regresión integral y cierre de 6.17

- **6.17: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**, sin anticipar su hash. Certificación: `docs/implementacion/turnos-gestionar-mis-turnos-617-certificacion.md`.
- Alcance literal V1.0: **Ejecutar regresión integral, pruebas PHP/JavaScript, build, integraciones MariaDB y verificaciones manuales de UX.** Conforme a la autorización específica, se ejecutaron PHP/JavaScript globales y build; MariaDB y UX manual reutilizan las evidencias certificadas de 6.13–6.15, sin nuevas ejecuciones.
- Suite PHP ordinaria completa, sin filtros: **2760 tests / 18474 assertions PASS**, exit code 0, 08:15.712; PHP 8.2.12 / PHPUnit 11.5.55. Sin failures, errors, skipped, risky ni warnings. Preflight efectivo: `testing`, configuración sin caché y SQLite `:memory:`, con PDO MySQL ausente en el proceso PHPUnit. Se invocó PHPUnit directamente con `phpunit.xml` para conservar `-n` y las extensiones restringidas; no se repitió la suite global.
- JavaScript completo: **196 PASS / 0 FAIL / 0 SKIP**, exit code 0: 150 wizard, 41 Mis turnos y 5 bloqueo-form. El primer intento fue bloqueado antes de ejecutar casos por `spawn EPERM`; el mismo comando pasó tras autorizar sus subprocesos, sin cambios de código o tests.
- `npm run build`: **PASS, 95 módulos**, Vite 7.3.1, 5.05 s, exit code 0. Aviso informativo de Browserslist sobre datos antiguos; sin actualización de dependencias.
- Lint y Pint `--test`: **PASS en los 54 archivos PHP del alcance UC-06**; `git diff --check`: **PASS**. No constituye Pint global sobre deuda ajena. Integridad de 683 archivos versionados de código, pruebas, configuración y dependencias conservada, sin cambios productivos ni de tests durante la certificación.
- Exclusiones explícitas de la ejecución actual: suites reales `tests/Integration/MySql` y `tests/Integration/Uc06MariaDb`, fuera del `phpunit.xml` ordinario. Se conserva MariaDB 6.13 **27 tests / 609 assertions PASS**, con sus validaciones independientes V1/V2-A, y UX manual de 6.14–6.15; la integración dirigida de 6.16 (**45/617 PASS**) es antecedente, no una cantidad a sumar a la regresión global.
- No se detectaron regresiones en el alcance ejecutado. **C-02 / DT-03 e integraciones externas permanecen pendientes**, según 6.16; SQLite no recertifica locking MariaDB ni acredita consumidores ausentes. El cierre documental definitivo se registra en el bloque de **6.18** siguiente, sin nuevas pruebas.

### UC–06 — Cierre definitivo de 6.18 y del incremento

- **6.18 COMPLETADO; UC–06 IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.** Informe, matriz CA-01–CA-40 y resumen para revisión futura: `docs/implementacion/turnos-gestionar-mis-turnos-618-cierre.md`.
- Alcance literal V1.0: **Elaborar informe de cierre, matriz de cumplimiento de los 40 criterios de aceptación, evidencias y estado final de Git.** Los subpasos 6.01–6.17 cuentan con su seguimiento, commits y certificaciones previas; esta fase es exclusivamente documental.
- Baseline final reutilizado de 6.17: **2760 tests / 18474 assertions PHP PASS**, **196 JavaScript PASS**, build **PASS, 95 módulos**, lint/Pint acotado **54 archivos PASS** y aislamiento SQLite `:memory:` verificado. No se repiten estas ejecuciones.
- Concurrencia real reutilizada de 6.13: MariaDB **27 tests / 609 assertions PASS**, 25 esperas, 8 carreras y 26 límites; V1 **2/49** y V2-A **1/21 PASS** son validaciones separadas. UX funcional/visual de 6.14–6.15 e integración de 6.16 **45/617 PASS** conservan su evidencia y límites propios, sin sumar resultados como una única ejecución.
- La matriz clasifica los **40 criterios** sin declarar un 40/40 PASS: **30** con pruebas localizadas; **CA-14** con revisión estática sin ensayo específico localizado del fallo de carga inicial; **CA-17** con revisión del límite administrativo sin flujo clínico UC-08 real; **CA-16 y CA-26** condicionados parcialmente por C-02 / DT-03; y **seis criterios de integración** pendientes de sus componentes externos, con los contratos de salida disponibles verificados.
- No se identificó una contradicción material que impida cerrar el alcance implementado. Persisten las dependencias de planes/sesiones reales, UC-08, reprogramación profesional completa, resolvedor de enlaces, recordatorios/Agenda Diaria Automática, procesamiento de intenciones, candidatos y ofertas. Un enum de canal o una intención persistida no son consumidores operativos; DX-01–DX-07 no se resuelven implícitamente.
- Los 683 archivos de código, pruebas, configuración y manifiestos conservan la huella certificada en 6.17. Las menciones a subpasos siguientes pendientes en certificaciones anteriores se conservan como estados históricos al emitir cada documento; el estado vigente es este cierre, sin reescribir sus evidencias.
- Al cierre de UC–06 no había siguiente incremento iniciado. La integración futura requiere revisión y autorización independientes, comprobación del destino y de migraciones aplicables, conservación de las fuentes locales congeladas y decisión humana sobre commit/PR/merge/despliegue. Este cierre no ejecuta ni autoriza automáticamente esas operaciones o la limpieza de sesiones y bases.

### Obligaciones del incremento UC–06

1. Mantener congeladas D-01–D-18, DT-01–DT-12 y UX-01–UX-05; ante una incompatibilidad no prevista, detenerse e informar antes de cambiar decisiones.
2. Preservar la infraestructura de UC–07 y Agenda, aplicando únicamente las armonizaciones planificadas y autorizadas.
3. Respetar los seis estados canónicos: Pendiente, Confirmado, En atención, Atendido, Ausente y Cancelado; su ocupación de agenda se rige por D-02 y DT-01 de UC–06, con armonización prevista en 6.04.
4. Mantener las restricciones temporales de confirmación y cancelación establecidas en D-03, D-04 y D-06, con revalidación autoritativa y `RelojAplicacion`.
5. Respetar autorización, privacidad documental, transacciones, concurrencia, idempotencia y auditoría conforme a D/DT y los patrones existentes.
6. No implementar módulos externos completos; respetar los contratos C-01–C-14 y las decisiones externas DX-01–DX-07 de la matriz, sin inventar datos clínicos ausentes.
7. Trabajar únicamente en el subpaso expresamente autorizado y detenerse al finalizarlo, sin adelantar los siguientes.
8. Mantener `docs/archivo/` sin inspección ni modificación, y proteger las fuentes congeladas.
9. Conservar las reglas transversales de arquitectura, Laravel, seguridad, auditoría, pruebas, archivos protegidos y Git de este repositorio.

## Antecedente cerrado — UC–07 Solicitar turno V2

Incremento histórico implementado, certificado y cerrado:

**UC–07 Solicitar turno V2 — CERRADO**

Rama histórica de UC–07:

`feature/turnos-solicitar-turno`

Commit base oficial histórico de UC–07:

`9d9b1510402e64c310f02ec1014b24333c13ed0c`

Ese commit corresponde al cierre documental de **UC–XX Configurar agenda de atención** y conserva como antecesor técnico el incremento de Agenda completo.

Base documental previa al Paso 7:

`808794f7fc0e69efba70c9c9fc3dc702b7c50a1a`

Estado de cierre de UC–07:

- Implementación V1, subpasos 7.01–7.28: **EJECUTADOS Y CERTIFICADOS COMO BASELINE V1**. No son trabajo pendiente ni deben reejecutarse automáticamente.
- UC–07 funcional V2: **APROBADO Y CONGELADO**.
- D-01…D-16 V2: **APROBADAS Y CONGELADAS**.
- DT-01…DT-07 V2 y Consolidación Técnica/Física V2.0: **APROBADAS Y CONGELADAS**.
- UX-01…UX-03 V2 y Consolidación UX V2.0: **APROBADAS Y CONGELADAS**.
- Stitch V2: **APROBADO Y CONGELADO**.
- Gap analysis V2 contra implementación actual: **COMPLETO Y APROBADO**.
- Planificación delta V2 7.29–7.52: **APROBADA, CONGELADA Y VERSIONADA** en `956e68e` (`docs: congelar plan delta V2 de UC-07`).
- Implementación delta V2 7.29–7.52: **COMPLETADA**.
- Recertificación V2-P1–V2-P6: **PASS**.
- Cierre definitivo V2: **COMPLETO**, documentado en 7.52.

Planificación ejecutada de UC–07, conservada como antecedente:

`docs/implementacion/turnos-solicitar-turno-planificacion.md`

Las fuentes específicas permanecen en `docs/_codex_sources/turnos/`.

Planificación ejecutada: **Parte II V2 7.29–7.52 COMPLETADA**, con V2-P1–V2-P6 PASS. La Parte I 7.01–7.28 permanece exclusivamente como antecedente histórico de la implementación existente.

Último subpaso de UC–07 completado: **7.52 — Cierre documental definitivo de UC–07 V2**.

No quedan subpasos V2 por ejecutar. UC–06, incremento posterior a UC–07, también queda cerrado conforme a su bloque histórico en este archivo. Esos cierres no autorizan implícitamente otros trabajos; el incremento activo y su alcance se identifican en el bloque inicial.

La ejecución se realizó mediante autorizaciones y prompts independientes. El cierre no autoriza reejecutar subpasos, limpiar fixtures ni iniciar otro trabajo. Toda intervención posterior requiere alcance y autorización explícitos del usuario.

### Baseline definitivo UC–07 V2

- HEAD técnico definitivo: `ba97c4b8e0a1df53c1c456da424b549db9798828` — `fix: unificar foco visual de pasos del wizard V2`.
- MC-03 completó el patrón de MC-02 en los headings de los Pasos 1–4: foco programático y semántica preservados, sin ring accidental; foco visible interactivo y stepper intactos, sin cambios JavaScript productivos.
- 7.51 reejecutado después de MC-03: **2231 tests / 14391 assertions PASS**, 158,06 s; cero failures, errors, skipped, risky y warnings informados; exit code 0.
- Entorno global: `APP_ENV=testing`, SQLite `:memory:`, PDO sqlite, `config_cached=false`; sin utilizar la base de desarrollo.
- JavaScript del wizard: **150 PASS / 0 FAIL / 0 SKIP**, 455,409 ms, exit code 0; ambos `node --check` PASS.
- `npm run build`: **PASS**, 94 módulos, 3,55 s, exit code 0; aviso informativo de Browserslist, sin actualizar dependencias.
- MariaDB/InnoDB histórica, 7.49: **30 tests / 1219 assertions PASS**, respaldados por la sección 8.1 de `docs/implementacion/turnos-solicitar-turno-cierre.md`; no constituyen una nueva certificación de UC–06.
- `git diff --check`: limpio al certificar 7.51. Pint de la microcorrección pasó antes del commit; sin cambios PHP durante la reejecución, no se repitió Pint ni `php -l`.
- Documento de cierre: `docs/implementacion/turnos-solicitar-turno-cierre.md`; preparado en 7.52 como cierre definitivo documental del incremento UC–07 V2.

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

El baseline previo a UC–07 y la certificación V1 (incluidas P1–P7) son históricos. El baseline de cierre de UC–07 es el definitivo V2 indicado arriba, con V2-P1–V2-P6 PASS y regresión global reejecutada en 7.51. El baseline inicial de UC–06 quedó certificado en 6.03 y se registra en su bloque histórico de este archivo. Ninguno de estos resultados implica un PASS de Pint global sobre deuda histórica ajena al incremento.

El documento `docs/implementacion/turnos-solicitar-turno-cierre.md` contiene el cierre definitivo V2 y conserva la evidencia V1 como anexo histórico. Su preparación documental no autoriza staging, commit, push ni limpieza de datos; el usuario controla la revisión y el versionado final.

Si la rama activa, el commit base o el estado Git no coinciden con lo esperado, detenerse e informarlo antes de modificar archivos.

No modificar, agregar, eliminar, inspeccionar ni limpiar `docs/archivo/` salvo instrucción explícita del usuario.

## Preflight técnico certificado para UC–07

Estado histórico del entorno verificado antes de comenzar UC–07:

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

Estos datos corresponden al preflight histórico previo a V1, no a una comprobación actual. La limitación inicial de verificación del SAPI web se resolvió en V1 y se recertificó para V2 en 7.50 mediante Apache/PHP real. El cierre registra los resultados, sin repetir operaciones de entorno.

## Fuente de verdad para UC–07 Solicitar turno

Las fuentes locales de apoyo para Turnos están en:

`docs/_codex_sources/turnos/`

Para consultar el antecedente documental de UC–07, leer primero:

`docs/_codex_sources/turnos/README_Codex_sources.md`

No inventar nombres de archivos dentro de esa carpeta. Utilizar el README y el contenido real disponible para localizar las fuentes pertinentes a UC–07.

Jerarquía de decisión de UC–07, conservada como antecedente del incremento cerrado:

1. Diccionario de Datos vigente: persistencia estructural congelada.
2. Fuente funcional UC–07 V2: `docs/_codex_sources/turnos/UC-07_Solicitar_turno.txt`.
3. D-01…D-16, DT-01…DT-07 y Diseño Técnico/Físico V2.0: `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_TECNICO_V2.0.md`. Incluye el cambio incremental explícito `descripcion VARCHAR(500) NULL`.
4. UX-01…UX-03 y Consolidación UX V2.0: `docs/_codex_sources/turnos/DECISIONES_UC07_DISENO_UX_V2.0.md`.
5. Parte II delta V2 de la planificación ejecutable congelada: `docs/implementacion/turnos-solicitar-turno-planificacion.md`, que gobierna el orden operativo de implementación y no puede contradecir una decisión superior.
6. Modelo Relacional vigente: apoyo visual del modelo; ante discrepancia prevalece el Diccionario de Datos.
7. Código real existente: convenciones técnicas y patrones reutilizables.
8. Referencias Stitch V2 y su contrato `docs/_codex_sources/turnos/prototipo_stitch_uc07/README.md`: sólo UI/UX; nunca fuente de dominio o persistencia.

Excepción incremental V2 ya aprobada: el Diccionario vigente todavía puede no enumerar `documentos_clinicos.descripcion`, pero DT-04 V2 y la Parte II 7.30 autorizan expresamente agregar `descripcion VARCHAR(500) NULL` mediante una migration aditiva, sin backfill ni modificación de migrations históricas. Esta ausencia documental conocida no constituye un bloqueante para 7.30 y no autoriza ninguna otra desviación estructural respecto del Diccionario.

Esta jerarquía conserva el orden de las fuentes declarado en el README local e incorpora la planificación en su lugar operativo subordinado. Los casos de uso relacionados sólo aportan contexto y no autorizan ampliar UC–07.

V1 sólo puede consultarse como antecedente histórico y no gobierna el objetivo V2. Los prompts blindados autorizan y delimitan la ejecución de cada subpaso, pero no sustituyen ni pueden contradecir las fuentes congeladas ni la Parte II de la planificación.

Configurar agenda es **ANTECEDENTE IMPLEMENTADO CONSUMIDO POR UC–07**. No reabrirlo salvo cambios mínimos de reutilización expresamente previstos en el plan.

Las decisiones D-01…D-16, DT-01…DT-07 y UX-01…UX-03 no deben reabrirse durante programación salvo que aparezca una incompatibilidad técnica real, demostrable y no prevista. En ese caso, detenerse, documentar la evidencia y pedir decisión al usuario antes de modificar arquitectura o reglas.

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

### D-03 — Inicio del flujo y disponibilidad previa al profesional

- El paciente elegible puede iniciar UC–07 aunque todavía no se conozca disponibilidad.
- Wizard canónico V2, exactamente cuatro pasos:
    1. **Fecha y horario**.
    2. **Elegir profesional**.
    3. **Motivo y documentación**.
    4. **Revisar y confirmar**.

- El flujo V1 que comenzaba por motivo/documentación y profesional está superado y no es el flujo activo.
- Seleccionar primero fecha e `inicio_at`; después, únicamente una profesional elegible con disponibilidad efectiva para ese inicio.
- Profesionales distintas pueden compartir `inicio_at` con distinto `fin_at`. No existe intervalo ni duración universal; el intervalo real se conoce al seleccionar profesional y `fin_at` se deriva autoritativamente en backend.
- Si no hay disponibilidad en la ventana consultada, mostrar un vacío válido y permitir consultar otros períodos. Una fecha no consultada no equivale a una fecha sin disponibilidad; un error técnico tampoco.

### D-04 — Kinesióloga habilitada

- Reutilizar las reglas canónicas existentes de KinesioSoft.
- Habilitación profesional y disponibilidad temporal son conceptos distintos.
- No crear una regla paralela independiente de las existentes.
- Sólo profesionales elegibles aportan disponibilidad agregada y sólo las disponibles para `inicio_at` aparecen como opciones en el Paso 2.

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
- La navegación visual V2 utiliza ventanas de siete días.
- El límite técnico congelado para una consulta de disponibilidad es de hasta 31 días por request, configurable.
- Ese límite por request no constituye un máximo de reserva.
- Cada fecha usa la configuración aplicable a esa fecha.
- No extrapolar una configuración donde no exista ninguna aplicable.
- Una configuración abierta continúa vigente hasta ser sustituida conforme a las reglas de Agenda.

### D-09 — Adjuntos clínicos

- Adjuntos opcionales, múltiples, 0..5.
- Política MVP: hasta 5 archivos.
- Máximo: 10 MiB por archivo.
- Máximo total de archivos por confirmación: 25 MiB.
- Formatos permitidos: PDF, JPEG/JPG y PNG.
- Validar backend, MIME real, tamaño individual, cantidad y total.
- Antes de confirmar, el paciente puede agregar/quitar/reemplazar archivos en el wizard.
- Cada archivo válido requiere descripción individual: trim, mínimo 1 carácter significativo y máximo 500. Quitar el archivo quita su descripción; reemplazarlo exige revisar/completar la del nuevo archivo.
- Un archivo inválido se rechaza individualmente: no integra la selección válida, no cuenta ni suma tamaño, no requiere descripción y no bloquea el avance si los restantes cumplen. Un archivo válido sin descripción permanece seleccionado y bloquea el avance hasta corregirse.
- Después de confirmar, el conjunto asociado al turno es histórico e inmutable.
- El upload del paciente no equivale a validación clínica profesional.
- Los documentos son privados y sólo se sirven mediante autorización de la aplicación.

### D-10 — Inmutabilidad del snapshot inicial

- Antes de confirmar, motivo, adjuntos y descripciones son editables.
- Después de confirmar, el motivo original no se edita ni se sobrescribe.
- El conjunto de archivos asociado a la solicitud confirmada no se reemplaza retroactivamente.
- La descripción original de cada documento también integra el snapshot inmutable.
- Cambios clínicos posteriores deben modelarse como operaciones nuevas o estados/clasificaciones previstos, sin destruir el original.
- Cancelar o reprogramar un turno no reescribe el motivo ni elimina sus documentos originales.

### D-11 — Concurrencia

- Ver o seleccionar un slot no lo reserva.
- No existe hold temporal en el MVP.
- La confirmación revalida autoritativamente D-01, DT-02, agenda aplicable, grilla, tiempo, bloqueos y ocupación.
- Ante dos confirmaciones incompatibles, como máximo una puede consolidarse.
- Multi-tab del mismo paciente también queda protegido.
- Doble submit/retry de la misma intención no debe duplicar el turno.

Recuperación contextual V2, reevaluando disponibilidad mediante los GET correspondientes:

- Profesional perdida con alternativas para el mismo inicio: conservar fecha + hora, invalidar profesional y volver al **Paso 2**.
- Hora perdida pero fecha con otras horas: conservar fecha, invalidar hora + profesional y volver al **Paso 1**.
- Fecha sin horas: invalidar fecha + hora + profesional y volver al **Paso 1**.
- D-01 adquirida: finalizar UC–07 hacia **Mis turnos**.
- Conservar motivo, archivos válidos, descripciones y UUID cuando sean compatibles; no reiniciar todo el formulario. Un error técnico de reevaluación no demuestra pérdida de disponibilidad.

### D-12 — Atomicidad funcional

- La confirmación es una sola operación todo-o-nada.
- Sólo hay éxito cuando turno, motivo y todos los adjuntos con sus descripciones del resumen confirmado quedaron consolidados de forma coherente.
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
- consulta agregada de disponibilidad;
- selección de fecha y hora de inicio;
- selección de kinesióloga elegible y disponible para ese inicio;
- motivo obligatorio;
- adjuntos opcionales con descripción individual obligatoria;
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

## Diseño técnico/físico congelado de UC–07 V2

### DT-01 — Tipo de turno

- Mantener `turnos.tipo` como `string(30)`.
- Conservar `TipoTurnoEnum` respaldado por string con:
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
- Conservar el predicado canónico de alto nivel en `User`, `puedeAtenderComoKinesiologo()`.
- Un servicio de elegibilidad debe listar y validar profesionales reutilizando ese predicado.
- Revalidar elegibilidad al construir disponibilidad agregada, al listar profesionales para `inicio_at` y al confirmar.
- No exigir agenda disponible para considerar habilitada a una profesional.
- No inventar vencimientos de matrícula ni exigir que catálogos históricos de título/especialidad permanezcan activos si la regla actual no lo exige.

### DT-03 — Motor de disponibilidad

- Conservar `DisponibilidadPrimeraConsultaService` como motor individual canónico.
- La capa fina `DisponibilidadAgregadaPrimeraConsultaService`, implementada en 7.34, consume profesionales elegibles → slots individuales → fechas disponibles → horas de inicio deduplicadas → profesionales disponibles para `inicio_at`. No duplicar grilla, overlap, estados ocupantes ni reloj ni recrear el servicio existente.
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

Conservar las tablas implementadas:

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
- `descripcion` (incorporada en 7.30);
- `ruta_storage`;
- `mime_type`;
- `tamanio_bytes`;
- timestamps.

Reglas UC–07:

- Incorporación V2 implementada en 7.30: `documentos_clinicos.descripcion VARCHAR(500) NULL`, mediante migration nueva aditiva, sin reescribir migrations históricas ni backfill artificial. Documentos históricos pueden conservar `NULL`; UC–07 exige descripción individual con trim y longitud de 1 a 500 caracteres significativos para nuevos adjuntos. La migration ya existe y fue aplicada de forma autorizada antes de 7.50; no recrearla ni reaplicarla por el cierre.
- `origen = SOLICITUD_TURNO`.
- `estado_validacion = PENDIENTE`.
- `tipo_documento_clinico = SIN_CLASIFICAR`.
- `ruta_storage` única y generada por backend.
- Storage exclusivamente privado.
- Sin URL pública directa.
- Sin pre-upload/staging persistente en el MVP.
- Política central: 5 archivos, 10 MiB individual, 25 MiB total, PDF/JPEG/JPG/PNG.
- Mantener nombre original y descripción como metadatos; nunca usarlos como ruta física confiable.
- Encapsular storage/validación/cleanup en un servicio documental reutilizable.
- Contrato documental V2 y límites efectivos recertificados mediante el flujo web Apache/PHP real en 7.50; sus límites de evidencia se registran en el cierre.

### DT-05 / DT-06 — Concurrencia, idempotencia y atomicidad

- Toda confirmación pasa por una única Action, conceptualmente `ConfirmarPrimeraConsultaAction`.
- Ejecutar la creación dentro de `DB::transaction()`.
- Usar `kinesiologas.id` como mutex de agenda mediante `lockForUpdate()`.
- Bloquear también `pacientes.id` para proteger D-01 incluso entre profesionales diferentes.
- Conservar el orden de locks certificado: kinesióloga → paciente. No adquirir locks de reserva durante la navegación ni introducir holds.
- Después de adquirir locks, releer y revalidar condiciones mutables; no confiar en lecturas previas.
- Toda operación futura que cree turnos o altere intervalo/estado de manera que cambie ocupación debe respetar el mismo convenio de locking por kinesióloga.

Conservar la persistencia V1 ya implementada en `turnos`:

- `clave_idempotencia`: nullable, UNIQUE.
- índice no único `(paciente_id, tipo, estado, inicio_at)` para D-01.

Idempotencia:

- UC–07 genera una UUID por flujo.
- La UUID identifica la intención de solicitar la Primera consulta, no un slot particular.
- Conservarla al cambiar fecha, hora, profesional, motivo, archivos o descripciones y durante 422/409 recuperables, red, resultado incierto y retry.
- Si la UUID ya corresponde a un turno consolidado del mismo paciente y contexto, devolver ese mismo turno sin recrear nada.
- Un replay ya consolidado se resuelve antes de exigir nuevamente condiciones propias de una creación nueva.
- Si la UUID no está consolidada, ejecutar la creación autoritativa completa.
- Rechequear UUID dentro de la transacción antes de insertar.

Atomicidad:

- Turno, documentos, descripciones, asociaciones y auditoría deben consolidarse como una única operación funcional.
- Los archivos físicos nuevos se registran para compensación.
- Ante excepción previa al commit: rollback DB + eliminación de archivos nuevos.
- No usar retries automáticos del callback transaccional si pueden repetir efectos de filesystem.
- Deadlock/timeout debe dejar la operación sin efectos parciales y permitir retry seguro con la misma UUID.
- Conservar la reconciliación segura ya certificada de archivos privados huérfanos sin referencia DB y con antigüedad segura, sin tocar archivos válidos, junto con los advisory locks documentales.

### DT-07 — Orquestación, seguridad, Mis turnos y auditoría

Rutas implementadas en UC–07 V2 (antecedente cerrado):

- `GET /turnos/mis-turnos` → `turnos.mis-turnos.index`.
- `GET /turnos/solicitar` → `turnos.solicitar.create`.
- `GET /turnos/solicitar/disponibilidad` → `turnos.solicitar.disponibilidad`.
- `GET /turnos/solicitar/profesionales-disponibles` → `turnos.solicitar.profesionales-disponibles`.
- `POST /turnos/solicitar` → `turnos.solicitar.store`.
- `GET /documentos-clinicos/{documento}/descargar` → `documentos-clinicos.download`.

Middleware para UC–07 del paciente:

- `auth`.
- `verified`.
- `perfil.completo`.
- `perfil.paciente`.

La descarga documental debe usar autenticación, perfil completo y una Policy específica del documento, no una URL pública.

`Mis turnos` mínimo certificado al cierre de UC–07, a evolucionar únicamente mediante los subpasos autorizados de UC–06:

- obtener siempre al paciente desde el usuario autenticado;
- no aceptar `paciente_id` arbitrario;
- mostrar al menos tipo, profesional, fecha, hora y estado;
- ofrecer `Solicitar nuevo turno`;
- usar paginación cuando corresponda;
- no implementar en este incremento cancelación, reprogramación, confirmación de asistencia ni otras acciones de UC–06.

Requests implementados en UC–07:

- `ConsultarDisponibilidadPrimeraConsultaRequest`.
- `ConsultarProfesionalesDisponiblesPrimeraConsultaRequest`.
- `ConfirmarPrimeraConsultaRequest`.

Disponibilidad agregada recibe `desde` y `hasta`, sin requerir `kinesiologa_id`, y devuelve fechas/horas de inicio. Profesionales disponibles recibe `inicio_at` y devuelve sólo profesionales elegibles con slot válido para ese inicio, con su intervalo real informativo. Ambas consultas admiten `200` vacío.

Confirmación acepta del cliente únicamente:

- `motivo_solicitud`;
- `kinesiologa_id`;
- `inicio_at`;
- `clave_idempotencia`;
- adjuntos estructurados: `adjuntos[n][archivo]` y `adjuntos[n][descripcion]`, sin arrays paralelos.

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

- `200`: disponibilidad o profesionales consultados correctamente, incluso vacíos.
- `422`: validación de entrada, archivos o descripciones; motivo/documentos vuelven al Paso 3 conservando estado compatible.
- `409`: conflicto de estado al revalidar una creación aún no consolidada.
- `201`: creación nueva exitosa.
- `200`: replay idempotente de una operación ya consolidada.
- Un error interno de integridad no debe disfrazarse como falta de disponibilidad.

Conservar inicialmente los códigos `primera_consulta_activa`, `profesional_no_elegible` y `slot_no_disponible`. No agregar códigos independientes para profesional/hora/fecha perdida: la recuperación contextual reevalúa mediante los GET V2. No ramificar por mensajes humanos.

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
- proteger la descripción original;
- proteger contenido/ruta física;
- proteger la asociación documento-turno;
- permitir que futuros UC autorizados evolucionen únicamente `estado_validacion` y `tipo_documento_clinico` según corresponda.

Auditoría:

- reutilizar `AuditoriaService`;
- auditar creación de Primera consulta;
- auditar cada documento clínico cargado;
- auditar acceso efectivo mediante descarga de documento clínico;
- no duplicar contenido binario, motivo clínico completo ni descripción clínica completa en snapshots de auditoría.

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

Para Agenda Diaria Automática, consultar las fuentes y el estado del bloque inicial, junto con la solicitud específica del subpaso. Las referencias siguientes a UC–06 y UC–07 corresponden a incrementos cerrados y no habilitan reejecutarlos.

Para UC–06, antes de modificar, leer las fuentes congeladas pertinentes, el subpaso de `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md` y su prompt blindado independiente. No ejecutar un subpaso sin autorización específica ni avanzar automáticamente al siguiente. La planificación de UC–07, incluidas la Parte I V1 y la Parte II V2 7.29–7.52, está ejecutada y cerrada; sólo aporta trazabilidad histórica.

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

### Infraestructura V1 protegida

**NO REABRIR INFRAESTRUCTURA V1 CERTIFICADA SIN EVIDENCIA.** Preservar enums y estados ocupantes canónicos, D-01, elegibilidad, motor individual y AgendaQuery, grilla, overlap semiabierto, `RelojAplicacion`, locking kinesióloga → paciente, idempotencia/replay, storage privado, compensación, advisory locks documentales, reconciliación, Policy y descarga documental, auditoría y backend de Mis turnos. Adaptar sólo el delta expresamente previsto y autorizado.

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

## Contratos existentes de Agenda consumidos por UC–07 y preservados en UC–06

Las reglas de Agenda ya implementadas son infraestructura compartida y no deben reescribirse desde cero. Las armonizaciones de UC–06 se limitan a su planificación y subpasos autorizados.

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
- Para UC–07 no duplicar el contenido completo de `motivo_solicitud`, la descripción clínica completa ni archivos dentro de auditoría.
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

Para UC–07, el diseño físico V1 ya implementado incluye:

- modificación de `turnos` para `clave_idempotencia` nullable UNIQUE;
- índice `(paciente_id, tipo, estado, inicio_at)`;
- creación de `documentos_clinicos`;
- creación de `documentos_clinicos_origen_turno`.

El único cambio físico del delta V2 quedó implementado:

- migration aditiva `2026_10_22_000001_add_descripcion_to_documentos_clinicos_table.php` para `documentos_clinicos.descripcion VARCHAR(500) NULL`, sin backfill artificial, creada en 7.30 y aplicada de forma autorizada antes de 7.50; 47 Ran / 0 Pending en esa verificación.

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
5. Ejecutar `php artisan test` completo únicamente en las puertas de regresión definidas por la planificación y el prompt autorizado del incremento activo; las puertas ya ejecutadas de UC–06 y UC–07 son antecedentes históricos.
6. Ejecutar Pint sobre los archivos modificados o el alcance explícitamente indicado; no reformatear masivamente archivos ajenos para intentar producir un Pint global verde.
7. Ejecutar `git diff --check` antes de cierres/checkpoints relevantes.
8. Ejecutar `npm run build` cuando el cambio afecte frontend o en puertas de regresión definidas.

Baseline histórico de partida de UC–07, anterior a su cierre V2:

`1376 passed (8842 assertions)`

Build histórico de partida de UC–07:

`npm run build`: PASS.

Limitación de las pruebas SQLite:

- PHPUnit usa SQLite `:memory:`.
- Eso no certifica locking real de MariaDB.
- Las carreras de DT-05/DT-06 de UC–07 fueron certificadas para V1 y recertificadas contra MariaDB/InnoDB en 7.49 para V2. Para UC–06, el baseline y las nuevas verificaciones corresponden a 6.03 y a los subpasos autorizados de su planificación; no se hereda un PASS de concurrencia nuevo.

No modificar tests para “hacerlos pasar” si eso debilita o cambia una regla congelada.

Cuando un test deba actualizarse por un cambio funcional planificado, mantener o aumentar la cobertura semántica.

## Frontend

- Blade + Tailwind + Alpine + Vite.
- SweetAlert2 ya está disponible globalmente.
- No agregar imports/CDN duplicados.
- Mantener desktop y mobile conforme a las decisiones UX aprobadas y patrones visuales reales de KinesioSoft.
- Los prototipos visuales nunca prevalecen sobre las fuentes funcionales y técnicas congeladas del caso de uso correspondiente, el Diccionario o el Modelo Relacional; para UC–06 rigen D-01–D-18 y DT-01–DT-12.
- Las vistas no deben contener reglas críticas que sólo existan en frontend.
- Toda regla crítica debe validarse también en backend.
- Evitar JavaScript inline cuando el proyecto ya usa módulos reutilizables y el cambio justifique extraerlos.
- No incorporar una librería frontend únicamente para reproducir un mock si Blade/Alpine/Tailwind pueden resolverlo correctamente.
- Los estados visuales derivados no justifican crear columnas persistentes.

### Formularios con archivos en UC–07

- Usar `multipart/form-data` / `FormData` real.
- Contrato V2: cada adjunto reúne `archivo` y `descripcion` en `adjuntos[n]`; no usar arrays paralelos.
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

- no hacer commits ni stagear por iniciativa propia; el usuario controla staging, commits y checkpoints;
- no limpiar ni normalizar el working tree por iniciativa propia;
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

## Alcance histórico del incremento cerrado UC–07

El incremento cerrado corresponde exclusivamente a:

**UC–07 Solicitar turno V2**

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

## Prototipos y diseño UX de UC–07 — Antecedente cerrado

UX-01…UX-03 y Consolidación UX V2.0 están aprobadas y congeladas. Stitch V2 está aprobado y congelado: 6 capturas desktop + 8 excepciones = 14, sin carpeta mobile canónica; responsive sigue siendo obligatorio. Contrato visual activo: `docs/_codex_sources/turnos/prototipo_stitch_uc07/README.md`.

Fidelidad exigida: funcional 100 %, estructural alta y estética alta, no pixel-perfect. Foto profesional opcional con avatar fallback; no usar `Asignada` como semántica administrativa. Si existe cierre X del éxito, equivale a `Entendido` y conduce a Mis turnos. Navegación desktop en una línea cuando exista ancho suficiente; profesional perdida visible sólo como contexto deshabilitado, no seleccionable.

La Parte II delta V2 7.29–7.52 fue ejecutada y cerrada mediante prompts independientes. El frontend V2 quedó certificado; no existe autorización de nuevos cambios visuales o funcionales por iniciativa propia.

Reglas:

- No convertir una propuesta UX en regla de negocio sin aprobación.
- No modificar persistencia para imitar un prototipo.
- No introducir React u otro framework sólo porque una herramienta de prototipado genere ese código.
- Si posteriormente se utiliza una herramienta de IA para prototipar pantallas, el resultado será referencia visual y deberá adaptarse a Laravel + Blade + Alpine + Tailwind.
- El material histórico existente en `docs/_codex_sources/turnos/prototipo_deepseek/` no es automáticamente el prototipo de UC–07 ni autoriza copiar código o comportamiento.
- Usar exclusivamente las referencias Stitch V2 enumeradas en su README; V1 sólo es antecedente histórico.

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
