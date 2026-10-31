# UC–06 — Subpaso 6.15: certificación y cierre de UX final

**6.15: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.**

## Referencia y alcance aprobado

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD de referencia previo al cierre: `aae4c2fd7f33a80469aa561cc3c14091c1c27e23`, regularización documental de 6.14.
- 6.14 permanece cerrado en `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`; 6.13 permanece cerrado en `616085b57b425e873ff0e957b0d558cb26add136`.

Alcance literal de **6.15**, según `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md`, Fase D:

> Completar mensajes, estados vacíos, responsive, navegación por teclado, accesibilidad y preservación del contexto.

Se respetan los contratos UX-01–UX-05 y las decisiones funcionales y técnicas congeladas. Se completa la experiencia de Próximos, Historial y detalle reutilizando las operaciones existentes, sin introducir nuevas reglas de dominio, migraciones, Actions ni infraestructura de concurrencia.

## Implementación certificada

| Archivo | Mejora |
| --- | --- |
| `resources/css/app.css` | Tipografía del sistema limitada a `.uc06-mis-turnos`, compatible con el entorno aislado. |
| `resources/views/layouts/app.blade.php` | Omite las fuentes remotas sólo en Mis turnos; incorpora el enlace «Saltar al contenido de Mis turnos» y su destino enfocable, sin alterar navegación o formularios de otros módulos. |
| `resources/views/turnos/mis-turnos/_filtros.blade.php` | Vincula los mensajes de validación con los campos correspondientes y mantiene foco visible. |
| `resources/views/turnos/mis-turnos/_listado.blade.php` | Encabezados de sección enfocables y recuperación de páginas vacías mediante el enlace a la primera página, conservando los filtros. |
| `resources/views/turnos/mis-turnos/index.blade.php` | Diferencia ausencia de turnos de búsquedas sin coincidencias y ofrece una salida explícita para mostrar todos los turnos. |
| `resources/views/turnos/mis-turnos/show.blade.php` | Resultado anunciado y enfocable; recuperación del listado o detalle con el contexto permitido y etiquetas coherentes. |
| `resources/js/turnos/mis-turnos.js` | Separa progreso del transporte y diálogo de resultado; distingue rechazos conocidos de incertidumbre, conserva resultados conocidos ante fallas visuales y recupera el foco. |
| `tests/JavaScript/mis-turnos.test.mjs` | Amplía la cobertura de progreso, mensajes, foco, contexto y fallas de diálogo, preservando las comprobaciones existentes. |
| `tests/Feature/Turnos/MisTurnosUxTest.php` | Nuevo test de tipografía y salto al contenido acotados, estados vacíos, errores de filtros y recuperación del contexto de navegación. |

### CSP, progreso y mensajes

- **CSP:** Mis turnos utiliza tipografía local y deja de solicitar `fonts.bunny.net`. No se amplía la política CSP ni se habilitan comunicaciones externas para resolver el aviso. No se declara eliminada la dependencia tipográfica de otros módulos.
- **Progreso:** `enviando` termina al concluir el transporte, antes del diálogo de éxito, replay o rechazo. El bloqueo de operaciones simultáneas se mantiene durante el diálogo; no se habilita doble envío al ocultar el indicador.
- **Mensajes:** 401, 403, 404 y 419 tienen mensajes claros incluso cuando el middleware devuelve HTML. 409 y 422 conservan su carácter de rechazo conocido sin depender de un JSON válido. Los errores técnicos o de transporte después de enviar mantienen la advertencia de resultado incierto y la consulta mediante GET, sin reintentos automáticos. Una falla visual previa al POST comunica que no se envió la operación; una falla del diálogo final no convierte un resultado conocido en incierto.
- **Contexto y accesibilidad:** se mantienen filtros, búsqueda y paginaciones independientes al volver al listado; los mensajes, destinos de navegación y encabezados tienen semántica y foco adecuados. Se conservan la estructura responsive, los controles existentes y sus estados accesibles.

No cambian las autorizaciones por paciente, CSRF, referencia cifrada de programación, límites temporales, transacciones, locking, idempotencia, auditoría ni privacidad documental certificados. El código productivo no depende de los auxiliares externos de validación.

## Certificaciones aprobadas

Los resultados siguientes corresponden a la implementación y validación ya finalizadas. La validación manual y su auditoría se registran conforme al resultado satisfactorio informado por el usuario. No se repitieron pruebas, builds ni migraciones durante esta preparación documental.

| Verificación | Resultado |
| --- | --- |
| PHP dirigido de 6.15 | **290 tests / 2402 assertions PASS**. |
| JavaScript de Mis turnos | **41 pruebas PASS**. |
| JavaScript existente | **155 pruebas PASS**, correspondientes al wizard y bloqueo de formulario. |
| Lint PHP/JavaScript | **PASS**. |
| Pint acotado, modo `--test` | **PASS**. |
| Build | **PASS, 95 módulos**. |
| `git diff --check` | **PASS**. |
| Validación manual | **Cuatro comprobaciones UX satisfactorias**. |
| Auditoría final del entorno manual | **PASS**. |

PHP se verificó con `APP_ENV=testing`, SQLite `:memory:` y configuración sin caché de desarrollo. Estos resultados son dirigidos; no sustituyen la regresión integral de 6.17 ni se suman a los baselines históricos como una única ejecución. La certificación MariaDB de 6.13 conserva su evidencia independiente y no se repitió.

## Entorno manual y límites de la evidencia

La revisión manual utilizó el procedimiento externo existente, con una sesión nueva de **SQLite temporal aislado, 48 migraciones y 44 tablas**. La sesión mantuvo una clave temporal estable, storage, sesiones, caché y logs propios, datos sintéticos, comunicaciones externas bloqueadas y servidor local limitado a `127.0.0.1`. Se preservaron autenticación, autorización, CSRF y validaciones originales. La auditoría final fue **PASS**.

La adaptación del auxiliar se limitó a actualizar las referencias de preflight al HEAD e inventario de 6.15 y la cobertura de hashes del nuevo test. No forma parte del commit del proyecto. No se incorporan al repositorio claves, contraseñas, cookies, datos personales o clínicos, sesiones SQLite ni archivos temporales de evidencia. No se autoriza limpiar sesiones anteriores.

La revisión visual de cuatro comprobaciones no constituye una auditoría exhaustiva de accesibilidad, dispositivos o navegadores. Los bordes temporales exactos, propiedad, CSRF, idempotencia y concurrencia conservan su cobertura automatizada previa; no se atribuye a esta revisión una reproducción manual exhaustiva ni locking real de MariaDB.

## Dependencias y transición

- **C-02 / DT-03 PENDIENTE:** integración clínica de planes y sesiones reales de Seguimiento; no queda resuelta por mejoras de presentación.
- **Integraciones externas PENDIENTES:** evaluación efectiva de cupos, candidatos, ofertas, enlaces seguros y comunicaciones, según la infraestructura realmente disponible. Una intención durable no implementa esos procesos.
- **6.16:** siguiente subpaso **PENDIENTE, no iniciado**, sujeto a autorización independiente para revisar contratos de integración.
- **6.17–6.18:** pendientes; regresión integral y cierre global de UC–06 conservan su alcance propio.

El cierre de 6.15 queda registrado mediante el mismo commit que incorpora esta certificación, sin anticipar su hash. No autoriza por sí solo staging, commit, push, operaciones sobre datos ni la ejecución de subpasos posteriores.
