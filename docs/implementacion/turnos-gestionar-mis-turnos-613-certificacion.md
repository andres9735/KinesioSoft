# UC–06 — Subpaso 6.13: certificación y cierre definitivo

**Estado:** IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.

## Referencia y alcance

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD de referencia: `9bd7586c1cac7070d534d78e4f4e650ba05ac98f`, cierre de 6.12.
- Alcance: concurrencia real, mutex compartidos, transiciones de confirmación/cancelación, revalidación después de esperas, límites temporales, idempotencia, recuperación UNIQUE, timeout 1205, deadlock 1213 y rollback.

## Incidencias y correcciones

- **D1 — Infraestructura:** el coordinador conservaba una conexión principal después del teardown y el guard del siguiente caso la rechazaba como ajena. `MariaDbUc06TestCase.php` cierra explícitamente las conexiones propias principal y observadora mediante `finally`, incluso con setUp parcialmente inicializado o errores de limpieza; conserva las excepciones y el orden workers → transacciones/fixtures → desconexión.
- **D2 — Aplicación:** una colisión UNIQUE 1062 podía escapar de `firstOrCreate()` cuando la reconsulta ordinaria no veía la intención ganadora en el snapshot REPEATABLE-READ. `RegistrarIntencionEvaluacionCupoService.php` conserva `firstOrCreate()` y su savepoint; comprueba el código, índice esperado, conexión, INSERT y transacción activa. Recupera por `auditoria_cancelacion_id` mediante `lockForUpdate()` en la misma conexión/transacción y valida el contexto inmutable. Las excepciones ajenas a ese caso conservan su semántica.

Los escenarios, assertions, guards, nivel de aislamiento y mecanismos originales de sincronización/observación se preservan. No se introdujeron reintentos automáticos ni dependencias productivas de la instrumentación temporal.

## Certificación MariaDB

Entorno certificado: **MariaDB 10.4.32, InnoDB, REPEATABLE-READ**, en `127.0.0.1:3306/kinesiosoft_uc06_613_test`, base exclusiva y descartable con autorización específica. `kinesiosoft` y `kinesiosoft_uc07_test` no son destinos alternativos de esta suite.

| Validación | Resultado | Evidencia principal |
| --- | --- | --- |
| V1 | **2 tests / 49 assertions PASS**, exit code 0 | Dos casos consecutivos en el mismo coordinador; primer teardown y segundo guard original satisfactorios. |
| V2-A | **1 test / 21 assertions PASS**, exit code 0 | Ambos workers OK, misma intención; lectura actual `lockForUpdate()` observada en el recuperador. |
| V2-B | **27 tests / 609 assertions PASS**, exit code 0 | Suite original completa: 25 esperas reales InnoDB, 8 carreras y 26 límites entre casos validados. |

V2-B certificó mutex, transiciones, revalidaciones, límites temporales, recuperación UNIQUE, timeout 1205, deadlock 1213, rollback e idempotencia con las assertions originales.

Preflight y auditoría posterior **PASS**: 48 migraciones, 44 tablas, UNIQUE de `auditoria_cancelacion_id` y tres claves foráneas restrictivas verificados; esquema intacto, sin filas residuales fuera de `migrations` ni conexiones ajenas. La auditoría terminó con rollback y desconexión. El reporte `resultado-v2b.json` registra `PASS_V2B_27_ORIGINALES`, Git íntegro y entorno restaurado.

Las ejecuciones MariaDB ya aprobadas no se repiten en esta preparación documental. La evidencia temporal detallada permanece fuera del repositorio.

## Regresión final ordinaria — Fase C

Aislamiento efectivo verificado antes de ejecutar: `APP_ENV=testing`, PDO SQLite `:memory:`, sin configuración cacheada. `phpunit.xml` incluye exclusivamente `Unit` y `Feature`; la suite MariaDB especial queda excluida. Los tests ordinarios de guards/protocolo no abren conexiones MariaDB.

| Verificación | Resultado |
| --- | --- |
| Pruebas dirigidas | **259 tests / 2040 assertions PASS**, exit code 0. |
| Suite PHP ordinaria completa, `php artisan test` | **2712 tests / 17947 assertions PASS**, exit code 0; 288,99 s. |
| Lint PHP | **11 archivos PASS**: servicio productivo y diez PHP nuevos. |
| Pint acotado en modo `--test` | **PASS** sobre los once archivos PHP del alcance. |
| `git diff --check` y whitespace de archivos nuevos | **PASS**. |

MariaDB y SQLite son certificaciones separadas. V1, V2-A y V2-B también corresponden a ejecuciones distintas; **no se suman sus cantidades como una única ejecución**. Las pruebas dirigidas están incluidas en el alcance de la suite ordinaria completa.

## Integridad certificada — SHA-256

| Elemento | SHA-256 |
| --- | --- |
| `app/Services/Turnos/RegistrarIntencionEvaluacionCupoService.php` | `FA0939B20214EC8C4BF6C616FD0EAE9C32D0002BD3540826098050BC6CC6AF0D` |
| `tests/Integration/Uc06MariaDb/Support/MariaDbUc06TestCase.php` | `176F671EFC7253B87952A3EB6B8D41456A897EE1226647EE2238A0A4B6F6C6CE` |
| Conjunto de once archivos nuevos de pruebas y soporte de 6.13, incluido `phpunit.xml` | `0bc476937c15b46eb01840d83aad7cc00709056fe23a38bb592d4b6651293163` |

La huella conjunta es SHA-256 de los registros `ruta/relativa:SHA256_EN_MAYUSCULAS`, ordenados con comparación ordinal, unidos con LF sin salto final y codificados en UTF-8. Código y pruebas mantienen estas huellas después de la regresión; esta fase sólo modifica documentación.

## Limitaciones y transición

- La certificación cubre los escenarios y el entorno indicados; no demuestra ausencia universal de deadlocks ni equivalencia de otros motores, versiones o niveles de aislamiento. SQLite no acredita locking real.
- **C-02 / DT-03 permanece PENDIENTE:** integración clínica de planes y sesiones reales de Seguimiento sin infraestructura externa disponible ni certificación.
- **Integraciones externas PENDIENTES:** evaluación efectiva de cupos, candidatos, ofertas, enlaces seguros y comunicaciones. La intención durable no implementa esos consumidores o canales.
- UC–07 y Agenda se preservan. JavaScript y build mantienen sus antecedentes históricos; no se recertifican en esta fase documental.
- No se incorporan credenciales, datos personales o clínicos ni archivos temporales de evidencia al repositorio. `docs/archivo/` y las fuentes congeladas permanecen sin inspección ni modificación.
- **6.13: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación.** **6.14: siguiente subpaso PENDIENTE, no iniciado**, sujeto a autorización independiente; 6.15–6.18 continúan pendientes.
