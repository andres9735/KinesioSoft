# UC-15 — Domicilio geográfico — Diseño físico aprobado

## 1. Propósito

Este documento deja congeladas las decisiones aprobadas para el diseño físico del domicilio geográfico de UC-15 — Completar perfil clínico inicial.
Su finalidad es servir como especificación previa a la implementación de las migrations. No implementa el esquema, no define el importador y no modifica el flujo funcional actual.

## 2. Alcance y estado

- Estado: diseño físico aprobado, todavía no implementado.
- Rama de trabajo: `feature/uc15-domicilio-geografia`.
- Fecha de congelación documental: 29/09/2026.
- Alcance: tablas geográficas, campos estructurados de domicilio en `pacientes`, restricciones, índices, nullability, transición de `direccion` histórica, orden de migrations y portabilidad del DDL.
  Las decisiones de este documento son la base obligatoria del siguiente subpaso de implementación. No deben reinterpretarse durante la creación de las migrations salvo que aparezca una contradicción nueva y comprobable en una fuente vigente.

## 3. Fuentes de verdad utilizadas

Se utilizaron las siguientes fuentes vigentes:

1. `docs/_codex_sources/README_Codex_sources.md`.
2. `docs/_codex_sources/Diccionario_de_Datos_vigente.txt`.
3. `docs/_codex_sources/UC-15_Completar_perfil_clinico_inicial.txt`.
4. `docs/_codex_sources/kinesiosoft_schema_actual.sql`.
5. Migrations actuales, únicamente para verificar convenciones técnicas del proyecto.
   `docs/_codex_sources/` es una carpeta auxiliar local y no versionada. Sus archivos no forman parte de este documento ni deben modificarse durante la implementación.
   La jerarquía aplicada es:

- persistencia objetivo: Diccionario de Datos vigente;
- comportamiento funcional: UC-15 vigente;
- estado físico previo: migrations actuales y `kinesiosoft_schema_actual.sql`;
- procedencia de los catálogos: datasets Georef locales aprobados.

## 4. Mapeo Georef aprobado

El mapeo congelado es:

```text
provincias
    ← provincias.csv
localidades
    ← localidades_censales.csv
calles
    ← calles.csv
```

`localidades.csv` se conserva únicamente como material auxiliar de análisis. No es la fuente primaria de la tabla `localidades`.
Durante la futura importación:

```text
localidades_censales.csv.provincia_id
    → provincias.georef_id
    → localidades.provincia_id
```

```text
calles.csv.localidad_censal_id
    → localidades.georef_id
    → calles.localidad_id
```

El sistema operará contra catálogos locales y no dependerá de Georef en runtime.

## 5. Modelo relacional geográfico

Se conserva el siguiente modelo:

```text
PROVINCIAS 1 ── 0..N LOCALIDADES
LOCALIDADES 1 ── 0..N CALLES
LOCALIDADES 1 ── 0..N PACIENTES
```

No se crearán tablas adicionales de:

- `localidades_censales`;
- departamentos;
- barrios.
  `jurisdicciones` pertenece al dominio de matriculación profesional y no se reutilizará para el domicilio.
  En CABA, `localidades` representará localidades censales/comunas. No se incorporará el nivel barrio en este incremento.

## 6. Diseño físico de provincias

| Columna      | Laravel Schema Builder          | SQL MySQL/MariaDB esperado       | Nullable | Clave/índice         | Decisión                       |
| ------------ | ------------------------------- | -------------------------------- | -------- | -------------------- | ------------------------------ |
| `id`         | `$table->id()`                  | `BIGINT UNSIGNED AUTO_INCREMENT` | No       | PK                   | Identificador interno.         |
| `georef_id`  | `$table->char('georef_id', 2)`  | `CHAR(2)`                        | No       | UNIQUE               | Identificador oficial textual. |
| `nombre`     | `$table->string('nombre', 100)` | `VARCHAR(100)`                   | No       | Sin índice adicional | Nombre de la provincia.        |
| `created_at` | `$table->timestamps()`          | `TIMESTAMP NULL`                 | Sí       | —                    | Timestamp Laravel.             |
| `updated_at` | `$table->timestamps()`          | `TIMESTAMP NULL`                 | Sí       | —                    | Timestamp Laravel.             |

Restricciones aprobadas:

- `PRIMARY KEY (id)`.
- `UNIQUE (georef_id)`.
- No agregar `UNIQUE (nombre)`.
- No agregar un índice ordinario adicional sobre `nombre`; el catálogo contiene sólo 24 provincias.

## 7. Diseño físico de localidades

| Columna               | Laravel Schema Builder                       | SQL MySQL/MariaDB esperado       | Nullable | Clave/índice                             | Decisión                            |
| --------------------- | -------------------------------------------- | -------------------------------- | -------- | ---------------------------------------- | ----------------------------------- |
| `id`                  | `$table->id()`                               | `BIGINT UNSIGNED AUTO_INCREMENT` | No       | PK                                       | Identificador interno.              |
| `georef_id`           | `$table->char('georef_id', 8)`               | `CHAR(8)`                        | No       | UNIQUE                                   | ID de `localidades_censales.csv`.   |
| `provincia_id`        | `$table->foreignId('provincia_id')`          | `BIGINT UNSIGNED`                | No       | FK; primera columna del índice compuesto | Provincia obligatoria.              |
| `nombre`              | `$table->string('nombre', 150)`              | `VARCHAR(150)`                   | No       | Segunda columna del índice compuesto     | Nombre de la localidad censal.      |
| `departamento_nombre` | `$table->string('departamento_nombre', 100)` | `VARCHAR(100)`                   | No       | Sin índice                               | Dato descriptivo de desambiguación. |
| `created_at`          | `$table->timestamps()`                       | `TIMESTAMP NULL`                 | Sí       | —                                        | Timestamp Laravel.                  |
| `updated_at`          | `$table->timestamps()`                       | `TIMESTAMP NULL`                 | Sí       | —                                        | Timestamp Laravel.                  |

FK aprobada:

```text
localidades.provincia_id → provincias.id
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

Índice aprobado:

```text
INDEX (provincia_id, nombre)
```

El orden responde al flujo Provincia → Ciudad y permite filtrar primero por `provincia_id` y luego buscar u ordenar por `nombre`.
No se agregará un índice individual redundante sobre `provincia_id` si el índice compuesto ya funciona como índice de soporte de la FK. La futura migration deberá declarar las estructuras en un orden que evite que MySQL/MariaDB cree un índice simple adicional innecesario.
No debe existir:

```text
UNIQUE (provincia_id, nombre)
```

`departamento_nombre`:

- es descriptivo;
- se utiliza para diferenciar localidades homónimas;
- no es una FK;
- no requiere índice;
- no genera una tabla de departamentos.

## 8. Diseño físico de calles

| Columna        | Laravel Schema Builder              | SQL MySQL/MariaDB esperado       | Nullable | Clave/índice                             | Decisión                       |
| -------------- | ----------------------------------- | -------------------------------- | -------- | ---------------------------------------- | ------------------------------ |
| `id`           | `$table->id()`                      | `BIGINT UNSIGNED AUTO_INCREMENT` | No       | PK                                       | Identificador interno.         |
| `georef_id`    | `$table->char('georef_id', 15)`     | `CHAR(15)`                       | No       | UNIQUE                                   | Identificador oficial textual. |
| `localidad_id` | `$table->foreignId('localidad_id')` | `BIGINT UNSIGNED`                | No       | FK; primera columna del índice compuesto | Localidad censal obligatoria.  |
| `nombre`       | `$table->string('nombre', 150)`     | `VARCHAR(150)`                   | No       | Segunda columna del índice compuesto     | Nombre oficial de la vía.      |
| `created_at`   | `$table->timestamps()`              | `TIMESTAMP NULL`                 | Sí       | —                                        | Timestamp Laravel.             |
| `updated_at`   | `$table->timestamps()`              | `TIMESTAMP NULL`                 | Sí       | —                                        | Timestamp Laravel.             |

FK aprobada:

```text
calles.localidad_id → localidades.id
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

Índice aprobado:

```text
INDEX (localidad_id, nombre)
```

No se agregará un índice individual redundante sobre `localidad_id` si el índice compuesto ya funciona como índice de soporte de la FK.
No debe existir:

```text
UNIQUE (localidad_id, nombre)
```

El catálogo puede contener varios registros oficiales con el mismo nombre dentro de una localidad. La deduplicación visual de sugerencias no altera su identidad persistida.
No se utilizará `FULLTEXT` en este diseño. Los aproximadamente 333.800 registros de `calles.csv` no se cargarán mediante una migration.

## 9. Diseño físico del domicilio en pacientes

Los cinco campos nuevos nacen físicamente nullable:
| Columna | Laravel Schema Builder | SQL MySQL/MariaDB esperado | Nullable | Clave/índice | Decisión |
|---|---|---|---|---|---|
| `localidad_id` | `$table->foreignId('localidad_id')->nullable()` | `BIGINT UNSIGNED NULL` | Sí | FK + índice | Localidad censal seleccionada. |
| `calle` | `$table->string('calle', 150)->nullable()` | `VARCHAR(150) NULL` | Sí | Sin índice | Calle sugerida o manual. |
| `altura_domicilio` | `$table->string('altura_domicilio', 30)->nullable()` | `VARCHAR(30) NULL` | Sí | Sin índice | Valor alfanumérico; no es una magnitud. |
| `piso_departamento` | `$table->string('piso_departamento', 50)->nullable()` | `VARCHAR(50) NULL` | Sí | Sin índice | Dato opcional. |
| `referencia_domicilio` | `$table->string('referencia_domicilio', 500)->nullable()` | `VARCHAR(500) NULL` | Sí | Sin índice | Referencia opcional. |
FK aprobada:

```text
pacientes.localidad_id → localidades.id
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

`pacientes.localidad_id` tendrá el índice necesario para su FK.
No se crearán:

- `pacientes.provincia_id`;
- `pacientes.calle_id`.
  La Provincia se obtiene a través de `pacientes.localidad_id → localidades.provincia_id`. El catálogo `calles` sólo apoya la búsqueda; el valor elegido o ingresado manualmente se persiste en `pacientes.calle`.
  `altura_domicilio` es `VARCHAR(30)`, no `INTEGER`. Debe admitir valores como:

```text
1234
1234 bis
1234 A
S/N
KM 12,5
```

La altura es un componente identificador del domicilio y no una magnitud matemática.

## 10. Política de nullability y perfil incompleto

> `localidad_id`, `calle`, `altura_domicilio`, `piso_departamento` y `referencia_domicilio` se crean físicamente nullable.
> Además, mientras el ciclo de vida permita persistir legítimamente un paciente con el perfil todavía incompleto, estos campos deben permanecer físicamente nullable:

- `localidad_id`;
- `calle`;
- `altura_domicilio`.
  Su obligatoriedad pertenece a UC-15, a las validaciones de aplicación y a la regla de perfil completo. No debe expresarse actualmente mediante `NOT NULL` global en `pacientes`.
  Para completar UC-15 son funcionalmente obligatorios:
- `localidad_id`;
- `calle`;
- `altura_domicilio`.
  Son funcionalmente opcionales:
- `piso_departamento`;
- `referencia_domicilio`.
  Que una columna sea nullable durante el ciclo de vida del paciente no significa que sea opcional al completar el perfil.
  No se inferirá el estado Provisorio a partir de `localidad_id IS NULL` ni de la ausencia de otros componentes del domicilio.

## 11. Tratamiento de direccion histórica

La columna existente debe quedar físicamente intacta:

```text
pacientes.direccion VARCHAR(255) NULL
```

La migration inicial del domicilio estructurado no debe:

- cambiar su tipo;
- cambiar su longitud;
- cambiar su nullability;
- agregar un valor por defecto;
- renombrarla;
- parsearla;
- copiarla;
- sobrescribirla;
- eliminarla.
  No se realizará backfill desde `direccion` hacia los campos estructurados y no se fabricará información geográfica.
  Después de la migration, un paciente histórico puede quedar legítimamente así:

```text
direccion = valor histórico existente
localidad_id = NULL
calle = NULL
altura_domicilio = NULL
piso_departamento = NULL
referencia_domicilio = NULL
```

El código funcional futuro dejará de utilizar `direccion` como domicilio objetivo, pero su eventual eliminación requiere una decisión posterior e independiente de conservación histórica.

## 12. Identificadores y longitudes

Los identificadores Georef se persistirán como texto para conservar ceros iniciales:
| Campo | Tipo aprobado |
|---|---|
| `provincias.georef_id` | `CHAR(2)` |
| `localidades.georef_id` | `CHAR(8)` |
| `calles.georef_id` | `CHAR(15)` |
No se convertirán a `INTEGER`.
Longitudes funcionales aprobadas:
| Campo | Tipo aprobado |
|---|---|
| `provincias.nombre` | `VARCHAR(100)` |
| `localidades.nombre` | `VARCHAR(150)` |
| `localidades.departamento_nombre` | `VARCHAR(100)` |
| `calles.nombre` | `VARCHAR(150)` |
| `pacientes.calle` | `VARCHAR(150)` |
| `pacientes.altura_domicilio` | `VARCHAR(30)` |
| `pacientes.piso_departamento` | `VARCHAR(50)` |
| `pacientes.referencia_domicilio` | `VARCHAR(500)` |
Estas longitudes quedan congeladas para la implementación inicial.

## 13. FKs y políticas referenciales

Las tres FKs nuevas son:

```text
localidades.provincia_id → provincias.id
calles.localidad_id → localidades.id
pacientes.localidad_id → localidades.id
```

Todas utilizarán:

```text
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

No se utilizarán cascadas de eliminación. Una eliminación de catálogo no debe borrar calles, referencias geográficas ni asociaciones de pacientes.
Las FKs utilizan IDs internos de KinesioSoft. Los `georef_id` se usan para resolver la correspondencia durante la importación, no como FK entre tablas internas.

## 14. Índices y rendimiento

Índices aprobados:

### `provincias`

- PK `id`.
- UNIQUE `georef_id`.

### `localidades`

- PK `id`.
- UNIQUE `georef_id`.
- INDEX `(provincia_id, nombre)`.

### `calles`

- PK `id`.
- UNIQUE `georef_id`.
- INDEX `(localidad_id, nombre)`.

### `pacientes`

- Índice de `localidad_id` necesario para su FK.
  No se agregarán índices adicionales “por si acaso”. En particular:
- no se indexará `provincias.nombre`;
- no se indexará `localidades.departamento_nombre`;
- no se duplicará un índice simple de FK cuando el compuesto ya comienza por la misma columna;
- no se indexarán `pacientes.calle`, `altura_domicilio`, `piso_departamento` ni `referencia_domicilio`;
- no se utilizará `FULLTEXT`.
  El índice `calles(localidad_id, nombre)` queda aprobado como soporte físico del autocomplete. La semántica exacta de búsqueda permanece diferida según la sección 18.

## 15. Orden aprobado de migrations

El orden congelado es:

1. `create_provincias_table`.
2. `create_localidades_table`.
3. `create_calles_table`.
4. `add_structured_address_fields_to_pacientes_table`.
   Este orden respeta todas las dependencias de FK y permite que el rollback se ejecute en orden inverso.
   La importación de datos Georef será una operación posterior y separada. Los CSV nunca se cargarán dentro de migrations.
   Las migrations deberán poder ejecutarse con los catálogos vacíos. Los pacientes históricos no bloquearán el cambio porque los nuevos campos nacen nullable.

## 16. Compatibilidad MySQL / SQLite

El proyecto utiliza:

- MySQL/MariaDB en desarrollo;
- SQLite `:memory:` en PHPUnit.
  Las migrations deberán utilizar Laravel Schema Builder.
  Se evitarán:
- DDL crudo específico de MySQL;
- `ENUM`;
- `FULLTEXT` en este incremento;
- collations específicas por tabla;
- `after()` cuando sólo aporte orden cosmético.
  Consideraciones de portabilidad:
- SQLite no distingue `UNSIGNED` del mismo modo que MySQL/MariaDB.
- SQLite no reproduce necesariamente la validación física de longitud de `CHAR(n)` y `VARCHAR(n)`.
- Las longitudes también deberán validarse en aplicación.
- Las FKs permanecerán habilitadas en los tests SQLite.
- Los tests no deberán asumir que SQLite reproduce exactamente la semántica case/accent-insensitive de `utf8mb4_unicode_ci`.
- Las políticas `ON DELETE RESTRICT` y `ON UPDATE RESTRICT` se expresarán mediante Schema Builder.

## 17. Seguridad de la transición

La transición física deberá cumplir:

- conservar todos los pacientes históricos;
- no tocar ni eliminar `direccion`;
- no parsear ni migrar automáticamente su contenido;
- no agregar defaults artificiales a los nuevos campos;
- agregar los cinco campos de pacientes como nullable;
- crear las tablas en orden de dependencia;
- aplicar FKs sólo sobre IDs internos;
- permitir que las migrations finalicen con catálogos vacíos;
- no importar CSV dentro de migrations;
- no habilitar el nuevo flujo antes de que los catálogos hayan sido importados y validados;
- no depender de Georef en runtime;
- no utilizar cascadas destructivas.
  La carga de aproximadamente 333.800 calles será una operación posterior, controlada y distinta del cambio de esquema.

## 18. Decisiones diferidas

Las siguientes decisiones están diferidas y no forman parte del diseño físico congelado:

- búsqueda por prefijo, subcadena o estrategia mixta en el autocomplete;
- comportamiento exacto con acentos y diferencias entre mayúsculas y minúsculas;
- diseño del importador y del comando Artisan;
- momento técnico exacto de habilitación del flujo después de importar y validar los catálogos.
  Estas decisiones no bloquean la implementación de las migrations aprobadas. El índice `calles(localidad_id, nombre)` ya queda definido, pero este documento no diseña endpoints ni congela la consulta funcional definitiva.

## 19. Fuera de alcance

Este documento no diseña ni implementa:

- importador;
- comando Artisan;
- endpoints;
- Controllers;
- Requests;
- Actions;
- Policies;
- Blade;
- Alpine;
- tests de implementación;
- auditoría de cambio de domicilio;
- edición posterior del domicilio;
- estado Provisorio;
- barrios;
- mapas;
- coordenadas;
- geocodificación.

## 20. Checklist previo a implementación

- [x] Fuentes Georef aprobadas.
- [x] Diseño físico aprobado.
- [x] Identificadores y longitudes aprobados.
- [x] FKs y políticas referenciales aprobadas.
- [x] Índices aprobados.
- [x] Estrategia de `direccion` legacy aprobada.
- [x] Nullability de `pacientes` aprobada.
- [x] Orden de migrations aprobado.
- [x] Criterios de portabilidad MySQL/SQLite aprobados.
- [x] Decisiones diferidas identificadas como no bloqueantes.
- [ ] Migrations implementadas.
- [ ] Catálogos importados.
- [ ] Catálogos validados después de la importación.
- [ ] Flujo UC-15 adaptado al domicilio estructurado.

## 21. Próximo subpaso autorizado

Implementar las cuatro migrations del esquema aprobado, sin importar todavía datos Georef ni modificar el flujo funcional de UC-15.
