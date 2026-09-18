KINESIOSOFT

# Paso 7 — Planificación exhaustiva de implementación

UC–XX Gestionar catálogos maestros

VERSIÓN DEFINITIVA CONGELADA

Tercera pasada operativa para ejecución controlada con Codex  
Proyecto Final — Analista de Sistemas  
Fecha de congelación: 17/09/2026

| Estado | CONGELADO — fuente de verdad para el Paso 8 |
| --- | --- |
| Alcance | Implementación de los 10 catálogos maestros de KinesioSoft |
| Método de ejecución | Subpasos secuenciales; Codex no avanza sin tests verdes |
| Fuentes de verdad | Diccionario v4 + UC–XX + prototipo final + ZIP/SQL actual |

# 1. Fuentes congeladas y jerarquía de decisión

- Diccionario de Datos Definitivo (Modelo Relacional 3FN - KinesioSoft), versión (4): gobierna tablas, atributos, claves, relaciones y dominios persistentes.

- UC–XX Gestionar catálogos maestros congelado: gobierna actores, operaciones, reglas de negocio, baja lógica, reactivación, auditoría, excepciones y postcondiciones.

- Prototipo final del Paso 6: gobierna organización visual, columnas, acciones por estado, responsive y tratamiento de Ejercicios.

- ZIP KinesioSoft actualizado + dump kinesiosoft_db: gobiernan las convenciones reales de arquitectura y el estado actual del código/BD.

```text
Prioridad ante contradicciones:
1. Persistencia / esquema -> Diccionario de Datos v4
2. Comportamiento / negocio -> UC–XX congelado
3. Presentación / UX -> prototipo final congelado
4. Convenciones técnicas -> proyecto KinesioSoft actual
```

# 2. Decisiones de dominio que NO se vuelven a discutir

- Los catálogos se agrupan en 4 profesionales y 6 clínicos.

- Administrador y Superadministrador son los únicos roles autorizados.

- No existe borrado físico como operación ordinaria del módulo.

- Registrar crea el ítem Activo por defecto.

- Editar no cambia el estado lógico.

- Activo -> Consultar / Editar / Inactivar. Inactivo -> Consultar / Editar / Reactivar.

- Consulta no genera auditoría; alta, modificación, inactivación y reactivación sí.

- Las escrituras relevantes se ejecutan transaccionalmente y deben hacer rollback ante error.

- No se crea un Controller genérico de catálogos: cada catálogo conserva Controller, Requests, Actions, Policy y reglas propias.

- Diagnósticos usa el nombre técnico definitivo diagnosticos_clinicos / DiagnosticoClinico.

- Ejercicios domiciliarios NO contiene grupo_muscular_zona.

- Recursos de ejercicio soporta origen LOCAL/EXTERNO con ruta_storage/url_externa mutuamente excluyentes.

- Categorías clínicas usa tipos ANATOMICA, FUNCIONAL, TERAPEUTICA y EQUIPAMIENTO; mismo tipo padre/hija; sin ciclos.

- Ejercicio activo exige al menos una categoría ANATOMICA activa y prohíbe EQUIPAMIENTO.

- Tipo de equipo requiere categoría activa de tipo EQUIPAMIENTO para crear, cambiar asociación o reactivar.

# 3. Protocolo global de ejecución con Codex

- Entregar a Codex un subpaso o pequeño bloque por vez. No ejecutar 19.2–19.42 de una sola vez.

- Codex debe inspeccionar primero los archivos indicados y explicar cualquier discrepancia antes de modificar.

- Después de cada subpaso debe informar archivos creados/modificados, decisiones aplicadas y salida exacta de los tests.

- No avanzar si falla un test del bloque actual o una regresión causada por el cambio.

- Las migraciones nuevas se agregan; no se reescriben migraciones históricas ya ejecutadas.

- No modificar .env, vendor/, node_modules/, dump SQL ni documentos congelados salvo instrucción expresa.

- No usar migrate:fresh, db:wipe, migrate:reset ni equivalentes destructivos contra la BD de desarrollo.

- PHPUnit usa su entorno de testing; los tests no deben depender de los datos reales de MySQL.

- No introducir destroy, switch de activo dentro de create/edit, un segundo sistema de confirmaciones SweetAlert ni un Controller CRUD genérico.

- No modificar UserController::toggleActivo, UserPolicy::toggleActivo ni MisMatriculasProfesionalesController::toggleActiva como parte de UC–XX.

- No hacer commit final antes de 19.42.

# 4. Índice operativo definitivo 19.2–19.42

| Paso | Subpaso definitivo |
| --- | --- |
| 19.2 | Congelar contrato técnico y fuentes de verdad |
| 19.3 | Confirmar arquitectura y baseline actual |
| 19.4 | Migrar atómicamente el contrato de estado de los 4 catálogos profesionales |
| 19.5 | Jurisdicciones como vertical slice de referencia |
| 19.6 | Extraer infraestructura Blade común desde Jurisdicciones estabilizadas |
| 19.7 | Adaptar Títulos profesionales al patrón definitivo |
| 19.8 | Adaptar Especialidades de kinesiología al patrón definitivo |
| 19.9 | Adaptar Colegios emisores y sus reglas de dependencia |
| 19.10 | Integrar Hub de Catálogos y navegación agrupada |
| 19.11 | Cerrar contratos globales de navegación/confirmación del bloque profesional |
| 19.12 | Pasada responsive de los 4 catálogos profesionales |
| 19.13 | Regresión completa del bloque profesional |
| 19.14 | Suite global baseline antes de crear esquema clínico |
| 19.15 | Crear las 8 tablas del bloque clínico |
| 19.16 | Cerrar FKs y conservación histórica |
| 19.17 | Crear Models y enums clínicos |
| 19.18 | Implementar Diagnósticos clínicos |
| 19.19 | Cerrar PHPUnit de Diagnósticos clínicos |
| 19.20 | Implementar y probar Pruebas funcionales |
| 19.21 | Implementar y probar Técnicas terapéuticas |
| 19.22 | Implementar base de Categorías clínicas |
| 19.23 | Implementar jerarquía, tipos y prevención de ciclos |
| 19.24 | Implementar restricciones de estado y dependencias de Categorías |
| 19.25 | Implementar Tipos de equipo terapéutico |
| 19.26 | Preparar dominio y relaciones de Ejercicios domiciliarios |
| 19.27 | Congelar payload y Requests de Ejercicios |
| 19.28 | Implementar Recursos/Storage y pruebas de filesystem |
| 19.29 | Implementar Actions transaccionales de Ejercicios y ActionTests |
| 19.30 | Implementar Controller, Policy, rutas, UI y JS de Ejercicios |
| 19.31 | Regresión integrada exhaustiva del agregado Ejercicio |
| 19.32 | Auditoría transversal de los 10 catálogos |
| 19.33 | Congelar estrategia de fixtures de tests |
| 19.34 | Congelar estrategia de seeders clínicos |
| 19.35 | Pasada responsive final de los 10 catálogos |
| 19.36 | Actualizar pruebas transversales del sistema |
| 19.37 | Regresión integral de Catálogos |
| 19.38 | Suite completa, Pint y build |
| 19.39 | Prueba funcional manual completa |
| 19.40 | Revisión de rendimiento |
| 19.41 | Revisión Git y limpieza |
| 19.42 | Suite de cierre y commit |

# FASE A — Congelación técnica y estabilización de los 4 catálogos profesionales

## 19.2 — Congelar contrato técnico y fuentes de verdad

Objetivo: Confirmar antes de programar qué documento gobierna cada decisión y qué nombres técnicos definitivos se usarán.

### Inspeccionar antes de modificar

```text
Diccionario de Datos Definitivo ... (4).docx
UC-XX Gestionar catálogos maestros (congelado)
prototipo HTML final del Paso 6
routes/web.php
composer.json
phpunit.xml
resources/js/app.js
resources/views/layouts/app.blade.php
```

### Reglas y decisiones congeladas

- Registrar por escrito la jerarquía: MR v4 -> persistencia; UC -> negocio; prototipo -> UX; proyecto -> convenciones.

- Confirmar las 8 tablas nuevas: diagnosticos_clinicos, pruebas_funcionales, tecnicas_terapeuticas, categorias_clinicas, ejercicios_domiciliarios, ejercicio_categoria, recursos_ejercicio, tipos_equipo_terapeutico.

- Confirmar que no se usará ningún nombre diagnosticos_funcionales en la implementación nueva.

### Comandos / verificaciones

```text
git status --short
git branch --show-current
php artisan route:list --name=catalogos
```

Condición de salida: Codex demuestra que entiende las fuentes, los 10 catálogos y las 8 tablas nuevas sin modificar archivos.

### NO HACER

- Programar o crear archivos en este subpaso.

## 19.3 — Confirmar arquitectura y baseline actual

Objetivo: Reusar el patrón real de KinesioSoft en vez de introducir una arquitectura paralela.

### Inspeccionar antes de modificar

```text
app/Actions/Catalogos/*
app/Http/Controllers/JurisdiccionController.php
app/Http/Controllers/ColegioEmisorController.php
app/Http/Controllers/TituloProfesionalController.php
app/Http/Controllers/EspecialidadKinesiologiaController.php
app/Http/Requests/Store*Catalogo*.php (equivalentes existentes)
app/Policies/JurisdiccionPolicy.php
app/Policies/ColegioEmisorPolicy.php
app/Services/AuditoriaService.php
tests/Feature/Actions/Catalogos/*
tests/Feature/Policies/*
```

### Reglas y decisiones congeladas

- Patrón congelado: Controller -> Form Request -> Action -> DB::transaction() -> Model -> AuditoriaService.

- Requests nuevos permanecen en app/Http/Requests/ para respetar la estructura actual.

- Actions nuevos permanecen en app/Actions/Catalogos/.

- Policies propios por catálogo; no Policy genérica.

### Pruebas obligatorias

- php artisan test tests/Feature/Actions/Catalogos

- php artisan test tests/Feature/Policies

Condición de salida: Baseline de Actions y Policies actual completamente verde.

### NO HACER

- Mover Requests existentes a una subcarpeta nueva.

- Crear repositorios o servicios genéricos sin necesidad demostrada.

## 19.4 — Migrar atómicamente el contrato de estado de los 4 catálogos profesionales

Objetivo: Reemplazar el toggle ciego de estado por operaciones explícitas Inactivar/Reactivar sin dejar un estado intermedio incompatible entre rutas, Controllers, Actions y Policies.

### Inspeccionar antes de modificar

```text
routes/web.php
app/Actions/Catalogos/CambiarEstadoJurisdiccionAction.php
app/Actions/Catalogos/CambiarEstadoColegioEmisorAction.php
app/Actions/Catalogos/CambiarEstadoTituloProfesionalAction.php
app/Actions/Catalogos/CambiarEstadoEspecialidadKinesiologiaAction.php
los 4 Controllers profesionales
las 4 Policies profesionales
tests/Feature/Actions/Catalogos/CambiarEstado*ActionTest.php
los 4 ControllerTest.php
tests/Feature/Policies/*Catalogo*PolicyTest.php
```

### Modificar

```text
routes/web.php
app/Actions/Catalogos/CambiarEstadoJurisdiccionAction.php
app/Actions/Catalogos/CambiarEstadoColegioEmisorAction.php
app/Actions/Catalogos/CambiarEstadoTituloProfesionalAction.php
app/Actions/Catalogos/CambiarEstadoEspecialidadKinesiologiaAction.php
app/Http/Controllers/JurisdiccionController.php
app/Http/Controllers/ColegioEmisorController.php
app/Http/Controllers/TituloProfesionalController.php
app/Http/Controllers/EspecialidadKinesiologiaController.php
app/Policies/JurisdiccionPolicy.php
app/Policies/ColegioEmisorPolicy.php
app/Policies/TituloProfesionalPolicy.php
app/Policies/EspecialidadKinesiologiaPolicy.php
tests relacionados
```

### Reglas y decisiones congeladas

- Mantener el nombre CambiarEstado...Action, pero su contrato recibe el estado objetivo explícito (bool $activoObjetivo).

- La Action debe recargar con lockForUpdate() dentro de DB::transaction().

- Si el estado ya coincide con el objetivo, no invertirlo ni generar una auditoría ficticia.

- Controllers exponen métodos inactivar() y reactivar(); se elimina toggleActivo() sólo en estos 4 catálogos.

- Policies pasan de toggleActivo a view, inactivar y reactivar además de viewAny/create/update.

- Rutas nuevas: PATCH .../{modelo}/inactivar y PATCH .../{modelo}/reactivar.

### Pruebas obligatorias

- Actualizar los 4 CambiarEstado*ActionTest: activo->false, inactivo->true, idempotencia, auditoría sólo si cambia, rollback.

- Actualizar 4 ControllerTests y 4 PolicyTests.

- Ejecutar: php artisan test tests/Feature/Actions/Catalogos

- Ejecutar los 4 ControllerTest y tests/Feature/Policies

Condición de salida: Los cuatro catálogos usan estado objetivo explícito y todas sus pruebas están verdes.

### NO HACER

- Modificar UserController::toggleActivo o UserPolicy::toggleActivo.

- Modificar MisMatriculasProfesionalesController::toggleActiva.

- Aplicar un reemplazo global de la palabra toggleActivo.

## 19.5 — Jurisdicciones como vertical slice de referencia

Objetivo: Implementar en un catálogo simple el patrón completo que luego se replicará: búsqueda, filtro, show, acciones por estado, responsive y regla de dependencia.

### Inspeccionar antes de modificar

```text
app/Http/Controllers/JurisdiccionController.php
app/Models/Jurisdiccion.php
app/Http/Requests/StoreJurisdiccionRequest.php
app/Http/Requests/UpdateJurisdiccionRequest.php
app/Actions/Catalogos/*JurisdiccionAction.php
resources/views/catalogos/jurisdicciones/*
tests/Feature/JurisdiccionControllerTest.php
tests/Feature/Actions/Catalogos/*JurisdiccionActionTest.php
```

### Modificar

```text
app/Http/Controllers/JurisdiccionController.php
app/Actions/Catalogos/CambiarEstadoJurisdiccionAction.php
resources/views/catalogos/jurisdicciones/index.blade.php
tests/Feature/JurisdiccionControllerTest.php
tests/Feature/Actions/Catalogos/CambiarEstadoJurisdiccionActionTest.php
routes/web.php
```

### Crear

```text
resources/views/catalogos/jurisdicciones/show.blade.php
```

### Reglas y decisiones congeladas

- Búsqueda por nombre; filtro estado=todos|activos|inactivos; paginate(10)->withQueryString().

- Listado desktop: Nombre | Estado | Acciones; no mostrar ID/slug como columnas principales.

- Activo: Consultar/Editar/Inactivar. Inactivo: Consultar/Editar/Reactivar.

- Show es sólo lectura y no genera auditoría.

- Inactivar Jurisdicción debe rechazarse si existe al menos un Colegio Emisor activo asociado.

- Conservar reglas actuales de nombre, slug y unicidad.

### Pruebas obligatorias

- Búsqueda, filtros, show activo/inactivo, consulta sin auditoría, acciones por estado.

- Inactivación permitida sin colegios activos.

- Inactivación rechazada con colegio activo, sin cambio de estado y sin auditoría.

- php artisan test tests/Feature/JurisdiccionControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=Jurisdiccion

Condición de salida: Jurisdicciones representa el patrón funcional completo y está verde.

### NO HACER

- Extraer componentes genéricos antes de comprobar este vertical slice.

## 19.6 — Extraer infraestructura Blade común desde Jurisdicciones estabilizadas

Objetivo: Extraer sólo componentes visuales cuya reutilización ya quedó demostrada por el vertical slice.

### Inspeccionar antes de modificar

```text
resources/views/catalogos/jurisdicciones/index.blade.php
resources/views/catalogos/jurisdicciones/show.blade.php
resources/views/layouts/app.blade.php
resources/js/app.js
```

### Modificar

```text
resources/views/catalogos/jurisdicciones/index.blade.php
resources/views/catalogos/jurisdicciones/show.blade.php
```

### Crear

```text
resources/views/components/catalogos/estado-badge.blade.php
resources/views/components/catalogos/empty-state.blade.php
resources/views/components/catalogos/filtros.blade.php
resources/views/components/catalogos/acciones.blade.php (sólo si la firma resulta limpia)
resources/views/catalogos/partials/navegacion.blade.php
```

### Reglas y decisiones congeladas

- Los componentes reciben datos/URLs; no ejecutan reglas de negocio.

- Reusar .js-confirm-action y SweetAlert2 global existente.

- No importar Alpine/SweetAlert por CDN.

- No intentar convertir columnas específicas de cada catálogo en un componente genérico gigantesco.

### Pruebas obligatorias

- npm run build

- php artisan test tests/Feature/JurisdiccionControllerTest.php

- php artisan test tests/Feature/GlobalConfirmationTest.php

Condición de salida: Jurisdicciones sigue verde usando la infraestructura común recién extraída.

## 19.7 — Adaptar Títulos profesionales al patrón definitivo

Objetivo: Aplicar el vertical slice estabilizado a Títulos sin tocar reglas maduras innecesariamente.

### Inspeccionar antes de modificar

```text
app/Http/Controllers/TituloProfesionalController.php
app/Actions/Catalogos/*TituloProfesionalAction.php
app/Http/Requests/StoreTituloProfesionalRequest.php
app/Http/Requests/UpdateTituloProfesionalRequest.php
resources/views/catalogos/titulos/*
tests/Feature/TituloProfesionalControllerTest.php
```

### Modificar

```text
app/Http/Controllers/TituloProfesionalController.php
resources/views/catalogos/titulos/index.blade.php
tests/Feature/TituloProfesionalControllerTest.php
```

### Crear

```text
resources/views/catalogos/titulos/show.blade.php
```

### Reglas y decisiones congeladas

- Búsqueda por nombre; filtro por estado; withQueryString().

- Listado: Nombre | Estado | Acciones.

- Show no audita.

- Conservar slug, normalización, unicidad y Actions actuales.

### Pruebas obligatorias

- php artisan test tests/Feature/TituloProfesionalControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=TituloProfesional

- php artisan test tests/Feature/Policies/TituloProfesionalPolicyTest.php

Condición de salida: Títulos cumple el patrón sin regresión de sus reglas actuales.

## 19.8 — Adaptar Especialidades de kinesiología al patrón definitivo

Objetivo: Aplicar búsqueda, filtro, show, acciones por estado y responsive al catálogo existente.

### Inspeccionar antes de modificar

```text
app/Http/Controllers/EspecialidadKinesiologiaController.php
app/Actions/Catalogos/*EspecialidadKinesiologiaAction.php
app/Http/Requests/StoreEspecialidadKinesiologiaRequest.php
app/Http/Requests/UpdateEspecialidadKinesiologiaRequest.php
resources/views/catalogos/especialidades/*
tests/Feature/EspecialidadKinesiologiaControllerTest.php
```

### Modificar

```text
app/Http/Controllers/EspecialidadKinesiologiaController.php
resources/views/catalogos/especialidades/index.blade.php
tests/Feature/EspecialidadKinesiologiaControllerTest.php
```

### Crear

```text
resources/views/catalogos/especialidades/show.blade.php
```

### Reglas y decisiones congeladas

- Búsqueda por nombre; filtro por estado; withQueryString().

- Listado: Nombre | Estado | Acciones.

- Conservar slug/unicidad y comportamiento actual.

### Pruebas obligatorias

- php artisan test tests/Feature/EspecialidadKinesiologiaControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=EspecialidadKinesiologia

- php artisan test tests/Feature/Policies/EspecialidadKinesiologiaPolicyTest.php

Condición de salida: Especialidades queda alineado al patrón definitivo y verde.

## 19.9 — Adaptar Colegios emisores y sus reglas de dependencia

Objetivo: Cerrar el catálogo profesional más relacional, preservando asociaciones históricas y validando la reactivación.

### Inspeccionar antes de modificar

```text
app/Http/Controllers/ColegioEmisorController.php
app/Actions/Catalogos/*ColegioEmisorAction.php
app/Http/Requests/StoreColegioEmisorRequest.php
app/Http/Requests/UpdateColegioEmisorRequest.php
resources/views/catalogos/colegios-emisores/*
tests/Feature/ColegioEmisorControllerTest.php
tests/Feature/Actions/Catalogos/*ColegioEmisorActionTest.php
```

### Modificar

```text
app/Http/Controllers/ColegioEmisorController.php
app/Actions/Catalogos/CambiarEstadoColegioEmisorAction.php
resources/views/catalogos/colegios-emisores/index.blade.php
tests relacionados
```

### Crear

```text
resources/views/catalogos/colegios-emisores/show.blade.php
app/Exceptions/Catalogos/NoSePuedeReactivarColegioEmisorException.php (sólo si encaja con el patrón de excepciones existente)
```

### Reglas y decisiones congeladas

- Búsqueda por nombre/sigla; filtro por estado; withQueryString().

- Listado: Nombre | Sigla | Jurisdicción | Estado | Acciones.

- Alta: sólo Jurisdicción activa.

- Edit: mostrar/conservar la jurisdicción actual aunque esté inactiva; alternativas nuevas sólo activas.

- Reactivar Colegio exige Jurisdicción activa; si no, rechazo sin cambio ni auditoría.

### Pruebas obligatorias

- Reactivación válida con jurisdicción activa.

- Reactivación rechazada con jurisdicción inactiva, rollback y sin auditoría.

- php artisan test tests/Feature/ColegioEmisorControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=ColegioEmisor

- php artisan test tests/Feature/Policies/ColegioEmisorPolicyTest.php

Condición de salida: Colegios queda funcionalmente completo y todas sus dependencias se preservan.

## 19.10 — Integrar Hub de Catálogos y navegación agrupada

Objetivo: Actualizar el punto de entrada del módulo para reflejar los 10 catálogos sin introducir enlaces rotos durante la implementación incremental.

### Inspeccionar antes de modificar

```text
app/Http/Controllers/CatalogoController.php
resources/views/catalogos/index.blade.php
resources/views/layouts/navigation.blade.php
resources/views/catalogos/partials/navegacion.blade.php
tests/Feature/NavigationTest.php
```

### Modificar

```text
resources/views/catalogos/index.blade.php
resources/views/catalogos/partials/navegacion.blade.php
tests/Feature/NavigationTest.php
```

### Crear

```text
tests/Feature/CatalogoControllerTest.php (si no existe)
```

### Reglas y decisiones congeladas

- Mostrar grupos Profesionales (4) y Clínicos (6).

- Durante el desarrollo, una card clínica sin ruta todavía implementada debe mostrarse temporalmente no navegable; no usar href="#" como falso enlace.

- A medida que cada CRUD clínico agregue su ruta, habilitar su card.

- Al cierre 19.37 todas las cards deben ser navegables.

### Pruebas obligatorias

- Admin y Superadmin ven el hub.

- Paciente/Kinesiólogo no acceden.

- Aparecen los 10 nombres y ambos grupos.

- php artisan test tests/Feature/CatalogoControllerTest.php

- php artisan test tests/Feature/NavigationTest.php

Condición de salida: Hub actualizado sin rutas rotas ni pérdida de autorización.

## 19.11 — Cerrar contratos globales de navegación y confirmación del bloque profesional

Objetivo: Comprobar que la nueva UI profesional sigue usando la infraestructura global existente y no duplica comportamientos.

### Inspeccionar antes de modificar

```text
resources/views/layouts/app.blade.php
resources/js/app.js
tests/Feature/GlobalConfirmationTest.php
tests/Feature/GlobalFlashMessageTest.php
tests/Feature/NavigationTest.php
```

### Reglas y decisiones congeladas

- Todas las inactivaciones/reactivaciones usan .js-confirm-action o el patrón global equivalente ya existente.

- Los mensajes flash success/error/warning/info siguen centralizados.

- No introducir un segundo manejador SweetAlert específico de catálogos.

### Pruebas obligatorias

- npm run build

- php artisan test tests/Feature/GlobalConfirmationTest.php

- php artisan test tests/Feature/GlobalFlashMessageTest.php

- php artisan test tests/Feature/NavigationTest.php

Condición de salida: Contratos globales de UI permanecen verdes.

## 19.12 — Pasada responsive de los 4 catálogos profesionales

Objetivo: Terminar el comportamiento desktop/mobile de los catálogos existentes antes de replicar el patrón en seis módulos nuevos.

### Inspeccionar antes de modificar

```text
resources/views/catalogos/jurisdicciones/index.blade.php
resources/views/catalogos/titulos/index.blade.php
resources/views/catalogos/especialidades/index.blade.php
resources/views/catalogos/colegios-emisores/index.blade.php
prototipo final Paso 6
```

### Modificar

```text
los 4 index.blade.php profesionales
componentes visuales compartidos si hace falta
```

### Reglas y decisiones congeladas

- Desktop usa las columnas congeladas.

- Mobile usa cards compactas; Colegio muestra Jurisdicción como contexto.

- Consultar/Editar/Inactivar/Reactivar permanecen disponibles según la matriz de estado.

### Pruebas obligatorias

- npm run build

- Ejecutar los 4 ControllerTest profesionales.

Condición de salida: Los 4 catálogos profesionales funcionan y son legibles en desktop/mobile.

## 19.13 — Regresión completa del bloque profesional

Objetivo: Cerrar formalmente todo lo tocado antes de crear tablas clínicas.

### Pruebas obligatorias

- php artisan test tests/Feature/JurisdiccionControllerTest.php

- php artisan test tests/Feature/ColegioEmisorControllerTest.php

- php artisan test tests/Feature/TituloProfesionalControllerTest.php

- php artisan test tests/Feature/EspecialidadKinesiologiaControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos

- php artisan test tests/Feature/Policies

- php artisan test tests/Feature/InactiveCatalogValidationTest.php

- php artisan test tests/Feature/GlobalConfirmationTest.php

- php artisan test tests/Feature/GlobalFlashMessageTest.php

- php artisan test tests/Feature/NavigationTest.php

- php artisan test tests/Feature/JavaScriptJurisdiccionColegioTest.php

Condición de salida: Cero failures/errors. Los tests existentes y nuevos del bloque profesional están verdes.

## 19.14 — Suite global baseline antes de crear esquema clínico

Objetivo: Capturar un baseline global inequívoco del proyecto tras el refactor profesional.

### Comandos / verificaciones

```text
php artisan config:clear
php artisan test
```

Condición de salida: Suite completa del proyecto verde. Sólo entonces se permite avanzar a 19.15.

### NO HACER

- Crear todavía una migración clínica si la suite global no está verde.

# FASE B — Esquema y dominio clínico base

## 19.15 — Crear las 8 tablas del bloque clínico

Objetivo: Agregar el esquema persistente de los seis catálogos clínicos y las dos tablas subordinadas de Ejercicios exactamente según el Diccionario v4.

### Inspeccionar antes de modificar

```text
database/migrations/*
Diccionario de Datos v4
dump SQL actual
```

### Crear

```text
database/migrations/*_create_diagnosticos_clinicos_table.php
*_create_pruebas_funcionales_table.php
*_create_tecnicas_terapeuticas_table.php
*_create_categorias_clinicas_table.php
*_create_ejercicios_domiciliarios_table.php
*_create_ejercicio_categoria_table.php
*_create_recursos_ejercicio_table.php
*_create_tipos_equipo_terapeutico_table.php
```

### Reglas y decisiones congeladas

- diagnosticos_clinicos: sistema_origen, codigo, nombre, descripcion nullable, activo default true, timestamps, UK(sistema_origen,codigo).

- pruebas_funcionales: sistema_origen, codigo, nombre, descripcion nullable, unidad_medida nullable, activo, timestamps, UK(sistema_origen,codigo).

- tecnicas_terapeuticas: sistema_origen, codigo, nombre, descripcion nullable, activo, timestamps, UK(sistema_origen,codigo).

- categorias_clinicas: categoria_padre_id nullable FK, codigo UK, nombre, tipo, activo, timestamps.

- ejercicios_domiciliarios: SIN grupo_muscular_zona; incluir parámetros recomendados/base, dificultad_base, contraindicaciones, notas_clinicas, activo.

- ejercicio_categoria: pivote pura sin id; PK compuesta ejercicio_id,categoria_id.

- recursos_ejercicio: ejercicio_id, tipo, origen, ruta_storage nullable, url_externa nullable, titulo, orden, activo, timestamps; UK por ejercicio+ruta y ejercicio+url.

- tipos_equipo_terapeutico: codigo UK, nombre, descripcion nullable, categoria_clinica_id FK, activo, timestamps.

- No usar ENUM nativo de MySQL; dominios se validan en PHP para mantener compatibilidad SQLite/MySQL.

### Comandos / verificaciones

```text
php artisan migrate --pretend
```

Condición de salida: Las 8 migraciones reflejan exactamente v4 y migrate --pretend no reporta errores.

### NO HACER

- php artisan migrate:fresh

- php artisan db:wipe

- Modificar migraciones históricas ya ejecutadas.

## 19.16 — Cerrar FKs y conservación histórica

Objetivo: Garantizar que las nuevas relaciones no permitan cascadas destructivas incompatibles con la baja lógica.

### Inspeccionar antes de modificar

```text
tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php
las 8 migraciones nuevas
```

### Modificar

```text
tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php
migraciones nuevas si la revisión detecta onDelete incorrecto
```

### Reglas y decisiones congeladas

- Categoria padre con hija: RESTRICT/NO ACTION.

- Ejercicio con ejercicio_categoria: RESTRICT/NO ACTION.

- Categoría usada por ejercicio_categoria: RESTRICT/NO ACTION.

- Ejercicio con recursos: RESTRICT/NO ACTION.

- Categoría usada por tipo de equipo: RESTRICT/NO ACTION.

### Pruebas obligatorias

- php artisan test tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php

Condición de salida: Todas las nuevas FKs relevantes preservan historia y el test queda verde.

## 19.17 — Crear Models y enums clínicos

Objetivo: Representar el dominio persistente con Models, casts, relaciones y enums explícitos.

### Crear

```text
app/Models/DiagnosticoClinico.php
app/Models/PruebaFuncional.php
app/Models/TecnicaTerapeutica.php
app/Models/CategoriaClinica.php
app/Models/EjercicioDomiciliario.php
app/Models/RecursoEjercicio.php
app/Models/TipoEquipoTerapeutico.php
app/Enums/SistemaOrigenDiagnosticoClinicoEnum.php
app/Enums/SistemaOrigenPruebaFuncionalEnum.php
app/Enums/SistemaOrigenTecnicaTerapeuticaEnum.php
app/Enums/TipoCategoriaClinicaEnum.php
app/Enums/DificultadEjercicioEnum.php
app/Enums/FrecuenciaPeriodoEjercicioEnum.php
app/Enums/TipoRecursoEjercicioEnum.php
app/Enums/OrigenRecursoEjercicioEnum.php
```

### Reglas y decisiones congeladas

- Diagnóstico: CIE-10, CIF, SNOMED, INTERNO.

- Prueba: INTERNO, SNOMED, LOINC.

- Técnica: INTERNO, SNOMED.

- Categoría: ANATOMICA, FUNCIONAL, TERAPEUTICA, EQUIPAMIENTO.

- Dificultad: BAJA, MEDIA, ALTA. Frecuencia: DIARIA, SEMANAL.

- Recurso: IMAGEN, VIDEO, DOCUMENTO. Origen: LOCAL, EXTERNO.

- CategoriaClinica: padre(), hijas(), ejercicios(), tiposEquipo().

- EjercicioDomiciliario: categorias(), recursos(). RecursoEjercicio: ejercicio(). TipoEquipoTerapeutico: categoriaClinica().

- No crear Model para ejercicio_categoria salvo necesidad posterior demostrada.

### Pruebas obligatorias

- php artisan test tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php

Condición de salida: Models y enums cargan correctamente y las FKs siguen verdes.

# FASE C — Catálogos clínicos simples

## 19.18 — Implementar Diagnósticos clínicos

Objetivo: Crear el primer CRUD clínico completo usando el patrón profesional ya estabilizado.

### Modificar

```text
routes/web.php
resources/views/catalogos/index.blade.php
resources/views/catalogos/partials/navegacion.blade.php
```

### Crear

```text
app/Http/Controllers/DiagnosticoClinicoController.php
app/Http/Requests/StoreDiagnosticoClinicoRequest.php
app/Http/Requests/UpdateDiagnosticoClinicoRequest.php
app/Actions/Catalogos/CrearDiagnosticoClinicoAction.php
app/Actions/Catalogos/ActualizarDiagnosticoClinicoAction.php
app/Actions/Catalogos/CambiarEstadoDiagnosticoClinicoAction.php
app/Policies/DiagnosticoClinicoPolicy.php
resources/views/catalogos/diagnosticos-clinicos/index.blade.php
resources/views/catalogos/diagnosticos-clinicos/create.blade.php
resources/views/catalogos/diagnosticos-clinicos/edit.blade.php
resources/views/catalogos/diagnosticos-clinicos/show.blade.php
```

### Reglas y decisiones congeladas

- Ruta base /catalogos/diagnosticos-clinicos; name group propuesto catalogos.diagnosticos.* para mantener brevedad consistente con titulos/colegios.

- Métodos Controller: index/create/store/show/edit/update/inactivar/reactivar.

- Listado: Código | Nombre | Sistema origen | Estado | Acciones.

- Búsqueda por código/nombre; filtro estado; withQueryString().

- Alta activo=true; update conserva estado.

- Sistema origen validado contra enum definitivo.

- Eventos de auditoría: diagnostico_clinico_creado/actualizado/inactivado/reactivado.

Condición de salida: CRUD funcional implementado; la card del Hub queda habilitada.

## 19.19 — Cerrar PHPUnit de Diagnósticos clínicos

Objetivo: No avanzar a Pruebas funcionales hasta cerrar completamente el primer catálogo clínico.

### Crear

```text
tests/Feature/DiagnosticoClinicoControllerTest.php
tests/Feature/Actions/Catalogos/CrearDiagnosticoClinicoActionTest.php
tests/Feature/Actions/Catalogos/ActualizarDiagnosticoClinicoActionTest.php
tests/Feature/Actions/Catalogos/CambiarEstadoDiagnosticoClinicoActionTest.php
tests/Feature/Policies/DiagnosticoClinicoPolicyTest.php
```

### Reglas y decisiones congeladas

- Cubrir Admin/Superadmin, bloqueo no admin, vacío, búsqueda, filtros, create/store/show/edit/update, enum válido/inválido, UK compuesta, edición activo/inactivo, preservación de estado, inactivar/reactivar, auditoría, idempotencia y rollback.

### Pruebas obligatorias

- php artisan test tests/Feature/DiagnosticoClinicoControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=DiagnosticoClinico

- php artisan test tests/Feature/Policies/DiagnosticoClinicoPolicyTest.php

Condición de salida: Diagnósticos completamente verde.

## 19.20 — Implementar y probar Pruebas funcionales

Objetivo: Crear el catálogo Pruebas funcionales replicando la arquitectura, no las reglas específicas de Diagnósticos.

### Modificar

```text
routes/web.php
Hub y navegación parcial
```

### Crear

```text
app/Http/Controllers/PruebaFuncionalController.php
app/Http/Requests/StorePruebaFuncionalRequest.php
app/Http/Requests/UpdatePruebaFuncionalRequest.php
app/Actions/Catalogos/CrearPruebaFuncionalAction.php
app/Actions/Catalogos/ActualizarPruebaFuncionalAction.php
app/Actions/Catalogos/CambiarEstadoPruebaFuncionalAction.php
app/Policies/PruebaFuncionalPolicy.php
resources/views/catalogos/pruebas-funcionales/index.blade.php
create.blade.php / edit.blade.php / show.blade.php correspondientes
tests/Feature/PruebaFuncionalControllerTest.php
3 ActionTests
tests/Feature/Policies/PruebaFuncionalPolicyTest.php
```

### Reglas y decisiones congeladas

- Campos según v4; unidad_medida es opcional.

- Sistema origen: INTERNO/SNOMED/LOINC.

- Listado: Código | Nombre | Sistema origen | Unidad | Estado | Acciones.

- Búsqueda código/nombre; alta activa; update preserva estado; auditoría en escrituras.

### Pruebas obligatorias

- php artisan test tests/Feature/PruebaFuncionalControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=PruebaFuncional

- php artisan test tests/Feature/Policies/PruebaFuncionalPolicyTest.php

Condición de salida: Pruebas funcionales implementado y verde.

## 19.21 — Implementar y probar Técnicas terapéuticas

Objetivo: Crear el catálogo de Técnicas sin mezclar parámetros de dosificación que pertenecen a prescripción/ejecución.

### Modificar

```text
routes/web.php
Hub y navegación parcial
```

### Crear

```text
app/Http/Controllers/TecnicaTerapeuticaController.php
Store/UpdateTecnicaTerapeuticaRequest.php
Crear/Actualizar/CambiarEstadoTecnicaTerapeuticaAction.php
app/Policies/TecnicaTerapeuticaPolicy.php
resources/views/catalogos/tecnicas-terapeuticas/{index,create,edit,show}.blade.php
ControllerTest + 3 ActionTests + PolicyTest
```

### Reglas y decisiones congeladas

- Sistema origen: INTERNO/SNOMED.

- Listado: Código | Nombre | Sistema origen | Estado | Acciones.

- No agregar duración, series, repeticiones, frecuencia o intensidad al catálogo.

### Pruebas obligatorias

- php artisan test tests/Feature/TecnicaTerapeuticaControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=TecnicaTerapeutica

- php artisan test tests/Feature/Policies/TecnicaTerapeuticaPolicyTest.php

Condición de salida: Técnicas terapéuticas implementado y verde.

# FASE D — Categorías clínicas y Tipos de equipo

## 19.22 — Implementar base de Categorías clínicas

Objetivo: Crear el CRUD base y la representación visual/relacional de Categorías antes de introducir validaciones recursivas complejas.

### Modificar

```text
routes/web.php
Hub y navegación parcial
```

### Crear

```text
app/Http/Controllers/CategoriaClinicaController.php
app/Http/Requests/StoreCategoriaClinicaRequest.php
app/Http/Requests/UpdateCategoriaClinicaRequest.php
app/Actions/Catalogos/CrearCategoriaClinicaAction.php
app/Actions/Catalogos/ActualizarCategoriaClinicaAction.php
app/Actions/Catalogos/CambiarEstadoCategoriaClinicaAction.php
app/Policies/CategoriaClinicaPolicy.php
resources/views/catalogos/categorias-clinicas/{index,create,edit,show}.blade.php
tests/Feature/CategoriaClinicaControllerTest.php
3 ActionTests base
tests/Feature/Policies/CategoriaClinicaPolicyTest.php
```

### Reglas y decisiones congeladas

- Listado: Código | Nombre | Tipo | Categoría padre | Estado | Acciones.

- Código único; tipo por enum; categoria_padre_id nullable.

- En esta etapa dejar preparada la arquitectura para 19.23/19.24, sin duplicar lógica recursiva en Blade.

### Pruebas obligatorias

- Pruebas base: raíz válida, hija válida, tipo válido/inválido, código único, show, update, estado, autorización.

Condición de salida: CRUD base verde y preparado para reglas avanzadas.

## 19.23 — Implementar jerarquía, tipos y prevención de ciclos

Objetivo: Centralizar la lógica jerárquica para impedir autorreferencias, ciclos y padres incompatibles.

### Modificar

```text
StoreCategoriaClinicaRequest.php
UpdateCategoriaClinicaRequest.php
CrearCategoriaClinicaAction.php
ActualizarCategoriaClinicaAction.php
CategoriaClinicaController.php
create.blade.php
edit.blade.php
```

### Crear

```text
app/Services/Catalogos/JerarquiaCategoriaClinicaService.php
tests/Feature/Services/Catalogos/JerarquiaCategoriaClinicaServiceTest.php
```

### Reglas y decisiones congeladas

- Padre nuevo: activo y del mismo tipo.

- Prohibir self-parent y cualquier ciclo directo/indirecto.

- En edit, lista de padres excluye la propia categoría y todos sus descendientes.

- Mostrar relación histórica actual inactiva cuando corresponda, pero no ofrecerla como nueva alternativa.

- La validación backend es autoritativa aunque la UI filtre opciones.

### Pruebas obligatorias

- Raíz, hija válida, padre inactivo, tipo distinto, self-parent, A->B->A, A->B->C->A, descendiente excluido.

- php artisan test tests/Feature/Services/Catalogos/JerarquiaCategoriaClinicaServiceTest.php

- php artisan test tests/Feature/CategoriaClinicaControllerTest.php

Condición de salida: Jerarquía sin ciclos y tests verdes.

## 19.24 — Implementar restricciones de estado y dependencias de Categorías

Objetivo: Aplicar la inactivación/reactivación restrictiva que protege otras entidades maestras activas.

### Modificar

```text
CambiarEstadoCategoriaClinicaAction.php
ActualizarCategoriaClinicaAction.php
ActionTests correspondientes
```

### Crear

```text
app/Exceptions/Catalogos/NoSePuedeCambiarEstadoCategoriaClinicaException.php (si el patrón de excepciones lo justifica)
```

### Reglas y decisiones congeladas

- Inactivar rechaza si hay hijas activas.

- Si es ANATOMICA, rechaza si dejaría un ejercicio activo sin al menos una ANATOMICA activa.

- Rechaza si un Tipo de equipo activo depende de ella.

- Reactivar con padre exige padre existente, activo y mismo tipo.

- Cambiar tipo no puede volver incompatibles dependencias activas existentes.

### Pruebas obligatorias

- php artisan test tests/Feature/Actions/Catalogos/CambiarEstadoCategoriaClinicaActionTest.php

- php artisan test tests/Feature/Actions/Catalogos/ActualizarCategoriaClinicaActionTest.php

- php artisan test tests/Feature/CategoriaClinicaControllerTest.php

Condición de salida: Categorías cumple todas las restricciones de dependencia y queda verde.

## 19.25 — Implementar Tipos de equipo terapéutico

Objetivo: Crear el catálogo que depende de Categorías una vez que las reglas de EQUIPAMIENTO están estabilizadas.

### Modificar

```text
routes/web.php
Hub y navegación parcial
```

### Crear

```text
app/Http/Controllers/TipoEquipoTerapeuticoController.php
Store/UpdateTipoEquipoTerapeuticoRequest.php
Crear/Actualizar/CambiarEstadoTipoEquipoTerapeuticoAction.php
app/Policies/TipoEquipoTerapeuticoPolicy.php
resources/views/catalogos/tipos-equipo-terapeutico/{index,create,edit,show}.blade.php
ControllerTest + 3 ActionTests + PolicyTest
```

### Reglas y decisiones congeladas

- categoria_clinica_id debe apuntar a categoría activa de tipo EQUIPAMIENTO para crear, cambiar asociación o reactivar.

- En edición puede conservar su asociación histórica actual inactiva si no la cambia.

- Listado: Código | Nombre | Categoría EQUIPAMIENTO | Estado | Acciones.

### Pruebas obligatorias

- Alta válida, rechazo de ANATOMICA/FUNCIONAL/TERAPEUTICA, rechazo categoría inactiva, conservación histórica, reactivación válida/bloqueada, auditoría.

- php artisan test tests/Feature/TipoEquipoTerapeuticoControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=TipoEquipoTerapeutico

- php artisan test tests/Feature/Policies/TipoEquipoTerapeuticoPolicyTest.php

Condición de salida: Tipos de equipo implementado y verde.

# FASE E — Ejercicios domiciliarios y Recursos multimedia

## 19.26 — Preparar dominio y relaciones de Ejercicios domiciliarios

Objetivo: Cerrar Models, relaciones, casts y convenciones antes de definir el contrato HTTP del agregado más complejo.

### Inspeccionar antes de modificar

```text
app/Models/EjercicioDomiciliario.php
app/Models/CategoriaClinica.php
app/Models/RecursoEjercicio.php
Diccionario v4
```

### Modificar

```text
los 3 Models si quedaron incompletos en 19.17
```

### Crear

```text
tests/Feature/Models/EjercicioDomiciliarioTest.php (si las relaciones merecen prueba explícita)
```

### Reglas y decisiones congeladas

- EjercicioDomiciliario: categorias(), recursos().

- CategoriaClinica: ejercicios(). RecursoEjercicio: ejercicio().

- No existe grupo_muscular_zona.

- dificultad_base obligatoria.

- frecuencia_cantidad_base y frecuencia_periodo_base se informan conjuntamente.

### Pruebas obligatorias

- php artisan test tests/Feature/Models/EjercicioDomiciliarioTest.php (si existe)

- php artisan test tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php

Condición de salida: Dominio de Ejercicios listo para Requests/Storage.

## 19.27 — Congelar payload y Requests de Ejercicios

Objetivo: Definir explícitamente el contrato de entrada antes de programar persistencia y archivos.

### Crear

```text
app/Http/Requests/StoreEjercicioDomiciliarioRequest.php
app/Http/Requests/UpdateEjercicioDomiciliarioRequest.php
```

### Reglas y decisiones congeladas

- No crear de entrada EjercicioDomiciliarioRequestBase.php; sólo extraer abstracción si la duplicación real lo justifica.

- Payload principal: codigo, nombre, descripcion, series_recomendadas, repeticiones_recomendadas, duracion_segundos_recomendada, frecuencia_cantidad_base, frecuencia_periodo_base, descanso_segundos_base, carga_resistencia_sugerida, dificultad_base, contraindicaciones, notas_clinicas, categorias[], recursos[].

- Cada recurso puede incluir id nullable (sólo para existente), tipo, origen, titulo, orden, activo, archivo (input técnico) y url_externa.

- NUNCA aceptar ruta_storage desde el cliente; es un dato controlado por backend.

- Si recursos.*.id viene en Update, validar que pertenezca al ejercicio editado, no sólo que exista.

- Alta activa: >=1 ANATOMICA activa; sólo ANATOMICA/FUNCIONAL/TERAPEUTICA; EQUIPAMIENTO prohibida.

- Update puede conservar asociaciones históricas inactivas existentes, pero no crear nuevas asociaciones inactivas.

- Frecuencia cantidad/período juntas; números con límites coherentes; categorias distinct.

- LOCAL: archivo requerido cuando se crea/reemplaza recurso local; url_externa NULL. EXTERNO: URL válida; archivo/ruta local NULL.

### Pruebas obligatorias

- Añadir cobertura de validación en ControllerTest/Request behavior antes de avanzar a Actions.

Condición de salida: Contrato HTTP inequívoco y validación backend definida.

### NO HACER

- Aceptar ruta_storage desde formularios.

- Interpretar omisión de un recurso persistido como DELETE.

## 19.28 — Implementar Recursos/Storage y pruebas de filesystem

Objetivo: Resolver antes de las Actions cómo se crean, reemplazan, conservan y limpian archivos LOCAL/EXTERNO.

### Inspeccionar antes de modificar

```text
config/filesystems.php
config/kinesiosoft.php
app/Models/RecursoEjercicio.php
```

### Modificar

```text
config/kinesiosoft.php
```

### Crear

```text
app/Services/Catalogos/GestionRecursosEjercicioService.php
tests/Feature/Services/Catalogos/GestionRecursosEjercicioServiceTest.php
```

### Reglas y decisiones congeladas

- Congelar disk 'public' para este incremento y una carpeta relativa de recursos de ejercicio configurada en config/kinesiosoft.php.

- Persistir ruta relativa, no path físico absoluto.

- Nuevo LOCAL: almacenar UploadedFile, devolver ruta_storage y url_externa=NULL.

- Nuevo EXTERNO: no tocar Storage; persistir url_externa y ruta_storage=NULL.

- Existente sin reemplazo: conservar referencia actual.

- Existente con reemplazo/cambio de origen: guardar primero lo nuevo, no borrar lo viejo antes de confirmar DB.

- Si la transacción SQL falla: eliminar archivos nuevos; conservar archivos antiguos.

- Si la transacción confirma: recién entonces eliminar archivos antiguos reemplazados cuando corresponda.

- Inactivar recurso conserva su archivo; reactivar no requiere reupload.

- Recurso persistido se identifica por id; un recurso nuevo tiene id null/ausente.

### Pruebas obligatorias

- Usar Storage::fake('public').

- Cubrir LOCAL, EXTERNO, reemplazo, LOCAL->EXTERNO, EXTERNO->LOCAL, cleanup tras fallo, preservación de archivo histórico.

- php artisan test tests/Feature/Services/Catalogos/GestionRecursosEjercicioServiceTest.php

Condición de salida: Contrato de filesystem probado antes de crear Actions de Ejercicios.

## 19.29 — Implementar Actions transaccionales de Ejercicios y ActionTests

Objetivo: Persistir el agregado Ejercicio+Categorías+Recursos+Auditoría con rollback real y sin archivos huérfanos.

### Crear

```text
app/Actions/Catalogos/CrearEjercicioDomiciliarioAction.php
app/Actions/Catalogos/ActualizarEjercicioDomiciliarioAction.php
app/Actions/Catalogos/CambiarEstadoEjercicioDomiciliarioAction.php
app/Exceptions/Catalogos/NoSePuedeReactivarEjercicioDomiciliarioException.php (si corresponde)
tests/Feature/Actions/Catalogos/CrearEjercicioDomiciliarioActionTest.php
tests/Feature/Actions/Catalogos/ActualizarEjercicioDomiciliarioActionTest.php
tests/Feature/Actions/Catalogos/CambiarEstadoEjercicioDomiciliarioActionTest.php
```

### Reglas y decisiones congeladas

- Crear: DB transaction -> ejercicio -> sync categorías -> recursos -> auditoría -> commit; ante error, rollback DB + cleanup de archivos nuevos.

- Actualizar: lockForUpdate() del ejercicio; actualizar atributos; sincronizar categorías válidas; crear/actualizar/inactivar/reactivar recursos; auditoría; commit.

- No borrar un recurso existente sólo porque no vino en el request.

- Ejercicio activo siempre conserva >=1 ANATOMICA activa y 0 EQUIPAMIENTO.

- Nuevas asociaciones de categoría deben estar activas; históricas inactivas existentes pueden conservarse.

- Reactivar revalida reglas actuales, no las reglas históricas del día de creación.

- El cambio de estado usa estado objetivo explícito e idempotente.

### Pruebas obligatorias

- Alta válida con categorías/recursos.

- Rollback DB + archivo ante fallo intermedio.

- Update con relación histórica.

- LOCAL/EXTERNO y reemplazos.

- Reactivación válida/sin ANATOMICA/con EQUIPAMIENTO.

- Auditoría e idempotencia.

- php artisan test tests/Feature/Actions/Catalogos --filter=EjercicioDomiciliario

Condición de salida: Las Actions del agregado están verdes antes de construir Controller/UI.

## 19.30 — Implementar Controller, Policy, rutas, UI y JS de Ejercicios

Objetivo: Conectar el agregado ya probado con la capa HTTP y la UI dedicada congelada en el Paso 6.

### Modificar

```text
routes/web.php
resources/js/app.js
Hub y navegación parcial
```

### Crear

```text
app/Http/Controllers/EjercicioDomiciliarioController.php
app/Policies/EjercicioDomiciliarioPolicy.php
resources/views/catalogos/ejercicios-domiciliarios/index.blade.php
create.blade.php
edit.blade.php
show.blade.php
partials/form.blade.php
partials/recursos.blade.php
resources/js/components/ejercicio-catalogo.js
tests/Feature/EjercicioDomiciliarioControllerTest.php
tests/Feature/Policies/EjercicioDomiciliarioPolicyTest.php
tests/Feature/JavaScriptEjercicioCatalogoTest.php
```

### Reglas y decisiones congeladas

- Formularios create/edit usan enctype="multipart/form-data".

- Alpine administra filas/estado/orden, pero archivos viajan como UploadedFile real; no Base64 ni strings simuladas.

- Nombres anidados: recursos[i][id|tipo|origen|titulo|orden|activo|archivo|url_externa].

- Los input type=file no se repueblan tras error de validación; la UI debe avisar que el usuario debe re-seleccionar archivos locales nuevos.

- Cinco secciones: Datos generales; Parámetros recomendados; Clasificación clínica; Contraindicaciones/notas; Recursos multimedia.

- Show es página completa readonly.

- Listado: Código | Nombre | Dificultad | Categorías | Recursos | Estado | Acciones; usar withCount() cuando corresponda.

- Mobile muestra dificultad + conteo de categorías + recursos activos.

- No usar CDN; integrar módulo JS desde resources/js/app.js.

### Pruebas obligatorias

- php artisan test tests/Feature/EjercicioDomiciliarioControllerTest.php

- php artisan test tests/Feature/Policies/EjercicioDomiciliarioPolicyTest.php

- php artisan test tests/Feature/JavaScriptEjercicioCatalogoTest.php

- npm run build

Condición de salida: Flujo HTTP/UI de Ejercicios verde sobre Actions ya probadas.

## 19.31 — Regresión integrada exhaustiva del agregado Ejercicio

Objetivo: Probar cruces HTTP+DB+Storage+categorías+recursos después de haber testeado cada capa por separado.

### Reglas y decisiones congeladas

- Esta etapa es regresión integrada; no es el primer momento en que se prueban Requests/Storage/Actions.

- Cubrir cambios LOCAL->EXTERNO, EXTERNO->LOCAL, reemplazo de archivo, reordenamiento, inactivar/reactivar recurso, rollback completo, edición de asociaciones históricas, reactivación de ejercicio, auditoría del agregado.

- Validar que recurso existente no pueda quitarse físicamente por omisión ni por UI ordinaria.

### Pruebas obligatorias

- php artisan test tests/Feature/EjercicioDomiciliarioControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos --filter=EjercicioDomiciliario

- php artisan test tests/Feature/Services/Catalogos/GestionRecursosEjercicioServiceTest.php

- php artisan test tests/Feature/Policies/EjercicioDomiciliarioPolicyTest.php

- php artisan test tests/Feature/JavaScriptEjercicioCatalogoTest.php

Condición de salida: El agregado completo se comporta de forma consistente y no deja persistencia/archivos parciales.

# FASE F — Integración transversal, regresión y cierre

## 19.32 — Auditoría transversal de los 10 catálogos

Objetivo: Uniformar eventos y comprobar que sólo las operaciones que modifican estado/contenido generan auditoría.

### Inspeccionar antes de modificar

```text
app/Services/AuditoriaService.php
todas las Actions de Catálogos
tests/Feature/Services/AuditoriaServiceTest.php
tests/Feature/AuditoriaControllerTest.php
```

### Reglas y decisiones congeladas

- Módulo de auditoría: Catálogos.

- Eventos consistentes *_creado, *_actualizado, *_inactivado, *_reactivado.

- index/create/edit/show no auditan.

- store/update/inactivar/reactivar auditan; update sin cambio real no debe producir auditoría engañosa.

- Ejercicios audita cambios significativos sin incluir binarios ni rutas temporales innecesarias.

### Pruebas obligatorias

- php artisan test tests/Feature/Services/AuditoriaServiceTest.php

- php artisan test tests/Feature/AuditoriaControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos

Condición de salida: Auditoría consistente y verde en todo el módulo.

## 19.33 — Congelar estrategia de fixtures de tests

Objetivo: Evitar introducir factories masivamente si el proyecto actual no las necesita.

### Inspeccionar antes de modificar

```text
database/factories/
tests de catálogos existentes
```

### Reglas y decisiones congeladas

- Mantener Model::create([...]) y helpers privados cuando sea suficiente.

- Crear una Factory nueva sólo si la repetición en Ejercicios hace objetivamente inmanejables los fixtures y se aprueba explícitamente.

Condición de salida: Estrategia de fixtures documentada; no se generan factories por defecto.

### NO HACER

- Crear automáticamente una Factory por cada catálogo.

## 19.34 — Congelar estrategia de seeders clínicos

Objetivo: No inventar datos maestros clínicos como parte de la implementación estructural.

### Inspeccionar antes de modificar

```text
database/seeders/DatabaseSeeder.php
seeders profesionales existentes
```

### Reglas y decisiones congeladas

- No crear seeders de Diagnósticos/Pruebas/Técnicas/Categorías/Ejercicios/Tipos de equipo sin dataset aprobado.

- Los enums de dominio no equivalen a un catálogo oficial de datos.

Condición de salida: DatabaseSeeder permanece sin datos clínicos inventados.

### NO HACER

- Sembrar CIE/SNOMED/LOINC ficticios sólo porque aparecen como sistemas de origen.

## 19.35 — Pasada responsive final de los 10 catálogos

Objetivo: Aplicar uniformemente el prototipo final una vez que todas las rutas y CRUD existen.

### Inspeccionar antes de modificar

```text
resources/views/catalogos/**/*
prototipo final Paso 6
```

### Modificar

```text
vistas/componentes que necesiten ajuste responsive
```

### Reglas y decisiones congeladas

- Desktop conserva columnas específicas.

- Mobile usa cards contextuales: Colegio->jurisdicción; Diagnóstico->sistema; Prueba->sistema+unidad; Técnica->sistema; Categoría->tipo+padre; Ejercicio->dificultad+conteos; Tipo equipo->categoría EQUIPAMIENTO.

- No transformar tablas grandes en scroll horizontal como solución principal móvil.

### Pruebas obligatorias

- npm run build

- Ejecutar ControllerTests de los 10 catálogos.

Condición de salida: Los 10 catálogos quedan usables en desktop/mobile.

## 19.36 — Actualizar pruebas transversales del sistema

Objetivo: Asegurar que navegación, confirmaciones, mensajes y FKs globales contemplan el módulo completo.

### Inspeccionar antes de modificar

```text
tests/Feature/NavigationTest.php
tests/Feature/GlobalConfirmationTest.php
tests/Feature/GlobalFlashMessageTest.php
tests/Feature/InactiveCatalogValidationTest.php
tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php
```

### Modificar

```text
los tests transversales sólo donde sea necesario
```

### Reglas y decisiones congeladas

- NavigationTest cubre el Hub y el acceso administrativo.

- GlobalConfirmationTest sigue validando el contrato .js-confirm-action.

- ConservacionHistoricaForeignKeysTest incluye las nuevas FKs.

- InactiveCatalogValidationTest sólo se amplía cuando exista un consumidor real; no inventar módulos todavía no implementados.

### Pruebas obligatorias

- php artisan test tests/Feature/NavigationTest.php

- php artisan test tests/Feature/GlobalConfirmationTest.php

- php artisan test tests/Feature/GlobalFlashMessageTest.php

- php artisan test tests/Feature/InactiveCatalogValidationTest.php

- php artisan test tests/Feature/Database/ConservacionHistoricaForeignKeysTest.php

Condición de salida: Pruebas transversales verdes.

## 19.37 — Regresión integral de Catálogos

Objetivo: Ejecutar todas las familias de pruebas del módulo antes de tocar la suite completa.

### Pruebas obligatorias

- php artisan test tests/Feature/JurisdiccionControllerTest.php

- php artisan test tests/Feature/ColegioEmisorControllerTest.php

- php artisan test tests/Feature/TituloProfesionalControllerTest.php

- php artisan test tests/Feature/EspecialidadKinesiologiaControllerTest.php

- php artisan test tests/Feature/DiagnosticoClinicoControllerTest.php

- php artisan test tests/Feature/PruebaFuncionalControllerTest.php

- php artisan test tests/Feature/TecnicaTerapeuticaControllerTest.php

- php artisan test tests/Feature/CategoriaClinicaControllerTest.php

- php artisan test tests/Feature/TipoEquipoTerapeuticoControllerTest.php

- php artisan test tests/Feature/EjercicioDomiciliarioControllerTest.php

- php artisan test tests/Feature/Actions/Catalogos

- php artisan test tests/Feature/Policies

Condición de salida: Todo Catálogos verde; las 10 cards del Hub ya deben ser navegables.

## 19.38 — Suite completa, Pint y build

Objetivo: Comprobar que Catálogos no rompió módulos ajenos y que el código/asset pipeline queda limpio.

### Reglas y decisiones congeladas

- Si Pint reporta cambios: ejecutar ./vendor/bin/pint y volver a correr php artisan test.

- No clasificar una regresión como “ajena” sin demostrar que existía en el baseline 19.14.

### Comandos / verificaciones

```text
php artisan config:clear
php artisan test
./vendor/bin/pint --test
npm run build
```

Condición de salida: Suite completa, Pint y build verdes.

## 19.39 — Prueba funcional manual completa

Objetivo: Validar el UC extremo a extremo con roles y escenarios reales que no se capturan sólo con tests automatizados.

### Reglas y decisiones congeladas

- Para cada catálogo: Hub -> listado -> buscar -> filtrar -> registrar -> consultar -> editar activo -> inactivar -> consultar/editar inactivo -> reactivar.

- Colegios: reactivación bloqueada con jurisdicción inactiva.

- Jurisdicciones: inactivación bloqueada con colegio activo.

- Categorías: raíz/hija, mismo tipo, self-parent, ciclos, inactivación restrictiva.

- Tipo equipo: sólo categoría EQUIPAMIENTO válida.

- Ejercicios: ANATOMICA requerida, EQUIPAMIENTO prohibida, LOCAL/EXTERNO, reordenamiento, inactivar/reactivar recurso, responsive.

- Paciente/Kinesiólogo no acceden ni ven enlaces administrativos.

Condición de salida: Todos los flujos manuales observados coinciden con CU y prototipo congelados.

## 19.40 — Revisión de rendimiento

Objetivo: Evitar N+1 y consultas innecesarias en listados, especialmente en catálogos relacionales.

### Inspeccionar antes de modificar

```text
los 10 index() de Controllers
queries de relaciones
uso de with()/withCount()/paginación
```

### Reglas y decisiones congeladas

- Colegio eager load de jurisdiccion.

- Categoría eager load de padre cuando se muestra.

- TipoEquipo eager load de categoriaClinica.

- Ejercicio preferir withCount() para conteos de categorías/recursos y eager loading sólo cuando sea necesario.

- Mantener paginate(10) salvo decisión posterior explícita.

- Revisar índices alineados con búsquedas/UK; no agregar índices arbitrarios sin medir.

### Pruebas obligatorias

- php artisan test

- npm run build

Condición de salida: No hay N+1 evidentes y el módulo cumple la expectativa de respuesta del CU en el entorno previsto.

## 19.41 — Revisión Git y limpieza

Objetivo: Auditar el diff completo antes de cerrar el módulo.

### Reglas y decisiones congeladas

- Verificar 10 familias de rutas y ausencia de rutas destroy.

- Confirmar .env, vendor/, node_modules/, dump SQL y documentos congelados sin cambios accidentales.

- No dejar console.log, datos demo, CDN duplicados, switch activo en forms ni migraciones históricas editadas.

- Revisar que UserController/Matrículas no hayan sido tocados por el refactor de toggle.

### Comandos / verificaciones

```text
git status --short
git diff --stat
git diff
php artisan route:list --name=catalogos
```

Condición de salida: Diff limpio, comprensible y estrictamente dentro del alcance.

## 19.42 — Suite de cierre y commit

Objetivo: Cerrar formalmente UC–XX sólo cuando todas las puertas anteriores estén verdes.

### Reglas y decisiones congeladas

- Realizar commit sólo si no quedan failures/errors/build errors ni pendientes funcionales.

- Commit propuesto: feat: implement master catalogs management.

- Después del commit, registrar UC–XX Gestionar catálogos maestros como implementado y cerrar Paso 8 para este módulo.

### Comandos / verificaciones

```text
php artisan config:clear
php artisan test
./vendor/bin/pint --test
npm run build
git status --short
```

Condición de salida: Commit de cierre creado sobre una suite íntegramente verde.

### NO HACER

- Hacer commit para “guardar avance” si todavía hay tests fallando dentro de este cierre final.

# 5. Modo recomendado de trabajo con Codex

Primera instrucción a Codex: Ejecutar exclusivamente 19.2 y 19.3. Inspeccionar, devolver relevamiento y discrepancias; no modificar archivos y no avanzar a 19.4.

Segunda instrucción: Ejecutar exclusivamente 19.4. Al finalizar, mostrar archivos modificados, diff conceptual y salida de tests. No avanzar a 19.5.

Regla general: A partir de allí avanzar uno o pocos subpasos por vez, usando las condiciones de salida como puertas obligatorias.

```text
Formato de entrega esperado de Codex al cerrar cada subpaso:
- Archivos inspeccionados
- Archivos modificados
- Archivos creados
- Reglas aplicadas
- Comandos ejecutados
- Resultado de PHPUnit / npm / Pint
- Riesgos o discrepancias encontradas
- Confirmación explícita: NO se avanzó al siguiente subpaso
```

# 6. Estado metodológico congelado

| Paso | Estado |
| --- | --- |
| 1. Modelo revisado/congelado | ✅ |
| 2. Cambios al DD | ✅ |
| 3. DD + MR definitivo | ✅ |
| 4. Relaciones/cardinalidades | ✅ |
| 5. UC–XX refactorizado/congelado | ✅ |
| 6. Diseño de pantalla | ✅ CONGELADO |
| 7. Planificación exhaustiva de implementación | ✅ CONGELADO — ESTE DOCUMENTO |
| 8. Programación | ⏳ Pendiente — iniciar por 19.2–19.3 |

FIN DE LA PLANIFICACIÓN EXHAUSTIVA CONGELADA — PASO 7
