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

Rama esperada para UC–XX Gestionar catálogos maestros:
`feature/catalogos-maestros`.

Baseline previo al inicio de este incremento:
- `php artisan test`: 552 tests passed, 2986 assertions.
- Working tree limpio antes de comenzar el Paso 8.

Si la rama activa o el estado Git no coinciden con lo esperado, detenerse e informarlo antes de modificar archivos.

## Fuente de verdad para UC–XX Gestionar catálogos maestros

La planificación ejecutable congelada está en:

`docs/implementacion/paso-7-planificacion-catalogos-maestros.md`

Jerarquía de decisión:

1. Diccionario de Datos Definitivo / Modelo Relacional 3FN versión (4): persistencia, tablas, atributos, claves, relaciones y dominios.
2. UC–XX Gestionar catálogos maestros congelado: actores, operaciones, reglas de negocio, restricciones, auditoría y comportamiento.
3. Prototipo final congelado del Paso 6: presentación, UX, columnas, responsive y flujo visual.
4. Código real existente de KinesioSoft: convenciones técnicas y patrones Laravel ya utilizados.

La planificación Markdown anterior integra las decisiones congeladas de esas fuentes y es la guía operativa para Codex.
No reabrir decisiones de dominio ya congeladas durante la programación.

## Forma obligatoria de trabajo

Trabajar únicamente en el subpaso o bloque de subpasos que el usuario solicite de forma explícita.

Antes de modificar:
1. Leer este `AGENTS.md`.
2. Leer el subpaso correspondiente de `docs/implementacion/paso-7-planificacion-catalogos-maestros.md`.
3. Inspeccionar primero todos los archivos indicados en ese subpaso.
4. Comparar el plan con la implementación real.
5. Informar cualquier discrepancia relevante antes de inventar una solución no prevista.

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

Para Catálogos Maestros, mantener el patrón:

`Controller -> Form Request -> Action -> DB::transaction() -> Model -> AuditoriaService`

Convenciones:
- Controllers propios por catálogo.
- Form Requests propios por catálogo en `app/Http/Requests/`.
- Actions en `app/Actions/Catalogos/`.
- Policies propias por catálogo.
- Models propios por entidad.
- Reutilizar `AuditoriaService`.
- Utilizar `DB::transaction()` para escrituras que deban ser atómicas.
- Utilizar `lockForUpdate()` cuando el flujo de estado/dependencias lo requiera.
- Reutilizar el sistema global existente de SweetAlert2 y `.js-confirm-action`.
- Reutilizar Alpine/Vite ya instalados; no agregar CDN duplicados.
- Reutilizar componentes Blade sólo cuando la reutilización esté demostrada.

No crear:
- Un `CatalogController` CRUD genérico.
- Un Model base genérico de catálogo.
- Repositorios artificiales sin necesidad demostrada.
- Una segunda infraestructura de auditoría.
- Una segunda infraestructura de confirmaciones.
- Abstracciones que mezclen reglas de negocio diferentes sólo porque las pantallas se parecen.

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

Auditar:
- Alta.
- Modificación real.
- Inactivación real.
- Reactivación real.

No auditar:
- `index`.
- `create`.
- `show`.
- `edit`.
- Updates sin cambios semánticos reales.
- Operaciones idempotentes sin cambio.

Módulo de auditoría para estos eventos: `Catálogos`.

Nunca almacenar contraseñas, binarios u otros datos sensibles en snapshots de auditoría.

## Base de datos y migraciones

- Agregar migraciones nuevas; no reescribir migraciones históricas ya ejecutadas.
- Preservar claves foráneas y conservación histórica según la planificación.
- No usar cascadas destructivas incompatibles con la conservación histórica.
- No usar native `ENUM` de MySQL para estos dominios; mantener compatibilidad entre MySQL y SQLite de PHPUnit.
- No ejecutar comandos destructivos sobre la BD de desarrollo.

PROHIBIDO sin instrucción explícita del usuario:
- `php artisan migrate:fresh`
- `php artisan migrate:reset`
- `php artisan db:wipe`
- equivalentes destructivos

Si una tarea requiere una migración real en MySQL, detenerse y pedir confirmación antes de ejecutarla.
`migrate --pretend` sí puede utilizarse cuando el plan lo indique.

## Archivos y configuración protegidos

No modificar salvo instrucción explícita:
- `.env`
- `vendor/`
- `node_modules/`
- dumps SQL
- documentos UP congelados
- Modelo Relacional congelado
- Diccionario de Datos congelado

No incluir en Git:
- cachés
- logs
- archivos temporales
- uploads de pruebas
- credenciales
- `.env`

## Pruebas

El proyecto usa PHPUnit, NO Pest.

Estrategia:
1. Ejecutar primero tests específicos del cambio.
2. Corregir fallos causados por el subpaso.
3. Ejecutar la regresión indicada por la planificación.
4. No avanzar con tests rojos.
5. Ejecutar `php artisan test` completo en las puertas de regresión definidas por el plan.

Baseline previo al Paso 8:
`552 passed (2986 assertions)`.

No modificar tests para “hacerlos pasar” si eso debilita o cambia una regla congelada.
Cuando un test deba actualizarse por un cambio funcional planificado, mantener o aumentar la cobertura semántica.

## Frontend

- Blade + Tailwind + Alpine + Vite.
- SweetAlert2 ya está disponible globalmente.
- No agregar imports/CDN duplicados.
- Mantener desktop y mobile conforme al prototipo congelado.
- Las vistas no deben contener reglas de dominio que sólo existan en frontend.
- Toda regla crítica debe validarse también en backend.
- Evitar JavaScript inline cuando el proyecto ya usa módulos reutilizables.

Para formularios con archivos:
- usar `multipart/form-data`.
- enviar `UploadedFile` real.
- no serializar archivos como strings/Base64 para suplir el transporte HTTP.
- recordar que un input file no puede repoblarse con `old()` tras una validación fallida.

## Git

Antes de un bloque:
- comprobar rama cuando sea relevante.
- comprobar `git status`.

Durante el trabajo:
- no hacer commits intermedios salvo instrucción explícita.
- no cambiar de rama por iniciativa propia.
- no hacer push.
- no reescribir historial.
- no usar `reset --hard`, `clean -fd` u otros comandos destructivos.

Antes de cierre:
- mostrar `git status --short`.
- mostrar `git diff --stat`.
- revisar el diff requerido por el subpaso.

El commit final del módulo corresponde al subpaso `19.42` y sólo se realiza con autorización explícita.

## Seguridad operacional

Si una acción:
- excede el subpaso solicitado,
- puede destruir datos,
- requiere credenciales,
- modifica configuración de entorno,
- cambia arquitectura congelada,
- o afecta módulos no incluidos,

detenerse y solicitar autorización antes de continuar.

No interpretar una aprobación para ejecutar un comando como autorización general para otros comandos posteriores.

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
