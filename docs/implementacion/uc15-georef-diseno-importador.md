# UC-15 — Georef — Diseño técnico del importador aprobado

## 1. Propósito

Este documento congela el microdiseño técnico aprobado para importar a KinesioSoft los catálogos locales de Georef utilizados por UC-15 — Completar perfil clínico inicial.
Su finalidad es guiar el subpaso posterior de implementación sin reabrir el modelo físico ya aprobado. Este documento no implementa el importador, no ejecuta importaciones y no modifica datos.

## 2. Alcance y estado

- Estado: diseño técnico aprobado; implementación pendiente.
- Rama: `feature/uc15-domicilio-geografia`.
- Fecha de congelación documental: 29/09/2026.
- Alcance: lectura de CSV, ruta operativa, preflight, memoria, idempotencia, batches, transacciones, concurrencia, resolución de FKs, errores, validación posterior y contrato del futuro Service y Command.
- Base física obligatoria: `docs/implementacion/uc15-domicilio-geografia-diseno-fisico.md`.
- Fuente auxiliar vigente: `docs/_codex_sources/README_Codex_sources.md`.
  No se rediscuten tablas, columnas, longitudes, índices, claves únicas ni claves foráneas del diseño físico aprobado.

## 3. Fuentes y datasets

El mapeo obligatorio es:

```text
provincias  ← provincias.csv
localidades ← localidades_censales.csv
calles      ← calles.csv
```

`localidades.csv` es únicamente material auxiliar de análisis. No alimenta la tabla `localidades`.
Los tres CSV se interpretan como UTF-8. Sus IDs oficiales se conservan como strings y nunca se convierten a enteros, para no perder ceros iniciales.
Los totales conocidos del snapshot actual, incluidos 24 provincias, 4.023 localidades censales y aproximadamente 333.800 calles, son datos descriptivos. No son reglas de aceptación ni deben hardcodearse.

## 4. Arquitectura Service / Command

La clase futura del Service será:

```text
app/Services/Geografia/ImportadorGeorefService.php
```

Su API pública mínima será:

```php
public function validar(string $directorio): array;
public function importar(
    string $directorio,
    ?callable $informarProgreso = null,
): array;
```

`validar()` ejecutará únicamente el preflight y no realizará escrituras. `importar()` ejecutará siempre su propio preflight, aunque el Command u otro consumidor haya invocado antes `validar()`. También protegerá la operación contra importaciones concurrentes, importará los catálogos en el orden aprobado y ejecutará la validación posterior.
Los métodos que importen cada catálogo serán privados. No se agregarán DTOs, repositorios ni otras clases sin una necesidad demostrada.
La clase futura del Command será:

```text
app/Console/Commands/ImportarGeografiaCommand.php
```

Su firma será:

```text
geografia:importar
{--path= : Directorio que contiene los tres CSV Georef}
{--validar : Ejecuta sólo preflight}
{--force : Autoriza escritura cuando APP_ENV=production}
```

`--path` es obligatorio lógicamente. Si falta o no contiene un valor utilizable, el Command finalizará con `Command::INVALID`. No se creará la opción `--solo`.
El Command será delgado: interpretará opciones, aplicará la protección de entorno, invocará el Service, presentará progreso y resumen, y traducirá los errores a códigos de salida. Las reglas de importación no residirán en el Command.

## 5. Ubicación de los CSV

No habrá una ruta hardcodeada ni un directorio predeterminado obligatorio. Toda ejecución del Command exigirá `--path=`.
La ruta debe señalar un directorio que contenga como mínimo archivos con exactamente estos basenames:

- `provincias.csv`;
- `localidades_censales.csv`;
- `calles.csv`.
  La presencia de otros archivos no invalida la importación. Una ruta relativa se resolverá desde `base_path()`; una ruta absoluta se utilizará directamente. La ruta se normalizará y los archivos se identificarán por su ruta real.
  En desarrollo puede pasarse explícitamente `docs/_codex_sources/georef`, pero el código no dependerá de esa carpeta ni asumirá que existe.

## 6. Lectura streaming

La lectura se realizará mediante `fopen()` y `fgetcsv()` en streaming. `calles.csv` nunca se cargará completo en memoria.
Parámetros CSV congelados:

- codificación: UTF-8;
- delimitador: coma `,`;
- enclosure: comillas dobles `"`;
- escape: string vacío explícito;
- fin de línea: lectura normal de CRLF o LF.
  Sólo se retirará un BOM UTF-8 si aparece al comienzo del primer header. No se eliminarán BOM ni caracteres de los valores de datos. Los IDs permanecerán como strings durante todo el flujo.

## 7. Preflight

El preflight debe finalizar por completo antes de cualquier escritura. Verificará para cada fuente:

- existencia de los tres archivos requeridos;
- que cada ruta sea un archivo regular;
- legibilidad;
- UTF-8 válido;
- presencia y validez del header;
- ausencia de headers duplicados;
- presencia de todas las columnas requeridas;
- ancho consistente de cada fila respecto del header;
- errores de apertura y lectura;
- formatos, obligatoriedad y longitudes compatibles con el diseño físico;
- unicidad de `georef_id` dentro de la fuente;
- relaciones entre fuentes y coherencia provincial.
  Además verificará que estén disponibles las tablas y columnas de destino requeridas por el diseño físico. Esta comprobación es de disponibilidad de esquema y no reemplaza las validaciones del source.
  Las columnas requeridas son:
  | Fuente | Headers requeridos |
  | --- | --- |
  | `provincias.csv` | `id`, `nombre` |
  | `localidades_censales.csv` | `id`, `nombre`, `departamento_nombre`, `provincia_id`, `provincia_nombre` |
  | `calles.csv` | `id`, `nombre`, `localidad_censal_id`, `provincia_id`, `provincia_nombre` |
  No se exigirá un orden fijo de columnas. Se aceptarán columnas adicionales y se informarán como advertencias.
  Las validaciones contra el diseño físico incluyen, como mínimo:
- IDs obligatorios tratados como texto y compatibles con `provincias.georef_id CHAR(2)`, `localidades.georef_id CHAR(8)` y `calles.georef_id CHAR(15)`;
- `provincias.nombre` obligatorio y de hasta 100 caracteres;
- `localidades.nombre` obligatorio y de hasta 150 caracteres;
- `localidades.departamento_nombre` obligatorio y de hasta 100 caracteres;
- `calles.nombre` obligatorio y de hasta 150 caracteres;
- IDs de relación obligatorios, textuales y compatibles con el identificador de su catálogo de destino.
  El `trim` sólo podrá utilizarse para decidir si un campo obligatorio está vacío; el valor oficial no se reemplazará por su versión recortada.
  La coherencia relacional exigirá que:
- cada `localidades_censales.csv.provincia_id` exista en `provincias.csv.id`;
- cada `calles.csv.localidad_censal_id` exista en `localidades_censales.csv.id`;
- la provincia declarada por una calle coincida con la provincia de su localidad censal;
- los nombres provinciales informados por las fuentes sean coherentes con el ID provincial correspondiente.
  Todo error de source abortará la operación antes de escribir.

## 8. Uso de memoria y sets

El preflight será streaming y no guardará las filas completas de `calles.csv`. Sólo conservará la información mínima necesaria:

- contadores;
- sets de IDs;
- mapas pequeños indispensables para relaciones y coherencia;
- errores agrupados por categoría;
- hasta 20 ejemplos por categoría;
- un máximo aproximado de 100 ejemplos mostrables en total.
  El límite de ejemplos no limita el conteo total de errores. El resumen debe informar los totales aunque omita detalles repetitivos.
  Todo set o mapa asociativo indexado por un ID Georef utilizará una clave prefijada:

```text
g:{georef_id}
```

Esta regla se aplica a detección de duplicados, sets del snapshot, mapas de provincia, mapas de localidad y validación posterior. Ninguna decisión dependerá de la conversión automática de claves numéricas de PHP.
Durante la resolución inicial y el preflight se registrará para cada archivo:

- `realpath`;
- tamaño;
- fecha de modificación.
  La identidad registrada se controlará durante la lectura cuando corresponda y se reconfirmará para los tres archivos después del preflight y antes de comenzar cualquier persistencia. Si cualquiera cambió, la importación abortará antes de escribir. No se incorporarán staging ni copias automáticas en este incremento.

## 9. Idempotencia

La estrategia será comparación por lote seguida de `upsert`, con `georef_id` como clave de conflicto.
Para cada lote:

1. obtener los `georef_id` del lote;
2. consultar en una sola query los registros existentes de esos IDs;
3. clasificar las filas como nuevas, modificadas o sin cambios;
4. ejecutar `upsert` sólo para las nuevas y modificadas;
5. excluir de toda escritura las filas idénticas.
   No se utilizarán:

- `truncate`;
- eliminación y reconstrucción;
- `updateOrCreate` por fila;
- `insert` simple como estrategia general.
  Una reimportación idéntica no modificará filas ni timestamps. Los IDs internos ya existentes se conservarán.

## 10. Política de registros ausentes

La política aprobada para este incremento es **CONSERVAR Y REPORTAR**.
Un registro existente en la base que no figure en el nuevo snapshot:

- no se elimina;
- conserva su ID interno;
- no se marca con un estado inexistente en el esquema;
- se cuenta y se informa como ausente conservado.
  La política se aplica a provincias, localidades y calles, y protege especialmente las relaciones históricas. El importador es conservador: no constituye una sincronización destructiva completa.
  Una futura baja o inactivación requerirá un cambio explícito de diseño y, si corresponde, una ampliación del esquema.

## 11. Batches

Los tamaños iniciales aprobados son constantes internas:
| Catálogo | Tamaño |
| --- | ---: |
| Provincias | 24, procesadas en un único lote |
| Localidades | 500 |
| Calles | 1000 |
No se expondrá una opción CLI para cambiar el tamaño de lote. El valor 24 de provincias es una decisión de batch, no una regla de aceptación sobre el total del source.

## 12. Transacciones y recuperación

La estrategia transaccional será:

1. preflight completo sin escritura;
2. una transacción para provincias;
3. una transacción para localidades;
4. una transacción independiente por lote de calles;
5. validación posterior.
   No habrá una transacción global ni una estrategia de staging/swap. Por lo tanto, **no existe atomicidad del snapshot completo**.
   Si ocurre un error de base de datos, se revierte la transacción actual y se detiene el proceso; no se continúa con catálogos o lotes posteriores. Las transacciones anteriores ya confirmadas permanecen confirmadas.
   En particular, si falla un lote de calles:

- los lotes anteriores quedan confirmados;
- el lote fallido se revierte;
- el Command termina con error;
- el flujo funcional no debe habilitarse;
- una nueva ejecución completa lo pendiente de forma idempotente.

## 13. Concurrencia

`importar()` debe impedir que dos importaciones con escritura se ejecuten simultáneamente. La protección pertenece conceptualmente al Service y abarcará la operación de importación, para que no dependa del Command.
`validar()` no necesita adquirir el lock de escritura. La implementación podrá usar un lock atómico soportado por Laravel.
Antes de elegir el mecanismo se comprobará el cache store real. Si no permite garantizar exclusión mutua de forma fiable, la implementación se detendrá y se reportará el impedimento; nunca se omitirá la protección silenciosamente.
No se congela todavía un TTL arbitrario. Su valor y la estrategia concreta de liberación se definirán al revisar la implementación real y el soporte efectivo del store.

## 14. Orden de importación

El flujo completo será exactamente:

1. resolver rutas y registrar el estado de los archivos;
2. ejecutar el preflight de provincias;
3. ejecutar el preflight de localidades;
4. ejecutar el preflight de calles;
5. reconfirmar que los archivos no cambiaron;
6. importar provincias;
7. construir el mapa de provincias;
8. importar localidades;
9. construir el mapa de localidades;
10. importar calles;
11. ejecutar la validación posterior;
12. producir el resumen.
    No se importará un catálogo posterior si falla una etapa anterior.

## 15. Resolución de FKs

Después de importar provincias se construirá un mapa:

```text
provincias.georef_id → provincias.id interno
```

Después de importar localidades se construirá un mapa:

```text
localidades.georef_id → localidades.id interno
```

Ambos mapas usarán claves `g:{georef_id}`. No se realizará una query por fila.
La resolución será:

```text
localidades_censales.csv.provincia_id externo
    → mapa de provincias
    → localidades.provincia_id interno
```

```text
calles.csv.localidad_censal_id externo
    → mapa de localidades
    → calles.localidad_id interno
```

Las FKs internas nunca almacenarán directamente IDs externos de Georef.

## 16. Upsert y timestamps

Para una fila nueva:

```text
created_at = now
updated_at = now
```

Para una fila modificada:

```text
created_at = valor existente preservado
updated_at = now
```

Una fila sin cambios no se incluirá en el `upsert` y no modificará timestamps.
`now` se capturará una sola vez por catálogo o lote, según la unidad transaccional correspondiente, y no una vez por fila. La lista de columnas actualizables del `upsert` debe impedir que una modificación reemplace el `id` interno o el `created_at` existente.

## 17. Normalización y duplicados

Sólo se permite:

- retirar el BOM UTF-8 del primer header;
- leer normalmente archivos CRLF o LF;
- aplicar `trim` exclusivamente para comprobar si un requerido está vacío;
- normalizar la ruta operativa.
  No se modificará el valor oficial de nombres, tildes, mayúsculas, abreviaturas, caracteres raros ni espacios internos.
  Reglas de duplicados:
- `georef_id` repetido en una fuente: error;
- mismo nombre con distinto `georef_id`: permitido;
- misma calle por nombre dentro de una localidad: permitido;
- deduplicación visual de calles: fuera del importador y reservada para el endpoint futuro.
  No se deduplicarán calles por nombre.

## 18. Manejo de errores

Los errores se agruparán por categoría y conservarán ejemplos limitados conforme a la sección 8. No se imprimirán miles de filas.
Clasificación operativa:

- error de source o preflight: abortar antes de cualquier escritura;
- cambio de un archivo tras el preflight: abortar antes de comenzar la persistencia;
- error de base de datos: rollback de la transacción actual y detención inmediata;
- error de validación posterior: finalizar con error y no habilitar el flujo funcional.
  Se utilizarán inicialmente excepciones estándar. No se creará una jerarquía propia de excepciones sin una necesidad concreta demostrada.
  El Command traducirá el resultado o la excepción a un código de salida apropiado y mostrará un resumen acotado. No ocultará errores ni continuará con catálogos posteriores.

## 19. Validación posterior

Después de la escritura se comprobará dinámicamente:

- que todos los IDs del source estén presentes en la base;
- que la resolución de relaciones sea completa;
- que existan cero huérfanos;
- que los duplicados detectados en el source sean cero;
- cantidad de filas insertadas;
- cantidad de filas actualizadas;
- cantidad de filas sin cambios;
- cantidad de registros ausentes conservados;
- totales físicos resultantes por tabla.
  Los sets y mapas de esta validación también usarán claves `g:{georef_id}`. Las verificaciones se resolverán en bloque o por lotes, nunca mediante una query por fila.
  No se hardcodearán 24, 4.023 ni 333.800 como reglas de aceptación. Los resultados se compararán con el snapshot efectivamente leído y con el estado real de la base. Dado que se conservan ausentes, el total físico de una tabla puede ser mayor que el total del source sin que eso constituya por sí solo un error.

## 20. Rendimiento esperado

El costo de lectura será lineal respecto de las filas de los tres CSV. La fuente de calles se recorrerá en streaming y la escritura se hará en lotes de 1.000 filas como máximo.
La comparación por lote limitará las consultas a una lectura de existentes y, cuando corresponda, un `upsert` por lote. Los mapas de provincias y localidades evitarán consultas por cada localidad o calle.
El diseño prioriza memoria acotada y reejecución segura. No promete un tiempo absoluto de ejecución: el tiempo real dependerá del hardware, el almacenamiento, el motor de base de datos y el cache store disponibles. El callback opcional de progreso permitirá informar avance sin trasladar reglas del Service al Command.

## 21. Testabilidad futura

Los tests futuros utilizarán fixtures pequeños versionados. PHPUnit no leerá el `calles.csv` completo ni dependerá de `docs/_codex_sources/georef`.
Casos mínimos futuros:

- importación inicial;
- reimportación idéntica;
- cambio de nombre conservando ID interno y `created_at`;
- provincia inexistente;
- localidad inexistente;
- ID duplicado;
- calle repetida por nombre con distinto `georef_id`, permitida;
- preflight inválido sin escritura;
- registro ausente en un snapshot futuro, conservado;
- reintento después de un estado parcial;
- `--validar` sin escritura;
- protección de `production` y `--force`.
  Los fixtures deberán cubrir encabezados, relaciones, longitudes, UTF-8, filas inválidas y columnas adicionales sin convertir los tests en una copia del dataset productivo.

## 22. Seguridad operativa

En `APP_ENV=production`:

- `--validar` podrá ejecutarse sin `--force` porque no escribe;
- toda importación con escritura requerirá `--force`;
- `--force` no omitirá el preflight, la reconfirmación de archivos, el lock, las validaciones ni la validación posterior.
  No se pedirá confirmación interactiva. La ausencia de `--force` en una importación productiva producirá una salida de error sin escritura.
  La operación no utilizará rutas implícitas, no ejecutará imports desde migrations, no hará `truncate` ni borrados y no habilitará el flujo funcional si termina de forma parcial o si falla la validación posterior.

## 23. Decisiones diferidas

Quedan expresamente diferidas:

- mecanismo concreto de lock atómico según el cache store real;
- TTL y estrategia final de expiración/liberación del lock;
- futura política de baja o inactivación para registros ausentes;
- estrategia de búsqueda, deduplicación visual y autocomplete;
- momento y mecanismo exactos para habilitar el flujo funcional después de una importación completa y válida;
- optimizaciones adicionales que sólo se justifiquen con mediciones reales.
  Estas decisiones no autorizan a omitir las garantías congeladas en este documento.

## 24. Fuera de alcance

No se diseñan ni implementan en este subpaso:

- endpoints;
- autocomplete;
- Form Requests;
- Controller;
- Action de UC-15;
- regla funcional de completitud;
- Blade;
- Alpine;
- auditoría;
- estado Provisorio;
- barrios;
- mapas;
- geocodificación.
  Tampoco se implementan todavía el Service, el Command, sus tests ni una importación real.

## 25. Checklist previo a implementación

Decisiones de diseño aprobadas:

- [x] Fuentes primarias y headers requeridos definidos.
- [x] Ruta operativa obligatoria mediante `--path` definida.
- [x] Lectura CSV streaming y parámetros de `fgetcsv()` definidos.
- [x] Contratos mínimos del Service y del Command definidos.
- [x] Preflight completo antes de toda escritura definido.
- [x] Límites de memoria, ejemplos y claves `g:{georef_id}` definidos.
- [x] Inmutabilidad de los archivos durante la operación definida.
- [x] Idempotencia por comparación de lote y `upsert` definida.
- [x] Política **CONSERVAR Y REPORTAR** definida.
- [x] Batches y transacciones definidos.
- [x] Ausencia de atomicidad del snapshot completo documentada.
- [x] Requisito de exclusión mutua definido.
- [x] Orden de importación y resolución de FKs definidos.
- [x] Timestamps y ausencia de escritura para filas idénticas definidos.
- [x] Manejo de errores y validación posterior definidos.
- [x] Protección de producción definida.
- [ ] `ImportadorGeorefService` implementado.
- [ ] `ImportarGeografiaCommand` implementado.
- [ ] Tests con fixtures pequeños implementados.
- [ ] Importación real ejecutada.
- [ ] Validación real contra MySQL ejecutada.

## 26. Próximo subpaso autorizado

Implementar ImportadorGeorefService, ImportarGeografiaCommand y sus tests con fixtures pequeños, sin ejecutar todavía la importación real de los datasets completos sobre MySQL.
