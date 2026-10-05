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
- MySQL/XAMPP en desarrollo.
- PHPUnit para pruebas automatizadas.
- Spatie Permission para roles/permisos.

## Incremento activo

Caso de uso actualmente en desarrollo:

**UC–XX Configurar agenda de atención**

Rama esperada:

`feature/turnos-configurar-agenda`

Commit base del incremento:

`021d1d159b5024b0a9de93cee056f1150a46a63a`

Último baseline global certificado antes de iniciar este incremento:

- `php artisan test`: 1109 tests passed, 6783 assertions.
- `npm run build`: PASS.
- Pint acotado al incremento UC-15 anterior: PASS.
- Working tree sin cambios trackeados al crear la rama.
- `docs/archivo/` permanece deliberadamente no trackeado.

Si la rama activa o el estado Git no coinciden con lo esperado, detenerse e informarlo antes de modificar archivos.

No modificar, agregar, eliminar ni limpiar `docs/archivo/` salvo instrucción explícita del usuario.

## Fuente de verdad para UC–XX Configurar agenda de atención

Las fuentes locales congeladas para este incremento están en:

`docs/_codex_sources/turnos/`

Antes de trabajar en este UC, leer primero:

`docs/_codex_sources/turnos/README_Codex_sources.md`

Jerarquía de decisión para este incremento:

1. `Diccionario_de_Datos_vigente.docx` y `Diccionario_de_Datos_vigente.txt`: persistencia, tablas, atributos, claves, índices, relaciones y reglas de dominio estructurales.
2. `UC-XX_Configurar_agenda_atencion.docx` y `UC-XX_Configurar_agenda_atencion.txt`: comportamiento funcional, actores, secciones A–F, validaciones, excepciones y postcondiciones.
3. `Modelo_Relacional_KinesioSoft_vigente.jpg`: representación visual del modelo relacional vigente. Ante una discrepancia accidental con el Diccionario de Datos, prevalece el Diccionario.
4. Decisiones funcionales explícitas del prompt aprobado para el subpaso actual.
5. `Casos_de_Usos_relacionados_con_Modulo_de_Turnos.docx` y `Casos_de_Usos_relacionados_con_Modulo_de_Turnos.txt`: contexto de integración futura; no autorizan ampliar el alcance actual.
6. `prototipo_deepseek/`: referencia exclusivamente visual y de UX/UI.
7. Código real existente de KinesioSoft: convenciones técnicas y patrones Laravel reutilizables.

Las decisiones complementarias congeladas para la implementación están documentadas también en:

`docs/_codex_sources/turnos/README_Codex_sources.md`

No reabrir decisiones de dominio ya congeladas durante la programación.

Si dos fuentes de mayor autoridad parecen contradecirse, detenerse e informar la discrepancia antes de resolverla por cuenta propia.

El prototipo DeepSeek nunca puede utilizarse para modificar el modelo de datos, las relaciones, las reglas de negocio o el alcance funcional.

El código del prototipo DeepSeek no debe copiarse automáticamente al proyecto. Debe analizarse y adaptarse a las convenciones reales de KinesioSoft.

## Forma obligatoria de trabajo

Trabajar únicamente en el subpaso o bloque de subpasos que el usuario solicite de forma explícita.

Antes de modificar:

1. Leer este `AGENTS.md`.
2. Si el trabajo corresponde al incremento activo de Turnos, leer `docs/_codex_sources/turnos/README_Codex_sources.md`.
3. Leer las fuentes y decisiones específicas indicadas por el subpaso solicitado.
4. Inspeccionar primero los archivos reales del proyecto relacionados con ese subpaso.
5. Comparar las fuentes congeladas, el requerimiento del subpaso y la implementación existente.
6. Informar cualquier discrepancia relevante antes de inventar una solución no prevista.

Si posteriormente se crea una planificación ejecutable congelada específica para este incremento, deberá leerse también el subpaso correspondiente antes de modificar.

Después de modificar:

1. Ejecutar las pruebas indicadas en el subpaso.
2. No avanzar si alguna prueba requerida falla.
3. Informar los archivos creados.
4. Informar los archivos modificados.
5. Resumir las decisiones aplicadas.
6. Mostrar los comandos de prueba ejecutados y su resultado.
7. Detenerse al finalizar el subpaso solicitado.

No adelantar trabajo de un subpaso posterior aunque parezca conveniente.

## Arquitectura a respetar

Antes de crear nuevas abstracciones, inspeccionar implementaciones equivalentes existentes.

Como patrón arquitectónico general para nuevos flujos de escritura, reutilizar cuando corresponda:

`Controller -> Form Request -> Action -> DB::transaction() -> Model -> AuditoriaService`

La estructura concreta de cada módulo debe derivarse de sus fuentes y de la implementación real existente.

No asumir namespaces, carpetas o abstracciones nuevas antes de diseñarlos y justificarlos.

Convenciones generales:

- Controllers tradicionales.
- Form Requests para validación y autorización de entrada cuando corresponda.
- Actions para lógica de aplicación y operaciones de negocio.
- Policies para autorización sobre recursos y operaciones.
- Models Eloquent propios por entidad.
- Reutilizar `AuditoriaService`.
- Utilizar `DB::transaction()` para escrituras que deban ser atómicas.
- Utilizar `lockForUpdate()` cuando concurrencia, transiciones de estado o reglas temporales puedan comprometer consistencia.
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
- Abstracciones que mezclen reglas de negocio diferentes sólo porque las pantallas se parecen.

## Reglas congeladas del incremento Turnos

Estas reglas resumen decisiones críticas del incremento activo.

La fuente completa sigue siendo `docs/_codex_sources/turnos/README_Codex_sources.md`, el Diccionario de Datos y el UC congelado.

### Configuraciones de agenda

- Una kinesióloga puede tener múltiples configuraciones de agenda a lo largo del tiempo.
- La vigencia se representa mediante `vigencia_desde` y `vigencia_hasta`.
- `vigencia_hasta` puede ser `NULL`.
- Las configuraciones históricas deben conservarse.
- Las configuraciones no anuladas de una misma kinesióloga no pueden superponer vigencias.
- No existe UK que limite a una configuración por kinesióloga.
- No existe UK `(kinesiologa_id, vigencia_desde)`.
- Una configuración futura puede editarse mientras todavía no haya comenzado.
- Una configuración con `vigencia_desde <= hoy` se considera iniciada.
- Una configuración futura todavía no iniciada puede anularse lógicamente.
- Una configuración iniciada no puede anularse como configuración futura.
- La anulación lógica utiliza `anulado_at` y `motivo_anulacion`.
- Una configuración anulada permanece almacenada para trazabilidad.
- Las configuraciones anuladas no participan en disponibilidad ni en solapamientos efectivos.
- No persistir un atributo `estado` para representar vigente, futura, histórica o anulada; esos estados se derivan.

### Franjas horarias

- Las franjas pertenecen a una configuración de agenda.
- Una configuración válida debe poseer al menos una franja.
- Puede haber múltiples franjas por día.
- No existe un máximo funcional de dos franjas por día.
- `hora_fin` debe ser posterior a `hora_inicio`.
- No pueden existir franjas superpuestas dentro del mismo día y configuración.
- Se admiten franjas contiguas.
- Cada franja debe tener duración igual o superior a `duracion_turno_minutos`.
- La existencia de franjas expresa si un día posee atención; no persistir un booleano adicional de día habilitado.

### Duración estándar

- `duracion_turno_minutos` pertenece a cada configuración.
- Debe ser entero.
- Mínimo: 15 minutos.
- Máximo: 180 minutos.
- Debe ser múltiplo de 5.
- 45 minutos puede utilizarse como sugerencia inicial, no como constante universal.
- Un cambio de duración con vigencia futura no modifica retrospectivamente configuraciones históricas.
- Si franjas que se conservarían son menores que la nueva duración, la operación debe impedirse hasta disponer de una matriz compatible.

### Bloqueos

- Los bloqueos pertenecen directamente a la kinesióloga mediante `kinesiologa_id`.
- Los bloqueos no pertenecen a `configuraciones_agenda`.
- Un bloqueo puede atravesar períodos pertenecientes a diferentes configuraciones.
- Dos o más bloqueos de una misma kinesióloga pueden superponerse total o parcialmente.
- Los bloqueos superpuestos se conservan como registros independientes.
- Un intervalo permanece bloqueado mientras exista al menos un bloqueo no anulado que lo afecte.
- Los bloqueos se anulan lógicamente mediante `anulado_at` y `motivo_anulacion`.
- No eliminar físicamente bloqueos como flujo ordinario.
- No editar un bloqueo persistido para corregir fechas o motivo.
- Una corrección se realiza anulando el original y creando otro cuando corresponda.
- No persistir un atributo `estado` para el bloqueo.
- No persistir `tipo` ni `todo_el_dia` únicamente por aparecer en el prototipo.
- La opción visual "Todo el día" es un modo de captura.
- Los intervalos se interpretan conceptualmente como semiabiertos `[inicio_at, fin_at)`.
- Un bloqueo parcial de varios días representa un único intervalo temporal continuo; no una repetición diaria automática.
- Presets como Feriado, Vacaciones, Licencia o Congreso pueden existir sólo como ayuda UX para completar `motivo`.
- Las fechas y horas deben respetar la configuración temporal real del proyecto; no hardcodear una zona horaria.

### Turnos afectados

- Los cambios de agenda, duración o bloqueos no modifican automáticamente turnos ya otorgados.
- `turnos` conserva sus propios `inicio_at` y `fin_at`.
- No agregar una FK desde `turnos` hacia `configuraciones_agenda` sólo para recordar qué configuración originó la disponibilidad.
- Un bloqueo puede registrarse aunque afecte turnos existentes.
- Antes de confirmar el bloqueo debe realizarse una comprobación real.
- La interfaz debe informar como mínimo la existencia de turnos afectados, su cantidad y sus fechas/horarios.
- La reprogramación o cancelación de esos turnos pertenece a otros casos de uso.

### Disponibilidad

- No persistir una tabla de slots, cupos o disponibilidades sólo para materializar la agenda futura.
- La disponibilidad debe derivarse de la configuración aplicable a la fecha objetivo, sus franjas, los bloqueos no anulados, los turnos existentes y otras restricciones cuando correspondan.
- La configuración aplicable se resuelve respecto de la fecha objetivo consultada, no simplemente usando la configuración vigente hoy.

### Actor del UC

- El actor funcional de UC–XX Configurar agenda de atención es la kinesióloga.
- No habilitar gestión de agendas para ADMINISTRADOR o SUPERADMIN sin una decisión funcional explícita posterior.

### Históricos y paginación

- Las colecciones históricas que puedan crecer indefinidamente deben diseñarse con paginación.
- Como convención inicial puede utilizarse `paginate(10)` cuando corresponda.
- La pantalla principal puede mostrar resúmenes y ofrecer acceso a listados completos paginados.

## Reglas específicas de módulos ya implementados

Las secciones siguientes que describen Catálogos Maestros y Catálogos Clínicos documentan reglas específicas de esos módulos ya implementados.

Se conservan como referencia del comportamiento existente, pero NO deben trasladarse automáticamente al incremento de Turnos.

En particular, conceptos como:

- `activo`;
- `inactivo`;
- inactivar;
- reactivar;
- `app/Actions/Catalogos/`;
- restricciones específicas de categorías, ejercicios o recursos;

no forman parte de UC–XX Configurar agenda de atención salvo que una fuente superior del incremento lo establezca expresamente.

## Reglas de estado de catálogos

- Un registro nuevo nace Activo.
- `create/store` no permite elegir estado.
- `edit/update` no cambia el estado lógico.
- Activo: Consultar / Editar / Inactivar.
- Inactivo: Consultar / Editar / Reactivar.
- Inactivar y Reactivar son operaciones explícitas.
- El cambio de estado debe utilizar estado objetivo; no invertir ciegamente con `!$activo`.
- Repetir una operación ya satisfecha debe ser idempotente y no generar auditoría ficticia.
- No existe `destroy` como operación ordinaria de Catálogos Maestros.
- No realizar eliminación física de catálogos para implementar la baja lógica.
- Las referencias históricas deben preservarse.

No aplicar estas reglas mediante reemplazos globales a módulos ajenos al UC.

En particular, no modificar por este motivo:

- `UserController::toggleActivo`.
- `UserPolicy::toggleActivo`.
- El cambio de estado de Matrículas Profesionales.

## Reglas congeladas de los catálogos clínicos

### Diagnósticos clínicos

Nombre técnico:

- Tabla: `diagnosticos_clinicos`.
- Model: `DiagnosticoClinico`.

No utilizar `diagnosticos_funcionales` para el nuevo catálogo.

Dominio de `sistema_origen`:

- `CIE-10`
- `CIF`
- `SNOMED`
- `INTERNO`

UK: `(sistema_origen, codigo)`.

### Pruebas funcionales

Dominio de `sistema_origen`:

- `INTERNO`
- `SNOMED`
- `LOINC`

`unidad_medida` es opcional.

UK: `(sistema_origen, codigo)`.

### Técnicas terapéuticas

Dominio de `sistema_origen`:

- `INTERNO`
- `SNOMED`

Los parámetros concretos de dosificación/aplicación no pertenecen al catálogo.

UK: `(sistema_origen, codigo)`.

### Categorías clínicas

Tipos:

- `ANATOMICA`
- `FUNCIONAL`
- `TERAPEUTICA`
- `EQUIPAMIENTO`

Reglas:

- Una hija debe tener el mismo tipo que su padre.
- No se permite autorreferencia.
- No se permiten ciclos.
- Una categoría activa no puede depender de un padre inactivo.
- La inactivación es restrictiva si deja dependencias maestras activas inválidas.

### Ejercicios domiciliarios

No existe `grupo_muscular_zona`.

Reglas:

- `codigo` es obligatorio y único.
- `dificultad_base`: `BAJA`, `MEDIA`, `ALTA`.
- `frecuencia_periodo_base`: `DIARIA` o `SEMANAL` cuando se informa frecuencia.
- `frecuencia_cantidad_base` y `frecuencia_periodo_base` se informan conjuntamente.
- Un ejercicio activo requiere al menos una categoría activa `ANATOMICA`.
- Puede asociarse a `ANATOMICA`, `FUNCIONAL` o `TERAPEUTICA`.
- Nunca puede asociarse a `EQUIPAMIENTO`.

### Recursos de ejercicio

Campos persistentes relevantes:

- `ejercicio_id`
- `tipo`
- `origen`
- `ruta_storage`
- `url_externa`
- `titulo`
- `orden`
- `activo`

Dominios:

- `tipo`: `IMAGEN`, `VIDEO`, `DOCUMENTO`.
- `origen`: `LOCAL`, `EXTERNO`.

Reglas:

- LOCAL: `ruta_storage` obligatoria y `url_externa = NULL`.
- EXTERNO: `url_externa` obligatoria y `ruta_storage = NULL`.
- `ruta_storage` nunca se acepta como valor controlado por el cliente; la genera el backend después de almacenar un archivo.
- Un recurso persistido se identifica por `id`.
- Omitir un recurso persistido del request no significa borrarlo.
- Recurso persistido: inactivar/reactivar; no eliminar físicamente como flujo normal.
- Recurso nuevo no persistido: puede quitarse del formulario.
- Si se almacena un archivo nuevo y luego falla la transacción DB, limpiar el archivo nuevo.
- Si se reemplaza un archivo existente, no borrar el viejo antes de confirmar la transacción.

### Tipos de equipo terapéutico

`categoria_clinica_id` debe apuntar a una categoría activa de tipo `EQUIPAMIENTO` para crear, cambiar asociación o reactivar.

## Auditoría

Reutilizar `app/Services/AuditoriaService.php`.

Como regla general, auditar operaciones de escritura relevantes conforme al módulo y al caso de uso.

Para módulos con cambio de estado explícito ya implementados, mantener los contratos existentes.

No auditar operaciones puramente de lectura salvo que una fuente funcional específica lo exija.

No generar auditoría ficticia para:

- updates sin cambios semánticos reales;
- operaciones idempotentes sin cambio real.

Para los eventos existentes de Catálogos, el módulo de auditoría utilizado es:

`Catálogos`

No asumir que el incremento Turnos deba utilizar ese mismo nombre de módulo. El nombre/eventos concretos de auditoría de Turnos deben definirse durante su diseño técnico conforme a las fuentes y a la infraestructura existente.

Nunca almacenar contraseñas, binarios u otros datos sensibles en snapshots de auditoría.

## Base de datos y migraciones

- Agregar migraciones nuevas; no reescribir migraciones históricas ya ejecutadas.
- Preservar claves foráneas y conservación histórica según la planificación y las fuentes vigentes.
- No usar cascadas destructivas incompatibles con la conservación histórica.
- No usar native `ENUM` de MySQL para dominios que deban mantenerse compatibles con SQLite de PHPUnit.
- Mantener compatibilidad entre MySQL de desarrollo y SQLite utilizado por las pruebas cuando corresponda.
- No ejecutar comandos destructivos sobre la BD de desarrollo.

PROHIBIDO sin instrucción explícita del usuario:

- `php artisan migrate:fresh`
- `php artisan migrate:reset`
- `php artisan db:wipe`
- equivalentes destructivos

Si una tarea requiere ejecutar una migración real sobre MySQL de desarrollo, detenerse y pedir confirmación antes de ejecutarla.

`migrate --pretend` puede utilizarse cuando el subpaso o planificación lo indiquen.

## Archivos y configuración protegidos

No modificar salvo instrucción explícita:

- `.env`
- `vendor/`
- `node_modules/`
- dumps SQL
- documentos UP congelados
- Modelo Relacional congelado
- Diccionario de Datos congelado
- fuentes congeladas dentro de `docs/_codex_sources/`

No incluir en Git:

- cachés;
- logs;
- archivos temporales;
- uploads de pruebas;
- credenciales;
- `.env`;
- `docs/_codex_sources/`, que contiene fuentes locales de apoyo para Codex y está excluido mediante `.git/info/exclude`.

No modificar `.gitignore` o `.git/info/exclude` para incorporar `docs/_codex_sources/` al repositorio salvo instrucción explícita.

## Pruebas

El proyecto usa PHPUnit, NO Pest.

Estrategia:

1. Ejecutar primero tests específicos del cambio.
2. Corregir fallos causados por el subpaso.
3. Ejecutar la regresión indicada por la planificación o por el prompt aprobado.
4. No avanzar con tests rojos.
5. Ejecutar `php artisan test` completo en las puertas de regresión definidas para el incremento.

Último baseline global certificado antes del incremento activo:

`1109 passed (6783 assertions)`.

Este baseline es una referencia histórica del punto de partida.

Las puertas de regresión posteriores deben informar siempre sus resultados reales y no asumir que el conteo permanecerá constante.

No modificar tests para “hacerlos pasar” si eso debilita o cambia una regla congelada.

Cuando un test deba actualizarse por un cambio funcional planificado, mantener o aumentar la cobertura semántica.

## Frontend

- Blade + Tailwind + Alpine + Vite.
- SweetAlert2 ya está disponible globalmente.
- No agregar imports/CDN duplicados.
- Mantener desktop y mobile conforme a las fuentes funcionales del incremento y a los patrones visuales actuales de KinesioSoft.
- Los prototipos visuales son referencias UX/UI y no prevalecen sobre el Diccionario de Datos, el UC, el Modelo Relacional ni las decisiones explícitas del subpaso.
- Las vistas no deben contener reglas de dominio que sólo existan en frontend.
- Toda regla crítica debe validarse también en backend.
- Evitar JavaScript inline cuando el proyecto ya usa módulos reutilizables.
- No incorporar una librería frontend únicamente para reproducir un comportamiento del prototipo si el stack existente puede resolverlo correctamente.
- Los estados visuales derivados no justifican crear columnas persistentes.

Para formularios con archivos:

- usar `multipart/form-data`;
- enviar `UploadedFile` real;
- no serializar archivos como strings/Base64 para suplir el transporte HTTP;
- recordar que un input file no puede repoblarse con `old()` tras una validación fallida.

Estas reglas de archivos se conservan para los módulos que los utilicen; no implican que UC–XX Configurar agenda de atención requiera carga de archivos.

## Git

Antes de un bloque:

- comprobar rama cuando sea relevante;
- comprobar `git status`.

Durante el trabajo:

- no hacer commits intermedios salvo instrucción explícita;
- no cambiar de rama por iniciativa propia;
- no hacer push;
- no reescribir historial;
- no usar `reset --hard`, `clean -fd` u otros comandos destructivos;
- no agregar `docs/archivo/` al staging salvo instrucción explícita;
- no agregar `docs/_codex_sources/` al repositorio.

Antes de cierre:

- mostrar `git status --short`;
- mostrar `git diff --stat`;
- revisar el diff requerido por el subpaso;
- utilizar `git diff --check` cuando corresponda.

No realizar commits por iniciativa propia.

El momento y alcance de cada commit serán indicados explícitamente por el usuario para el incremento activo.

No hacer push salvo autorización explícita.

## Seguridad operacional

Si una acción:

- excede el subpaso solicitado;
- puede destruir datos;
- requiere credenciales;
- modifica configuración de entorno;
- cambia arquitectura congelada;
- afecta módulos no incluidos;
- o contradice una fuente congelada;

detenerse y solicitar autorización antes de continuar.

No interpretar una aprobación para ejecutar un comando como autorización general para otros comandos posteriores.

No ejecutar comandos destructivos o de limpieza para resolver un estado Git inesperado.

No eliminar archivos no trackeados cuya procedencia no haya sido autorizada.

## Alcance del incremento Turnos

El alcance inicial corresponde exclusivamente a:

**UC–XX Configurar agenda de atención**

La presencia de otros casos de uso de Turnos dentro de las fuentes documentales sirve para comprender integraciones futuras.

No implementar por anticipado:

- UC–07 Solicitar turno;
- programación de turnos de seguimiento;
- reprogramación o cancelación de turnos;
- ofertas de adelanto;
- aceptación o rechazo de ofertas;
- reservas de equipos;
- otras funcionalidades de Turnos no incluidas en el subpaso aprobado.

Puede diseñarse una responsabilidad reutilizable cuando sea necesaria para el UC actual y esté respaldada por las fuentes, pero no implementar flujos futuros por anticipado.

## Prototipo DeepSeek

La carpeta:

`docs/_codex_sources/turnos/prototipo_deepseek/`

contiene material de referencia visual.

Puede utilizarse para analizar:

- jerarquía visual;
- distribución de información;
- cards;
- tablas;
- formularios;
- modales;
- filtros;
- responsive;
- interacción general.

No puede utilizarse como fuente para:

- crear columnas;
- crear tablas;
- cambiar cardinalidades;
- persistir estados derivados;
- introducir relaciones nuevas;
- modificar reglas de negocio;
- ampliar el alcance;
- introducir frameworks o librerías externas;
- copiar directamente HTML, CSS o JavaScript del mock.

Cuando el prototipo y una fuente superior difieran, debe adaptarse el prototipo a KinesioSoft.

No debe adaptarse el dominio para imitar al prototipo.

## Formato de cierre de cada subpaso

Al terminar, responder con:

1. **Subpaso ejecutado**
2. **Archivos inspeccionados**
3. **Archivos creados**
4. **Archivos modificados**
5. **Cambios realizados**
6. **Pruebas/comandos ejecutados**
7. **Resultado exacto**
8. **Discrepancias o riesgos encontrados**
9. **Estado de Git**
10. **Confirmación explícita de que no se avanzó al subpaso siguiente**

Detenerse después de ese informe.
