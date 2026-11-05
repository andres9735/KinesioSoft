# Agenda Diaria Automática — 6.05: certificación del baseline ordinario

**Baseline ordinario actual: CERTIFICADO para PHP/SQLite, JavaScript y build. MariaDB: certificación actual PENDIENTE.**

Registro documental preparado en **6.06**, pendiente de revisión y versionado expresamente autorizado. Este documento registra las ejecuciones de 6.05; su elaboración no repite pruebas ni builds.

## Identificación y alcance

- Proceso: **Agenda Diaria Automática**.
- Casos de uso del incremento: UC–05 Consultar agenda de atención, UC–27 Notificar próximos turnos automáticamente, UC–28 Resolver asistencia de turno vía enlace y UC–29 Generar agenda diaria automáticamente.
- Fecha de certificación y registro documental: **05/11/2026**, referencia local `America/Buenos_Aires`.
- Repositorio: `C:\xampp\htdocs\Proyecto2026\KinesioSoft`.
- Rama: `feature/turnos-agenda-diaria-automatica`.
- Commit base y HEAD durante 6.05: `ddcbdb9acfdf0ef87b3220179ecdcfe2dd249082` — `docs: cerrar incremento UC-06 gestionar mis turnos`.
- Evidencia de origen: informe efectivo de 6.05 y sus resultados de ejecución; esta certificación no reconstruye resultados a partir de antecedentes históricos.

El objetivo de 6.05 fue obtener un baseline actual y reproducible antes de implementar el nuevo incremento, mediante la suite PHP ordinaria sobre SQLite en memoria, las tres suites JavaScript y el build de producción. No comprendió implementar funcionalidades, corregir código, cambiar pruebas, instalar dependencias ni ejecutar las integraciones MariaDB.

## Preflight Git e instrucciones

Antes de las ejecuciones se leyó íntegramente `AGENTS.md` mediante `Get-Content` y se revisó su diff completo. La rama y HEAD coincidían con los autorizados; `AGENTS.md`, actualizado en 6.04, era el único archivo versionado modificado. No había staging y permanecía la entrada untracked `docs/archivo/`, sin inspección ni modificación.

Se verificaron el alcance de los cuatro casos de uso, el seguimiento 6.04/6.05, las fuentes aprobadas, la aclaración complementaria AT-10/GA-07/GA-08 y la conservación de los contratos de UC–06, UC–07 y Configuración de Agenda. No se detectaron instrucciones activas incompatibles con la ejecución expresamente autorizada.

## Aislamiento efectivo de PHP y seguridad de ejecución

El preflight comprobó mediante bootstrap Laravel y apertura de PDO SQLite:

| Componente | Resultado verificado en 6.05 |
| --- | --- |
| PHP CLI | 8.2.12, invocado con `-n` y extensiones explícitas |
| Entorno | `APP_ENV=testing` |
| Configuración cacheada | No |
| Conexión y destino | SQLite `:memory:` |
| Drivers PDO | Exclusivamente `sqlite`; sin PDO MySQL |
| Correo | `array` |
| Caché | `array` |
| Sesiones | `array` |
| Cola | `sync` |
| Storage y vistas compiladas | Directorios exclusivos de esta ejecución |

Se controlaron las variables de entorno del proceso, retirando overrides relevantes heredados y fijando explícitamente el perfil aislado. Se utilizó una clave de aplicación efímera sin persistirla ni incorporarla a la evidencia. No se expusieron secretos ni se modificaron archivos de entorno.

La configuración `phpunit.xml` incluye únicamente los directorios **Unit y Feature**. Las suites de integración MariaDB no forman parte de ese XML. Se utilizó PHPUnit directamente para conservar `-n` y las restricciones de extensiones, sin sustituirlo por `php artisan test`.

Las pruebas JavaScript no escriben archivos. Antes del build se verificó que la salida efectiva de Vite era `public/build`, sin archivos versionados, enlaces de filesystem ni contenido ajeno al manifiesto y sus assets generados. No se realizaron limpiezas manuales.

## Comandos efectivamente ejecutados en 6.05

Desde la raíz del repositorio y con el perfil de proceso aislado descrito:

```powershell
& 'C:/xampp/php/php.exe' -n `
  -d extension_dir=C:/xampp/php/ext `
  -d extension=pdo_sqlite `
  -d extension=mbstring `
  -d extension=fileinfo `
  -d extension=openssl `
  -d memory_limit=512M `
  vendor/bin/phpunit -c phpunit.xml `
  --do-not-cache-result --colors=never `
  --fail-on-risky --fail-on-warning
```

```powershell
node --test `
  tests/JavaScript/bloqueo-form.test.mjs `
  tests/JavaScript/mis-turnos.test.mjs `
  tests/JavaScript/solicitar-turno.test.js
```

```powershell
npm.cmd run build
```

Estos bloques son el registro de ejecuciones anteriores, no una instrucción para repetirlas durante 6.06. No se instalaron ni actualizaron dependencias.

## Resultados actuales de 6.05

### PHP / SQLite

| Dato | Resultado |
| --- | --- |
| PHP | **8.2.12** |
| PHPUnit | **11.5.55** |
| Tests | **2760 PASS** |
| Assertions | **18474** |
| Failures / errors / risky / warnings | **0 / 0 / 0 / 0** |
| Código de salida | **0** |
| Duración | **10:17.914** |
| Memoria | **334 MB** |
| Ejecuciones ordinarias completas | **Una** |

Salida final registrada: `OK (2760 tests, 18474 assertions)`. No se corrigió código ni se repitió la suite completa.

### JavaScript

- Node.js: **25.8.1**.
- **196 tests PASS / 0 FAIL / 0 SKIP**.
- Código de salida: **0**.

El primer intento fue bloqueado por `spawn EPERM` del sandbox antes de ejecutar los casos. La misma invocación se ejecutó satisfactoriamente fuera del sandbox con autorización, sin modificar archivos de pruebas. El bloqueo inicial no se suma a los 196 casos de la ejecución satisfactoria como una batería adicional.

### Build de producción

- Vite: **7.3.1**.
- Resultado: **PASS**.
- Módulos transformados: **95**.
- Código de salida: **0**.
- Duración de compilación informada: **5.12 segundos**.
- Aviso informativo: datos de Browserslist de **ocho meses** de antigüedad. No se ejecutó la actualización sugerida ni se modificaron dependencias.

La ejecución se realizó fuera del sandbox por el bloqueo de subprocesos previamente comprobado. Los cuatro archivos recompilados en `public/build` conservaron sus hashes anteriores:

- `manifest.json`.
- `assets/app-39c7LKqe.css`.
- `assets/app-lIMRfClo.css`.
- `assets/app-CQlaMh0d.js`.

## Integridad y artefactos generados

Durante 6.05 se compararon las huellas anteriores y posteriores de **725 archivos versionados**, incluidas las modificaciones documentales de `AGENTS.md` ya existentes al iniciar ese subpaso. No hubo alteraciones durante las pruebas ni cambios funcionales.

Las **ocho fuentes aprobadas** de `docs/_codex_sources/turnos/agenda_diaria_automatica/` permanecieron intactas y excluidas de Git. También se conservaron las huellas de `AGENTS.md`, `.gitignore`, `.git/info/exclude` y de los archivos preexistentes verificados en cachés, framework, logs y build. `.vite-temp` quedó sin archivos.

`git diff --check` obtuvo **PASS**, código de salida **0**. La rama y HEAD no cambiaron y no se realizó staging, commit ni ninguna operación sobre remotos.

Las pruebas dejaron **134 archivos temporales ignorados**, correspondientes a **133 vistas compiladas y un archivo de disco ficticio**, en:

`storage/framework/testing/agenda-diaria-605-mw3j8dlm-sixdold1/`

Estos archivos se conservan sin limpieza y no deben agregarse a Git. `docs/archivo/` permaneció sin inspección ni modificación.

Al finalizar 6.05, el estado Git seguía mostrando exclusivamente:

```text
 M AGENTS.md
?? docs/archivo/
```

El registro documental de 6.06 modifica únicamente el bloque operativo de `AGENTS.md` y crea este documento. Esas modificaciones autorizadas posteriores no forman parte del inventario inalterado durante la ejecución de 6.05.

## MariaDB: exclusiones y certificación pendiente

**NO se ejecutaron** las siguientes suites:

| Configuración | Suite real | Destino de prueba esperado |
| --- | --- | --- |
| `phpunit.mysql.xml` | `tests/Integration/MySql` | `kinesiosoft_uc07_test` |
| `tests/Integration/Uc06MariaDb/phpunit.xml` | `tests/Integration/Uc06MariaDb` | `kinesiosoft_uc06_613_test` |

En 6.05 se realizó únicamente el relevamiento por lectura de sus destinos, guards y requisitos. No se utilizaron bases MariaDB ni se ejecutaron comandos manuales de migración o preparación destructiva.

El harness de UC–07 incluye `migrate:fresh` antes de cada caso, tras sus validaciones de seguridad; por ello requiere autorización específica actual para reinicializar el destino exclusivo. El harness de UC–06 exige un esquema previamente preparado y vacío salvo las migraciones, sus restricciones y ausencia de conexiones ajenas; no prepara automáticamente el esquema.

La certificación futura requiere preflight del destino y servidor, configuración sin caché, perfil y autorización específicos, credenciales suministradas sin exponerlas y verificación de MariaDB/InnoDB con aislamiento `REPEATABLE-READ`. Las pruebas de concurrencia requieren procesos y conexiones independientes para observar bloqueos reales. Cualquier preparación destructiva necesita autorización expresa del usuario.

Los resultados históricos de MariaDB de UC–06 y UC–07 no constituyen una certificación actual de Agenda Diaria Automática y no se presentan como nuevas ejecuciones ni como PASS de este baseline.

## Comparación con el cierre histórico de UC–06

Referencia histórica: `turnos-gestionar-mis-turnos-617-certificacion.md` y cierre 6.18 conservado en `AGENTS.md`.

| Componente | Certificación histórica de UC–06 | Ejecución actual de 6.05 |
| --- | --- | --- |
| PHP ordinario | 2760 tests / 18474 assertions PASS | **2760 tests / 18474 assertions PASS** |
| JavaScript | 196 PASS | **196 PASS / 0 FAIL / 0 SKIP** |
| Build | PASS, 95 módulos | **PASS, 95 módulos** |
| MariaDB | Evidencias históricas separadas | **No ejecutado; certificación actual pendiente** |

Las cantidades de PHP, JavaScript y módulos coinciden. Los resultados actuales provienen de las ejecuciones de 6.05, sin sumar sus cantidades a las históricas ni trasladar certificaciones de alcances no ejecutados.

## Límites y dictamen

El baseline certifica los componentes efectivamente ejecutados y aprobados: **PHP/SQLite, JavaScript y build de producción**. SQLite no certifica locking real, esperas, carreras ni ausencia de deadlocks en MariaDB. La ausencia de ejecución de las suites MariaDB no equivale a PASS.

Esta certificación no acredita la implementación de UC–05, UC–27, UC–28 o UC–29 ni el cumplimiento de sus criterios futuros por la sola existencia de infraestructura previa. No altera contratos congelados ni amplía el alcance del incremento.

**Dictamen de 6.05: BASELINE ORDINARIO ACTUAL CERTIFICADO. MariaDB continúa pendiente de certificación independiente y autorización específica.**

**Estado documental de 6.06: PREPARADO PARA REVISIÓN Y POSTERIOR COMMIT AUTORIZADO.** No se realizó staging ni commit y no se habilita automáticamente 6.07 antes del cierre de 6.06.
