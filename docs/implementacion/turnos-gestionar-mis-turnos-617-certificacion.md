# UC–06 — Subpaso 6.17: certificación y cierre de regresión integral

**6.17: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.** No se anticipa el hash de ese commit.

## Alcance y referencia

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD inicial: `d73af11ef98d77ebb81de59a56ada35d9af4639c`, cierre de 6.16; staging vacío y sin cambios trackeados al comenzar.
- Fuente congelada: `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md`, Fase E:

> Ejecutar regresión integral, pruebas PHP/JavaScript, build, integraciones MariaDB y verificaciones manuales de UX.

La autorización específica de 6.17 exige ejecutar la regresión ordinaria global y reutilizar las certificaciones reales MariaDB y visuales ya cerradas. Por ello se ejecutaron PHP completo, todo JavaScript y un build; las evidencias anteriores se identifican separadamente, sin presentarlas como nuevas ejecuciones.

## Aislamiento efectivo

Antes de iniciar la suite global se comprobó mediante bootstrap Laravel y conexión PDO real:

- `APP_ENV=testing`, `configurationIsCached()=false`.
- Conexión predeterminada `sqlite`, URL de conexión vacía y destino configurado/efectivo `:memory:`.
- `PRAGMA database_list`: un único `main`, sin archivo ni bases adjuntadas.
- `PDO::getAvailableDrivers()`: exclusivamente `sqlite` en el proceso de regresión; sin PDO MySQL.

Preflight y PHPUnit se ejecutaron en procesos separados, evitando la interferencia de manejadores de errores observada al preparar 6.16. El perfil temporal fijó `APP_ENV=testing`, `APP_DEBUG=false`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` y `DATABASE_URL` vacías, `APP_CONFIG_CACHE=bootstrap/cache/NO-CONFIG-UC06-617.php` inexistente, `BCRYPT_ROUNDS=4`, caché/sesión `array`, correo `array`, cola `sync`, broadcast `null` y filesystem `local`. Se generó una clave sintética temporal y se restauraron las variables del proceso mediante `finally`, sin registrar claves o credenciales.

Se utilizó PHPUnit global directamente: `artisan test` crea un subproceso con `PHP_BINARY` sin propagar los argumentos `-n` de restricción de extensiones. La invocación directa conserva esas protecciones y ejecuta el mismo `phpunit.xml`, que incluye íntegramente **Unit y Feature**, sin filtros ni exclusiones añadidas. No se ejecutó el script Composer que llama a `config:clear`.

Las migraciones automáticas de los traits de testing quedaron restringidas al SQLite en memoria. No se ejecutaron comandos manuales de migración/seed, conexiones a MariaDB persistente, servidores ni operaciones sobre sesiones SQLite manuales. Los tests unitarios de guards/protocolo incluidos usan validaciones, mocks y un tripwire que impide abrir un conector MySQL; no son la suite de concurrencia real.

## Comandos ejecutados

Con el perfil temporal anterior y desde la raíz del proyecto:

```powershell
& 'C:\xampp\php\php.exe' -n -d extension_dir=C:/xampp/php/ext -d extension=pdo_sqlite -d extension=mbstring -d extension=fileinfo -d extension=openssl -d memory_limit=512M vendor/bin/phpunit -c phpunit.xml --do-not-cache-result --colors=never --fail-on-risky --fail-on-warning

node --test tests/JavaScript/bloqueo-form.test.mjs tests/JavaScript/mis-turnos.test.mjs tests/JavaScript/solicitar-turno.test.js

npm.cmd run build
```

`package.json` no define un script JavaScript `test`; los tres archivos anteriores son la suite completa existente de Node. No se instaló ni actualizó ninguna dependencia.

Para las verificaciones estáticas se obtuvo el inventario con `git diff --name-only 6c9dcd63160ca60603547feab7eb6afe525ee653 HEAD -- app routes config database tests`, limitado a archivos `.php` existentes, excluyendo Blade. Sobre esos **54 archivos de UC-06** se ejecutó `C:\xampp\php\php.exe -n -l <archivo>` y, en una única invocación, `C:\xampp\php\php.exe -n -d extension_dir=C:/xampp/php/ext -d extension=mbstring vendor/bin/pint --test <54 rutas explícitas derivadas del inventario>`. También se ejecutó `git diff --check`.

## Resultados de esta ejecución

| Verificación | Resultado exacto |
| --- | --- |
| PHP ordinario global | **2760 tests / 18474 assertions PASS**, 08:15.712, 332 MB; PHP 8.2.12, PHPUnit 11.5.55; exit code **0**. |
| Incidencias PHP | **0 failures, 0 errors, 0 skipped, 0 risky, 0 warnings**. Una única ejecución global, sin filtros. |
| JavaScript completo | **196 tests PASS**, 0 fail, 0 cancelled, 0 skipped, 0 todo; 1092,0804 ms; exit code **0**. Desglose: 150 wizard + 41 Mis turnos + 5 bloqueo-form. |
| Build | **PASS, 95 módulos**, Vite 7.3.1, 5.05 s; exit code **0**. |
| Lint PHP de UC-06 | **54 archivos PASS**. |
| Pint acotado, `--test` | **54 archivos PASS**, sin formatear archivos. |
| `git diff --check` | **PASS**. |

No se detectaron regresiones en el alcance verificado. El resultado de Pint corresponde únicamente al incremento, sin afirmar que se corrigió o certificó la deuda global de formato ajena a UC-06.

### Incidencias del entorno y avisos

- El primer intento Node fue rechazado por el entorno con `Error: spawn EPERM` al iniciar los tres archivos; no llegó a ejecutar sus casos. La misma suite se ejecutó después con autorización para crear los subprocesos fuera del sandbox y obtuvo los **196 PASS** anteriores. No hubo cambios de código, tests o assertions, ni nuevas ejecuciones JavaScript después de ese PASS.
- El build necesitó la autorización de subprocesos de Vite/esbuild y se ejecutó una vez. Emitió el aviso informativo de Browserslist: los datos `caniuse-lite` tenían siete meses. No fue un error de compilación y no se ejecutó la actualización sugerida.
- No hubo incidencias PHP que exigieran correcciones o repetición global. No se repitieron suites para certificar la documentación.

## Evidencia reutilizada y exclusiones explícitas

| Fuente | Evidencia conservada, no reejecutada |
| --- | --- |
| `turnos-gestionar-mis-turnos-613-certificacion.md` | MariaDB 10.4.32 / InnoDB / REPEATABLE-READ: V1 **2/49 PASS**, V2-A **1/21 PASS**, V2-B **27/609 PASS**; 25 esperas reales, 8 carreras, 26 límites entre casos, UNIQUE, timeout 1205, deadlock 1213 y rollback; auditoría final sin residuos ni conexiones ajenas. |
| `turnos-gestionar-mis-turnos-614-certificacion.md` | Revisión técnica R1 y validación manual principal de confirmación/cancelación satisfactorias; **1207/8555 PHP**, **62/586 integración UC-07**, **26 + 155 JavaScript**, build 95 módulos, con su alcance histórico propio. |
| `turnos-gestionar-mis-turnos-615-certificacion.md` | UX final: **290/2402 PHP**, **41 + 155 JavaScript**, build 95 módulos y cuatro comprobaciones manuales satisfactorias en SQLite temporal aislado; auditoría PASS. |
| `turnos-gestionar-mis-turnos-616-certificacion.md` | Integraciones disponibles: **45/617 PASS**, recorrido UC-07 → confirmar → cancelar/replay → nueva reserva, y clasificación explícita de consumidores externos pendientes. |

Fuera de la ejecución ordinaria actual quedan **`tests/Integration/MySql`** (configuración especial `phpunit.mysql.xml`, certificación histórica UC-07 de 30 tests / 1219 assertions) y **`tests/Integration/Uc06MariaDb`** (su propio `phpunit.xml`, 27 escenarios reales de UC-06). No pertenecen a los directorios Unit/Feature del XML ordinario y no se introdujo una exclusión adicional para ocultarlos.

Tampoco se repitieron verificaciones visuales o pruebas sobre las sesiones SQLite manuales. Las certificaciones históricas y dirigidas no se suman entre sí ni al resultado global como una única ejecución; SQLite no acredita locking real o ausencia universal de deadlocks en MariaDB.

## Integridad, dependencias y transición

Las huellas iniciales y posteriores de **683 archivos versionados** de `app`, `routes`, `tests`, `config`, `database`, `resources` y manifiestos de dependencias/build/testing coinciden. Huella conjunta SHA-256: `4598E754B82808973C6B01E2AD54E696EE09B612C4440328B4F25D989D4FCAFE`. Se calculó ordenando ordinalmente los registros `ruta:SHA256_EN_MAYUSCULAS`, unidos por LF sin salto final y codificados en UTF-8. No se modificaron código, tests, configuración persistente, migraciones, dependencias ni fuentes congeladas. Los artefactos regenerados por el build permanecen fuera del inventario versionado propuesto.

Persisten las dependencias descritas en 6.16: **C-02 / DT-03**, planes/sesiones clínicas reales, flujo UC-08, reprogramación profesional completa, resolvedor de enlaces, recordatorios/Agenda Diaria Automática, consumidor de intenciones, candidatos, ofertas y aceptación. Son contratos y componentes externos pendientes, no integraciones implementadas por superar la regresión ordinaria. Las decisiones externas DX-01–DX-07 no se resolvieron implícitamente.

Esta fase modifica únicamente `AGENTS.md` y crea este documento. No incorpora credenciales, datos personales reales ni evidencia temporal. `docs/archivo/` permanece sin inspección ni modificación. No se realizó staging, commit o push.

**Dictamen: LISTO PARA COMMIT.** 6.17 queda cerrado mediante el commit que incorpore esta certificación; **6.18 es el siguiente subpaso pendiente, no iniciado**, sujeto a autorización independiente. No se ejecuta una segunda batería de pruebas para estos cambios documentales.
