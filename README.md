KinesioSoft

KinesioSoft es un sistema web de gestión para un consultorio de kinesiología, desarrollado con Laravel.

El proyecto forma parte de un trabajo académico para la cátedra Trabajo Final/Proyecto Software de la carrera Analista de Sistemas de Computación de la Universidad Nacional de Misiones y se encuentra en desarrollo incremental.

La aplicación utiliza autenticación, perfiles diferenciados por rol, autorización en múltiples capas, gestión administrativa de usuarios y catálogos profesionales, matrículas de kinesiólogos y un módulo transversal de auditoría.

La documentación de requisitos, casos de uso, diagramas de secuencia, modelo relacional, diagrama de clases y demás artefactos del Proceso Unificado se mantiene separada de este README.

Estado actual del proyecto

Actualmente KinesioSoft incluye:

Autenticación y registro de usuarios.

Verificación de correo electrónico.

Gestión de cuenta propia.

Perfil de paciente.

Perfil profesional de kinesiólogo.

Gestión de matrículas profesionales.

Gestión administrativa de usuarios.

Gestión de catálogos profesionales:

Jurisdicciones.

Colegios emisores.

Títulos profesionales.

Especialidades de kinesiología.

Navegación y dashboard diferenciados por rol.

Control de usuarios activos e inactivos.

Autorización mediante middleware, Policies y Gates.

Auditoría transversal de operaciones relevantes.

Consulta y filtrado de registros de auditoría.

Exportación de auditoría a PDF.

Componentes JavaScript reutilizables para formularios profesionales.

Los módulos clínicos y operativos restantes se incorporarán progresivamente de acuerdo con la planificación del proyecto.

Tecnologías principales

El proyecto utiliza principalmente:

PHP 8.2.

Laravel 12.

MySQL.

Laravel Breeze.

Blade.

Tailwind CSS.

Alpine.js.

Vite.

Spatie Laravel Permission.

SweetAlert2.

Tom Select.

DomPDF.

PHPUnit.

Entorno de desarrollo validado

PHP 8.2.12.

Laravel 12.55.1.

Composer 2.8.6.

Spatie Laravel Permission 6.25.0.

Node.js 25.8.1.

npm 11.11.0.

XAMPP con MySQL.

Windows.

Las versiones de Node.js y npm indicadas corresponden al entorno actualmente utilizado y validado. No representan necesariamente las versiones mínimas requeridas por el proyecto.

Requisitos del entorno

Para ejecutar KinesioSoft localmente se requiere:

PHP compatible con Laravel 12.

Composer.

MySQL.

Node.js y npm.

Extensiones PHP requeridas por Laravel y sus dependencias.

Un servidor web compatible o el servidor de desarrollo de Laravel.

Durante el desarrollo se utiliza XAMPP para Apache y MySQL.

Instalación local

Clonar o copiar el proyecto

Si se utiliza Git:

git clone <URL_DEL_REPOSITORIO>
cd KinesioSoft

Si el proyecto se copia manualmente, ubicarse luego en su directorio raíz.

Dependencias PHP

composer install

Variables de entorno

Crear .env a partir de .env.example:

Copy-Item .env.example .env

Generar la clave de aplicación:

php artisan key:generate

Configurar MySQL, por ejemplo:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kinesiosoft_db
DB_USERNAME=root
DB_PASSWORD=

Configurar también el superadministrador inicial:

KINESIOSOFT_SUPERADMIN_NAME=Superadministrador
KINESIOSOFT_SUPERADMIN_APELLIDO=Principal
KINESIOSOFT_SUPERADMIN_EMAIL=
KINESIOSOFT_SUPERADMIN_PASSWORD=

KINESIOSOFT_SUPERADMIN_EMAIL y KINESIOSOFT_SUPERADMIN_PASSWORD deben configurarse antes de ejecutar los seeders.

Las contraseñas y demás secretos no deben almacenarse en el repositorio. .env está excluido del control de versiones y .env.example debe contener únicamente valores seguros para compartir.

Si Laravel tuviera configuración cacheada:

php artisan config:clear

Base de datos

Crear previamente una base MySQL vacía. Durante el desarrollo se utiliza kinesiosoft_db.

No es necesario importar manualmente una copia de una base existente para realizar una instalación nueva: la estructura se reconstruye mediante migraciones y los datos iniciales mediante seeders.

Migraciones y datos iniciales

php artisan migrate --seed

Para reconstruir completamente una base utilizada exclusivamente para desarrollo o pruebas:

php artisan migrate:fresh --seed

Advertencia: migrate:fresh elimina todas las tablas de la base configurada. No debe utilizarse sobre una base que contenga información que deba conservarse.

Dependencias frontend

npm install

Durante el desarrollo:

npm run dev

Para compilar assets:

npm run build

Levantar la aplicación

Puede utilizarse Apache mediante XAMPP o:

php artisan serve

Correo electrónico en desarrollo

El entorno local utiliza:

MAIL_MAILER=log

Los mensajes generados por la aplicación, incluidos los enlaces de verificación, pueden consultarse en storage/logs.

Migraciones y datos iniciales

Migraciones

KinesioSoft utiliza migraciones de Laravel para definir y evolucionar el esquema de datos.

Entre las estructuras actualmente gestionadas se encuentran:

Usuarios.

Roles y permisos.

Pacientes.

Kinesiólogos.

Jurisdicciones.

Títulos profesionales.

Especialidades de kinesiología.

Colegios emisores.

Matrículas profesionales.

Relaciones entre kinesiólogos y especialidades.

Auditoría.

También existen migraciones destinadas a preservar integridad referencial e información histórica en relaciones relevantes.

El estado de las migraciones puede consultarse con:

php artisan migrate:status

Seeders

DatabaseSeeder ejecuta actualmente:

RoleSeeder.

SuperadminUserSeeder.

JurisdiccionSeeder.

ColegioEmisorSeeder.

TituloProfesionalSeeder.

EspecialidadKinesiologiaSeeder.

Los seeders de roles y catálogos evitan crear duplicados cuando los datos iniciales ya existen. Su finalidad principal es inicializar una instalación, no sincronizar automáticamente modificaciones posteriores.

Roles iniciales

KinesioSoft define:

superadmin
administrador
kinesiologo
paciente

Los nombres se centralizan mediante App\Enums\RoleEnum.

Superadministrador inicial

La configuración se centraliza en config/kinesiosoft.php y utiliza:

KINESIOSOFT_SUPERADMIN_NAME
KINESIOSOFT_SUPERADMIN_APELLIDO
KINESIOSOFT_SUPERADMIN_EMAIL
KINESIOSOFT_SUPERADMIN_PASSWORD

En una instalación nueva, SuperadminUserSeeder:

Crea el usuario configurado.

Almacena la contraseña de forma hasheada.

Lo crea activo.

Marca su correo como verificado.

Le asigna exclusivamente el rol superadmin.

El seeder es un mecanismo de bootstrap inicial, no de administración posterior.

Si vuelve a ejecutarse y el email ya pertenece exclusivamente a un superadministrador, no duplica el usuario ni modifica contraseña, nombre, apellido, estado activo o verificación.

Cambiar KINESIOSOFT_SUPERADMIN_PASSWORD en .env no rota automáticamente la contraseña de un superadministrador existente.

Si el email configurado pertenece a un usuario que no posee exclusivamente el rol superadmin, el seeder aborta mediante una excepción en lugar de elevar silenciosamente sus privilegios.

Roles y autorización

KinesioSoft implementa autorización en múltiples capas:

Estado de cuenta
↓
Autenticación
↓
Perfil completo
↓
Verificación de correo
↓
Middleware por rol
↓
Policies / Gates

Paciente

El rol paciente identifica al usuario que utiliza las funciones destinadas al paciente. Durante el onboarding puede acceder al formulario necesario para completar su perfil.

Un paciente no tiene acceso a las áreas administrativas de usuarios, catálogos o auditoría.

Kinesiólogo

El rol kinesiologo identifica al profesional. Su perfil requiere la información personal y profesional definida por KinesioSoft, incluyendo datos de matrícula y especialidades.

Puede administrar sus propias matrículas. Las Policies verifican que la matrícula pertenezca al perfil profesional autenticado antes de autorizar su actualización o cambio de estado.

Administrador

El administrador puede acceder a gestión de usuarios, catálogos, consulta de auditoría y exportación de registros.

Puede administrar pacientes y kinesiólogos, pero no puede modificar otro administrador ni un superadministrador.

Superadministrador

El superadministrador posee las capacidades administrativas más amplias.

Mediante la gestión ordinaria no se permite cambiar el rol de una cuenta superadministradora hacia otro rol. Puede actualizar sus datos manteniendo el rol, pero no degradarla a paciente, kinesiólogo o administrador mediante ese flujo.

Capas de autorización

Aliases propios:

perfil.completo
perfil.paciente
perfil.kinesiologo
admin.or.superadmin

perfil.paciente permite pacientes; perfil.kinesiologo, kinesiólogos; admin.or.superadmin, administradores o superadministradores; y perfil.completo controla el onboarding.

Usuarios activos

EnsureUserIsActive se incorpora al grupo global web.

Si un usuario autenticado tiene activo = false, KinesioSoft cierra su sesión, elimina su remember_token, invalida la sesión, regenera el token CSRF, redirige al login e informa que la cuenta está inactiva.

Perfil completo y verificación de correo

Las rutas operativas principales utilizan, según corresponda:

auth
perfil.completo
verified

El dashboard exige las tres condiciones.

Las rutas destinadas a completar el perfil son una excepción deliberada para permitir el onboarding. La gestión general de la cuenta en /profile requiere autenticación.

Policies y Gates

Las operaciones sensibles utilizan Policies y Gate::authorize() además de los middleware.

Actualmente existen Policies específicas para usuarios, auditoría, jurisdicciones, títulos profesionales, colegios emisores, especialidades y matrículas profesionales.

Gestión de usuarios

Cambio de rol

KinesioSoft trabaja actualmente con un único rol operativo por usuario.

Cuando existe un cambio real:

$user->syncRoles([$rol->value]);

Esto reemplaza el rol anterior en lugar de acumular varios roles.

Regla histórica de cambio de rol

Los perfiles asociados a un usuario se consideran información histórica independiente del rol operativo actual.

Usuario
├── rol operativo actual
├── perfil paciente histórico
└── perfil profesional histórico

Cambiar el rol no elimina automáticamente el perfil anterior.

Al cambiar de kinesiólogo a paciente, los datos profesionales previamente registrados se conservan. Si no existe perfil de paciente, KinesioSoft lo crea y genera su número de historia clínica; si ya existe, lo reutiliza.

Al cambiar hacia kinesiologo, KinesioSoft no crea automáticamente un perfil profesional completo. El usuario deberá completar el onboarding correspondiente si todavía no posee la información requerida.

La actualización administrativa y el cambio de rol se ejecutan dentro de una transacción. Si la operación falla, los cambios asociados se revierten.

Cuando se modifican simultáneamente datos generales y rol, se registran eventos de auditoría separados.

Auditoría

KinesioSoft implementa un sistema transversal de auditoría para operaciones relevantes de seguridad, administración y mantenimiento de información.

La escritura se centraliza principalmente en:

App\Services\AuditoriaService

La construcción de consultas se encuentra en:

App\Services\Auditorias\AuditoriaQueryService

Arquitectura

Un registro puede conservar:

Usuario actor.

Nombre histórico del actor.

Email histórico del actor.

Evento.

Módulo.

Recurso afectado.

Valores anteriores.

Valores nuevos.

Metadata.

Dirección IP.

User-Agent.

Fecha y hora.

El recurso afectado usa una relación polimórfica mediante auditable_type y auditable_id.

El actor conserva nombre y email como snapshot histórico.

Protección de datos sensibles

AuditoriaService aplica sanitización recursiva antes de persistir valores anteriores, nuevos o metadata.

Se excluyen, entre otros:

password
password_confirmation
current_password
remember_token
token
session
session_id
two_factor_secret
two_factor_recovery_codes

Inmutabilidad

Los registros de auditoría son inmutables desde el modelo de aplicación.

El modelo Auditoria impide su actualización y eliminación mediante Eloquent lanzando una LogicException.

Esta protección corresponde a la capa de aplicación Laravel y no implica una imposibilidad física de modificar las filas directamente mediante MySQL.

Eventos auditados

Actualmente existen eventos asociados a:

Auditoría.

Catálogos.

Cuenta.

Matrículas profesionales.

Perfiles.

Sistema / Acceso.

Usuarios.

Se auditan, por ejemplo, inicios y cierres de sesión, creación y actualización de usuarios, cambios de rol, actualización de cuenta, cambio y restablecimiento de contraseña, verificación de correo, perfiles, matrículas, catálogos y exportaciones de auditoría a PDF.

Cuando una operación administrativa modifica datos generales y rol se registran dos eventos semánticos distintos:

usuario_actualizado
rol_cambiado

Consulta y autorización

La consulta está autorizada únicamente para administradores y superadministradores.

Filtros disponibles:

fecha_desde
fecha_hasta
modulo
evento
actor

Las fechas son inclusivas. modulo y evento usan coincidencia exacta. actor realiza búsqueda parcial por nombre o email.

Los resultados se ordenan por fecha descendente e ID descendente y se paginan de a 10 registros.

Exportación PDF

Los usuarios autorizados pueden exportar el conjunto filtrado a PDF.

La exportación utiliza los mismos filtros que la consulta y genera documentos DomPDF en A4 horizontal con numeración de páginas.

El nombre de archivo sigue el formato:

kinesiosoft_auditoria_YYYYMMDD_HHMMSS.pdf

Si no existen resultados, no se genera un PDF vacío ni se registra una exportación inexistente.

Una exportación correcta registra:

auditoria_exportada_pdf

El conjunto del PDF se obtiene antes de registrar la propia exportación, evitando que el evento se incluya a sí mismo.

Frontend y JavaScript reutilizable

KinesioSoft utiliza Blade, Tailwind CSS y JavaScript administrado mediante Vite.

Las interacciones globales utilizan SweetAlert2 y los selects avanzados Tom Select.

Entre los componentes reutilizables se encuentran:

resources/js/components/tom-select.js
resources/js/components/jurisdiccion-colegio.js

La inicialización general se realiza desde:

resources/js/app.js

La dependencia entre jurisdicción y colegio emisor se implementa mediante atributos data-\* en las vistas Blade para evitar lógica JavaScript duplicada.

Pruebas

KinesioSoft utiliza PHPUnit.

La suite cubre autenticación, registro, verificación de correo, usuarios activos e inactivos, perfiles, matrículas profesionales, gestión administrativa de usuarios, Policies, catálogos, Actions, auditoría, exportación PDF, seeders y restricciones de conservación histórica e integridad.

Para ejecutar toda la suite:

php artisan test

Última validación integral:

468 tests passed
2373 assertions

Para ejecutar un archivo específico:

php artisan test tests/Feature/NombreDelTest.php

Por ejemplo:

php artisan test tests/Feature/Seeders/SuperadminUserSeederTest.php

Validación de whitespace:

git diff --check

Validación frontend:

npm run build

Consideraciones de desarrollo

Base de datos de pruebas

Las pruebas automatizadas deben ejecutarse sobre un entorno de testing separado.

No debe utilizarse una base de desarrollo con información que deba conservarse para operaciones destructivas como:

php artisan migrate:fresh

Para comprobar una instalación limpia puede utilizarse una base temporal independiente.

Seeders y datos existentes

Los seeders están orientados principalmente a inicializar una instalación. No debe asumirse que sincronizan automáticamente todos los registros existentes cuando se modifica posteriormente su configuración.

En particular, SuperadminUserSeeder no debe utilizarse para cambiar la contraseña de un superadministrador existente.

Contraseñas y secretos

Nunca deben versionarse contraseñas reales, credenciales privadas de base de datos, tokens, claves privadas, secretos del entorno o contenido sensible de .env.

.env.example debe contener únicamente valores seguros para compartir.

Almacenamiento público

La instalación actual no depende de public/storage.

Si futuros módulos incorporan archivos públicos gestionados mediante el disco public, deberá evaluarse:

php artisan storage:link

Instalación reproducible

Orden recomendado:

1. Instalar dependencias PHP.
2. Crear y configurar .env.
3. Generar APP_KEY.
4. Crear la base MySQL.
5. Configurar KINESIOSOFT*SUPERADMIN*\*.
6. Ejecutar php artisan migrate --seed.
7. Instalar dependencias frontend.
8. Ejecutar npm run dev o npm run build.
9. Levantar la aplicación.

Estructura de documentación

Este README documenta principalmente instalación, entorno técnico, arquitectura transversal, roles y autorización, gestión inicial de datos, auditoría, frontend reutilizable, pruebas y consideraciones de desarrollo.

La documentación académica de análisis y diseño se mantiene separada e incluye, según corresponda:

Requisitos.

Casos de uso.

Casos de uso extendidos.

Diagramas de secuencia del sistema.

Diagramas de clases.

Modelo relacional.

Diseño de subsistemas.

Artefactos asociados al Proceso Unificado.

KinesioSoft

Sistema de gestión para consultorio de kinesiología desarrollado como proyecto académico de Analista de Sistemas.
