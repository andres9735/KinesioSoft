# Agenda Diaria Automática — Checkpoint del bloque B (6.07–6.09)

**Estado: CERTIFICADO para los contratos internos de lectura y autorización implementados.**

- Fecha: **05/11/2026**, hora local `America/Argentina/Buenos_Aires`.
- Rama: `feature/turnos-agenda-diaria-automatica`.
- HEAD de revisión y ejecución conjunta, previo al checkpoint: `1ec42444346ad6005ff7b622e33fdb46a861ec0b`.
- El commit que incorpora este documento registra el checkpoint; no se anticipa su hash.

## Alcance y revisión conjunta

| Subpaso | Componente | Contrato implementado |
| --- | --- | --- |
| 6.07 | `AgendaAtencionQueryService::turnosDeJornada()` | Turnos persistidos de una profesional por jornada local completa, incluidos horarios anteriores a las 7:00, ambos tipos y seis estados. |
| 6.08 | `turnosDePeriodo()` e `informacionDePeriodo()` del mismo servicio | Fechas civiles desde/hasta inclusivas, fin exclusivo al iniciar el día posterior a hasta, pertenencia por `inicio_at`, orden por inicio e id y representación administrativa mínima. La jornada delega en la consulta común. |
| 6.09 | `AgendaProfesionalQueryService::informacionDeJornada()` e `informacionDePeriodo()` | Identidad del guard web, usuario y rol profesional revalidados, y relación profesional persistida propia antes de invocar la lectura de datos de pacientes. |

La representación comprende id del turno, identificación y nombre de paciente y profesional, enums canónicos de tipo/estado e intervalos completos `inicio_at`/`fin_at` como `CarbonImmutable`. Las fechas civiles se interpretan con `app.timezone`, respetando el almacenamiento local existente; no se supone almacenamiento UTC ni se recalculan intervalos mediante configuraciones posteriores.

La selección utiliza campos explícitos; las relaciones se cargan en conjunto, sin consultas por turno. No se entregan modelos completos serializados, motivo clínico, contactos, documentos ni credenciales. No se infieren equipos sin una relación registrada. Los períodos invertidos se rechazan como entrada inválida; no se confunden con agendas vacías ni se hereda el límite de ventana de disponibilidad.

La revisión conjunta de los cuatro PHP no requirió correcciones. No se incorporaron rutas, controladores, interfaces, escrituras funcionales ni modificaciones de estados o reservas. El lector interno permanece independiente de autenticación y de una ejecución previa de UC–29.

## Trazabilidad parcial

Fuentes aprobadas: paquete local congelado `docs/_codex_sources/turnos/agenda_diaria_automatica/`, decisiones AT-01 a AT-04 y AT-09, GB-01 a GB-03 y GB-07, especificaciones extendidas de UC–05/UC–29 y matriz consolidada V1.0.

| Criterios pertinentes | Evidencia del bloque y límite |
| --- | --- |
| CA-05-04/05/06/11/19/22 | Lectura vigente por jornada/período, tipos y estados, vacíos y actualización de datos; navegación y presentación HTTP pendientes. |
| CA-05-08/18/29 | Representación administrativa mínima, sin acceso documental ni escrituras; no certifica detalle, impresión o descarga. |
| CA-05-01/02/17 | Frontera profesional con rechazo por identidad, rol o pertenencia insuficientes, antes de leer pacientes; integración HTTP y detalle pendientes. |
| CA-29-02/03/07/09/10/11/12/13/17/19 | Aporte del lector compartido: jornada completa, aislamiento por profesional, información vigente y estados reales, sin efectos ni dependencia del proceso automático. No certifica selección de profesionales habilitadas, Scheduler ni generación UC–29. |

Estos aportes no declaran completos los casos de uso ni sus criterios funcionales de extremo a extremo. No se certifican límites de rendimiento de interfaz o exportación mediante tiempos de PHPUnit.

## Ejecución conjunta real

Se ejecutó una única regresión focalizada conjunta con **PHP 8.2.12 / PHPUnit 11.5.55**, usando `phpunit.xml` y exclusivamente:

- `tests/Feature/Services/Agenda/AgendaAtencionQueryServiceTest.php`.
- `tests/Feature/Services/Agenda/AgendaProfesionalQueryServiceTest.php`.
- `tests/Feature/Policies/TurnoPolicyTest.php`.
- `tests/Feature/Policies/ConfiguracionAgendaPolicyTest.php`.

Invocación con el perfil de entorno aislado del proceso:

```powershell
& 'C:/xampp/php/php.exe' -n `
  -d variables_order=EGPCS `
  -d extension_dir=C:/xampp/php/ext `
  -d extension=pdo_sqlite -d extension=mbstring `
  -d extension=fileinfo -d extension=openssl -d memory_limit=512M `
  vendor/bin/phpunit -c phpunit.xml --do-not-cache-result --colors=never `
  --fail-on-risky --fail-on-warning `
  tests/Feature/Services/Agenda/AgendaAtencionQueryServiceTest.php `
  tests/Feature/Services/Agenda/AgendaProfesionalQueryServiceTest.php `
  tests/Feature/Policies/TurnoPolicyTest.php `
  tests/Feature/Policies/ConfiguracionAgendaPolicyTest.php
```

**Resultado conjunto: 102 tests / 534 assertions PASS, exit code 0.** Sin failures, errors, risky ni warnings. Duración informada: **00:11.485**; memoria: **60 MB**. Es el resultado de esta ejecución conjunta, sin sumar ejecuciones previas.

Sintaxis `php -n -l` y Pint `--test` limitados a los cuatro PHP: **PASS, exit 0**. Diff y whitespace de los archivos del checkpoint verificados sin diagnósticos antes del versionado.

## Aislamiento y seguridad

El bootstrap efectivo previo a PHPUnit comprobó `testing`, configuración sin caché, SQLite `:memory:` con apertura de PDO y ausencia de PDO MySQL. Mail, caché y sesiones utilizaron `array`; cola `sync`; storage, discos y vistas fueron exclusivos de la ejecución.

Se retiraron overrides relevantes heredados del proceso y se utilizaron una clave efímera y rutas de caché aisladas. `LOG_CHANNEL` y `LOG_DEPRECATIONS_CHANNEL` se entrecomillaron para seleccionar explícitamente el string `null`. Se verificaron los handlers efectivos `Monolog\Handler\NullHandler`, sin salida a archivos. No se persistieron variables ni se modificó `.env` o configuración permanente.

Los rechazos profesionales usan el mismo mensaje genérico y se verifican antes de consultar turnos, pacientes o documentos. Se ensayaron identificadores ajenos, inexistentes y manipulados, rol revocado y relaciones precargadas obsoletas. No se crearon permisos Spatie ni se ampliaron Policies históricas.

## Integridad y límites

- Durante las pruebas se conservaron los hashes de los cuatro PHP y los 726 archivos versionados existentes. Para el checkpoint sólo se actualiza el bloque operativo de `AGENTS.md` y se crea este certificado, además de incorporar los cuatro PHP ya revisados.
- Las secciones históricas de UC–06/UC–07, contratos congelados y aclaración AT-10/GA-07/GA-08 se conservan intactos. No se modificaron Actions, disponibilidad, esquema, rutas ni Policies existentes.
- Las ocho fuentes congeladas, `.git/info/exclude` y los 134 temporales previos de 6.05 conservan sus hashes. Los auxiliares de pruebas permanecen ignorados, sin limpieza. `docs/archivo/` sigue untracked, sin inspección ni modificación.
- **MariaDB no ejecutado ni certificado actualmente.** SQLite no certifica locking real ni concurrencia MariaDB. Las evidencias históricas mantienen sus límites originales.
- No se ejecutaron PHPUnit global, JavaScript ni Vite. El baseline global de 6.05 —PHP 2760 tests / 18474 assertions, JavaScript 196 PASS y build PASS con 95 módulos— permanece como referencia histórica vigente, sin una nueva ejecución ni recertificación global.

## Pendientes y dictamen

6.10 deberá integrar rutas y controlador propios de UC–05 con la frontera profesional y conservar los controles transversales de cuenta activa, correo verificado y perfil. La autorización administrativa no concede acceso clínico. Calendario, filtros de interfaz, detalle, exportaciones, generación automática UC–29, recordatorios y enlaces no se implementan en este checkpoint.

**6.07, 6.08 y 6.09: COMPLETADOS Y VERIFICADOS dentro del alcance del bloque B. Checkpoint conjunto certificado. 6.10 no está autorizado para ejecución automática.**
