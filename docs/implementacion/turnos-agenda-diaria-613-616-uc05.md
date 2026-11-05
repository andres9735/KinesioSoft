# Agenda Diaria Automática — Cierre del bloque UC–05 (6.13–6.16)

**Estado: bloque implementado y certificado en las condiciones locales ensayadas, con dependencias y reservas explícitas. No constituye certificación integral de producción ni cumplimiento sin reservas de los 32 criterios de UC–05.**

- Registro documental: **05/11/2026**, referencia local del proyecto.
- Rama: `feature/turnos-agenda-diaria-automatica`.
- HEAD de revisión previo al checkpoint: `ae9abaf0d763c23dd7ff444835e26e3a2f037bc4`.
- El commit que incorpora este documento registra el cierre del bloque; no se anticipa su hash.

## Alcance de los subpasos

| Subpaso | Implementación y verificaciones |
| --- | --- |
| 6.13 | Detalle administrativo por turno, autorizado para la profesional, con rechazo uniforme de recursos ajenos/inexistentes, ambos tipos, seis estados e intervalos reales. Regreso con contexto temporal, filtros y página; sin documentos ni acciones modificatorias. |
| 6.14 | Impresión y PDF auténtico mediante Dompdf instalado, con resultados completos del período filtrado, fecha/hora local de emisión, contenido administrativo, descarga privada y recuperación de errores. |
| 6.15 | Entrada desde el menú profesional, salto al contenido, foco y contraste del menú móvil, estructura semántica, referencias accesibles, actualización con contexto, mensajes recuperables de carga y lectura adaptable de la impresión. Verificación automatizada, sin certificación visual manual. |
| 6.16 | Regresión conjunta funcional, seguridad, compatibilidad y ensayos sintéticos de volumen. Ajuste acotado del renderizado PDF en tablas de hasta 25 registros por bloque, conservando todos los resultados y su orden. |

Fuentes de referencia: especificación extendida UC–05 y compendio local V1.0 congelado; contratos AT compartidos, GB-01 a GB-03 y GB-07 a GB-12, UXP-05 a UXP-08 y criterios CA-05-01 a CA-05-32. Los contratos y evidencias anteriores conservan su alcance histórico.

## Archivos del bloque

| Estado respecto del checkpoint 6.10–6.12 | Archivo |
| --- | --- |
| Modificado | `routes/web.php` |
| Modificado | `app/Http/Controllers/AgendaAtencionController.php` |
| Modificado | `app/Services/Agenda/AgendaAtencionQueryService.php` |
| Modificado | `app/Services/Agenda/AgendaProfesionalQueryService.php` |
| Modificado | `resources/views/layouts/app.blade.php` |
| Modificado | `resources/views/layouts/navigation.blade.php` |
| Modificado | `resources/views/turnos/agenda-atencion/index.blade.php` |
| Modificado | `tests/Feature/Turnos/AgendaAtencionHttpTest.php` |
| Nuevo | `app/Actions/Agenda/ExportarAgendaAtencionPdfAction.php` |
| Nuevo | `resources/views/turnos/agenda-atencion/show.blade.php` |
| Nuevo | `resources/views/turnos/agenda-atencion/exportacion.blade.php` |
| Nuevo | `resources/views/turnos/agenda-atencion/error-exportacion.blade.php` |

El checkpoint incorpora también este certificado y la actualización exclusiva del seguimiento activo de `AGENTS.md`: **14 archivos, nueve modificados y cinco nuevos**. No agrega cambios funcionales durante el cierre documental.

## Seguridad, privacidad y compatibilidad

Las rutas mantienen autenticación, rol profesional, correo verificado y perfil completo, junto con el control transversal de cuenta activa. La profesional se deriva de su relación persistida; `AgendaProfesionalQueryService` revalida identidad, rol y pertenencia antes de entregar información de pacientes. El detalle consulta id y profesional conjuntamente; no utiliza un binding global de Turno para revelar recursos ajenos.

La ampliación de los lectores conserva las firmas y semántica previamente certificadas. El lector interno permanece disponible para UC–29 sin sesión HTTP. Detalle y período comparten representación administrativa mínima y carga conjunta de relaciones, sin serializar modelos completos. No se amplían Policies del paciente ni permisos clínicos. Los rechazos, parámetros manipulados y roles revocados están cubiertos por pruebas.

La impresión y el PDF comparten la consulta autorizada, fechas y filtros validados; exportan todos los resultados coincidentes, independientemente de la página visible. Se muestran únicamente paciente, profesional, tipo, estado, inicio, fin y duración real. No se agregan contactos, motivos, documentos, equipos inferidos ni datos clínicos. Blade escapa valores. Dompdf usa exclusivamente protocolo local autorizado, con acceso remoto, PHP y JavaScript deshabilitados; el PDF se entrega como bytes, sin archivo público persistido.

Los errores de carga y generación se diferencian de los vacíos, ofrecen reintento y no muestran SQL ni excepciones internas. Detalle y exportaciones usan respuestas privadas sin almacenamiento en caché. Las operaciones de consulta no alteran turnos, estados, reservas, configuraciones ni auditorías; se verifican snapshots de la base sintética. El comportamiento transversal de expulsión de cuentas inactivas se conserva como seguridad histórica, separado de escrituras funcionales de agenda.

Se preservan los contratos de UC–06, UC–07 y Configuración de Agenda: no se modifican sus Actions, Policies, ocupación, reservas ni reglas temporales. La lectura conserva programación local, seis estados, ambos tipos y orden estable por inicio e id, independientemente de UC–29.

## Regresión definitiva reutilizada

**181 tests / 1785 assertions PASS, exit code 0**, sin fallos ni errores. PHP **8.2.12**, PHPUnit **11.5.55**; duración **00:43.541**, memoria informada por PHPUnit **188 MB**. Este resultado corresponde al código definitivo de 6.13–6.16, incluido el fixture corregido y el ajuste de tablas PDF; no se suman ejecuciones anteriores.

La ejecución conjunta comprendió:

- `tests/Feature/Turnos/AgendaAtencionHttpTest.php`.
- `tests/Feature/Services/Agenda/AgendaAtencionQueryServiceTest.php`.
- `tests/Feature/Services/Agenda/AgendaProfesionalQueryServiceTest.php`.
- `tests/Feature/Routes/AgendaRoutesTest.php`.
- `tests/Feature/Turnos/TurnosRoutesTest.php`.
- `tests/Feature/NavigationTest.php`.

Se utilizó PHPUnit directamente con `phpunit.xml`, sin caché de resultados y con `--fail-on-risky --fail-on-warning`. El bootstrap efectivo comprobó `testing`, configuración sin caché, SQLite `:memory:` con apertura de PDO, driver exclusivamente SQLite y ausencia de PDO MySQL. Correo/caché/sesiones `array`, cola `sync`, storage/discos/vistas/cachés exclusivos y logging/deprecaciones con handlers efectivos `Monolog\Handler\NullHandler`. Entorno sólo del proceso y clave efímera, sin persistir secretos.

Sintaxis PHP y Pint `--test` de los seis PHP afectados: **PASS**. Las vistas se compilaron durante los casos HTTP. Diff y whitespace: **PASS**. La revisión documental actual conserva el código probado y no repite PHPUnit, Pint, build ni migraciones; valida el diff y el staging final.

Incidencias resueltas: fixture de estado protegido contra asignación masiva, corregido con precondición explícita; inspección de la instancia real de Dompdf en la prueba de opciones; costo de tabla PDF extensa, mitigado mediante bloques sin pérdida de registros. La evidencia definitiva reemplaza los resultados intermedios con fallos, sin presentarlos como certificaciones satisfactorias ni acumulables.

## Rendimiento y volumen: resultados y condiciones

Ensayos HTTP dentro de PHPUnit/CLI sobre Windows, SQLite en memoria y datos exclusivamente sintéticos: **30 pacientes dentro del ámbito profesional y 30 turnos por jornada**, con conjuntos de 30, 150 y 600 turnos autorizados, equivalentes a 1, 5 y 20 jornadas. El fixture contiene además un turno ajeno de control, excluido de los resultados.

La creación de fixtures queda fuera del cronometraje. Se prepara la jornada antes de medir para disponer de Blade compilado, registrando esa preparación por separado. Se mide una solicitud por operación y escenario con `hrtime`, log de consultas activo y reset del pico de heap PHP; no son medianas, pruebas de navegador, mediciones RSS del sistema ni ensayos MariaDB. `withoutVite()` excluye la descarga de assets del recorrido probado.

| Turnos autorizados | Preparación | Jornada de 30 turnos | Período completo | Filtros del período | Detalle | Impresión | PDF |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| 30 | 0,0847 s | 0,0853 s | 0,0990 s | 0,0638 s | 0,0286 s | 0,0237 s | 0,6530 s |
| 150 | 0,0689 s | 0,0477 s | 0,1029 s | 0,0791 s | 0,0201 s | 0,1074 s | 2,1667 s |
| 600 | 0,0502 s | 0,0586 s | 0,4513 s | 0,3043 s | 0,0241 s | 0,2928 s | 8,7988 s |

| Turnos autorizados | Pico heap PHP durante PDF | Incremento respecto del heap previo a esa solicitud |
| ---: | ---: | ---: |
| 30 | 82,87 MiB | 18,88 MiB |
| 150 | 93,24 MiB | 29,04 MiB |
| 600 | 140,71 MiB | 76,01 MiB |

Las solicitudes medidas efectuaron **11 consultas SQL**, con una sola consulta de turnos y una de pacientes por recorrido; no se detectó N+1 en estos ensayos. Se comprobaron totales, orden estable, separación de páginas sin duplicados y preservación de registros al imprimir/exportar, sin escrituras funcionales.

Antes del ajuste, una medición de 600 turnos registró PDF en **19,1796 s**, con pico de heap **216,66 MiB** e incremento **151,96 MiB**. El resultado definitivo de la tabla corresponde al renderizado por bloques. **La paginación y la exportación siguen en memoria**: dividir tablas reduce el costo del renderizado, pero no implementa lectura SQL paginada ni streaming y no garantiza comportamiento para volúmenes arbitrarios. No se impusieron límites artificiales al historial o al conjunto exportable.

En una ejecución exploratoria aislada se observó una primera carga de **4,9916 s**, superior a dos segundos, sin la preparación explícita del ensayo definitivo. Se registra como reserva: **la carga fría no queda certificada**. Los valores del ensayo preparado no sustituyen esa observación ni acreditan producción.

## Criterios y reservas de cierre

| Criterios | Alcance de evidencia y estado |
| --- | --- |
| CA-05-01–08, 10–22, 25–29 | Verificados dentro de la implementación y de los recorridos automatizados locales: autorización, agenda, navegación, filtros, detalle, errores, privacidad, representaciones y lectura sin cambios funcionales. No certifica exhaustivamente navegadores o infraestructura productiva. |
| CA-05-09 | **Dependencia no resuelta:** no existe vinculación real de equipos disponible en Turno para este recorrido. No se inventan equipos ni reservas y no se declara cumplido el criterio. |
| CA-05-23 | **Dependencia no resuelta:** no hay destino implementado y autorizado de UC–08 para ofrecer desde esta agenda. No se inicia atención ni se declara cumplido el criterio. |
| CA-05-24 | **Dependencia no resuelta:** operaciones profesionales de reprogramación/cancelación no disponibles como destinos de este bloque. No se sustituyen por autogestión del paciente ni se declara cumplido el criterio. |
| CA-05-30–32 | Las cotas aprobadas de 2 s para jornada habitual, 1 s para filtros habituales y 10 s para impresión/descarga de jornada habitual se contrastaron satisfactoriamente en el escenario preparado de 30 turnos. Los escenarios mayores caracterizan volumen; no redefinen esas cotas ni certifican carga fría, navegador, MariaDB o producción. |

**Pendientes explícitos:** revisión visual manual y de dispositivos/navegadores; validación sobre MariaDB y producción, carga fría y volúmenes superiores representativos del uso real; dependencias CA-05-09/23/24. La suite PHP global, JavaScript y Vite no se ejecutaron durante 6.13–6.16 ni en este checkpoint. El baseline de 6.05 permanece histórico y no se recertifica. SQLite no certifica locking real ni concurrencia MariaDB.

Se conservan las fuentes congeladas, exclusiones, certificados anteriores y temporales históricos. `AGENTS.md` sólo cambia en el seguimiento activo; `docs/archivo/` permanece sin inspección ni modificación.

**Dictamen:** cierre documental del bloque UC–05 6.13–6.16, implementado y certificado bajo las condiciones locales ensayadas, con las dependencias y reservas anteriores. No se declara cumplimiento integral sin reservas ni se autoriza avanzar automáticamente.

Próximo subpaso para preparar: **6.17 — UC–29**. Modalidad acelerada experimental aprobada: primera ejecución 6.17–6.18 y segunda 6.19–6.21, con prioridad de optimización de cuota semanal. Este checkpoint no ejecuta ni autoriza automáticamente 6.17.
