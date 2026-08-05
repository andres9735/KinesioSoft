# Matriz de permisos de KinesioSoft

## 1. Objetivo

Este documento define qué operaciones puede realizar cada rol de KinesioSoft.

La matriz servirá como especificación para:

- middlewares;
- Policies de Laravel;
- permisos de Spatie;
- pruebas de autorización;
- restricciones de cambios de rol;
- navegación y visibilidad de acciones.

## 2. Roles del sistema

| Rol             | Descripción                                                                                    |
| --------------- | ---------------------------------------------------------------------------------------------- |
| `superadmin`    | Máxima autoridad administrativa del sistema.                                                   |
| `administrador` | Gestiona usuarios y catálogos, con restricciones sobre administradores y superadministradores. |
| `kinesiologo`   | Gestiona su perfil profesional y sus propias matrículas.                                       |
| `paciente`      | Gestiona su perfil personal.                                                                   |

Los nombres oficiales se encuentran centralizados en:

`app/Enums/RoleEnum.php`

## 3. Convenciones

| Símbolo     | Significado                                           |
| ----------- | ----------------------------------------------------- |
| Sí          | Puede realizar la operación.                          |
| No          | No puede realizar la operación.                       |
| Propio      | Solo puede operar sobre sus propios datos o recursos. |
| Condicional | Depende del estado del usuario o del recurso.         |
| Pendiente   | Requiere revisar reglas adicionales del controlador.  |

## 4. Acceso general actual

| Área u operación             | Superadmin | Administrador | Kinesiólogo | Paciente | Protección actual                     |
| ---------------------------- | ---------: | ------------: | ----------: | -------: | ------------------------------------- |
| Página pública de inicio     |         Sí |            Sí |          Sí |       Sí | Sin autenticación                     |
| Iniciar sesión               |         Sí |            Sí |          Sí |       Sí | Rutas de autenticación                |
| Cerrar sesión                |         Sí |            Sí |          Sí |       Sí | Usuario autenticado                   |
| Dashboard                    |         Sí |            Sí |          Sí |       Sí | `auth`, `verified`, `perfil.completo` |
| Editar perfil general        |     Propio |        Propio |      Propio |   Propio | `auth`, `perfil.completo`             |
| Actualizar perfil general    |     Propio |        Propio |      Propio |   Propio | `auth`, `perfil.completo`             |
| Completar perfil de paciente |         No |            No |          No |   Propio | `auth`, `perfil.paciente`             |
| Completar perfil profesional |         No |            No |      Propio |       No | `auth`, `perfil.kinesiologo`          |

## 5. Gestión de usuarios

### 5.1 Matriz actual

| Operación                                                  |  Superadmin | Administrador | Kinesiólogo | Paciente |
| ---------------------------------------------------------- | ----------: | ------------: | ----------: | -------: |
| Listar todos los usuarios                                  |          Sí |            Sí |          No |       No |
| Buscar y filtrar usuarios                                  |          Sí |            Sí |          No |       No |
| Ver formulario de creación                                 |          Sí |            Sí |          No |       No |
| Crear paciente                                             |          Sí |            Sí |          No |       No |
| Crear kinesiólogo                                          |          Sí |            Sí |          No |       No |
| Crear administrador directamente                           |          Sí |            No |          No |       No |
| Crear superadministrador directamente                      |          No |            No |          No |       No |
| Promover un usuario a superadministrador                   |          Sí |            No |          No |       No |
| Editar datos de paciente                                   |          Sí |            Sí |          No |       No |
| Editar datos de kinesiólogo                                |          Sí |            Sí |          No |       No |
| Editar datos de administrador                              |          Sí |            No |          No |       No |
| Editar datos básicos de superadministrador                 |          Sí |            No |          No |       No |
| Cambiar paciente a kinesiólogo                             |          Sí |            Sí |          No |       No |
| Cambiar kinesiólogo a paciente                             |          Sí |            Sí |          No |       No |
| Cambiar paciente o kinesiólogo a administrador             |          Sí |            No |          No |       No |
| Cambiar paciente, kinesiólogo o administrador a superadmin |          Sí |            No |          No |       No |
| Quitar el rol a un superadministrador existente            |          No |            No |          No |       No |
| Activar o inactivar paciente                               |          Sí |            Sí |          No |       No |
| Activar o inactivar kinesiólogo                            |          Sí |            Sí |          No |       No |
| Activar o inactivar administrador                          |          Sí |            No |          No |       No |
| Activar superadministrador                                 |          Sí |            No |          No |       No |
| Inactivar superadministrador                               | Condicional |            No |          No |       No |
| Inactivar al único superadministrador activo               |          No |            No |          No |       No |
| Eliminar físicamente una cuenta                            |          No |            No |          No |       No |

### 5.2 Restricción sobre administradores

Un administrador no puede editar ni cambiar el estado de un usuario que tenga rol:

- `administrador`;
- `superadmin`.

Esta restricción se aplica actualmente dentro de `UserController`.

### 5.3 Restricción sobre superadministradores

Un superadministrador existente no puede cambiarse a otro rol desde la pantalla de usuarios.

Sin embargo, un superadministrador puede promover un usuario que todavía no sea superadministrador al rol `superadmin`.

### 5.4 Superadministrador activo mínimo

Cuando se intenta inactivar un superadministrador, el sistema cuenta los superadministradores activos.

La operación se rechaza cuando solo queda uno activo.

### 5.5 Consecuencias actuales del cambio de rol

Cuando el rol cambia a `paciente`:

- se sincroniza el nuevo rol;
- se crea un registro `Paciente` cuando todavía no existe;
- se genera un número de historia clínica con formato `HC-######`.

Cuando el rol cambia a `kinesiologo`:

- se sincroniza el nuevo rol;
- no se crea automáticamente un registro `Kinesiologa`;
- el usuario deberá completar posteriormente su perfil profesional.

Cuando el rol cambia a `administrador` o `superadmin`:

- se sincroniza el nuevo rol;
- no se eliminan automáticamente los perfiles anteriores.

Actualmente el cambio de rol no:

- elimina el perfil anterior;
- elimina matrículas;
- elimina especialidades;
- revoca explícitamente las sesiones activas;
- registra el motivo del cambio;
- genera un evento de auditoría.

## 6. Matrículas profesionales

### 6.1 Matriz actual

| Operación                                     | Superadmin | Administrador | Kinesiólogo | Paciente |
| --------------------------------------------- | ---------: | ------------: | ----------: | -------: |
| Listar matrículas propias                     |         No |            No |      Propio |       No |
| Ver formulario de nueva matrícula             |         No |            No |      Propio |       No |
| Crear matrícula propia                        |         No |            No |      Propio |       No |
| Ver formulario de edición de matrícula propia |         No |            No |      Propio |       No |
| Ver formulario de matrícula ajena             |         No |            No |          No |       No |
| Actualizar matrícula propia                   |         No |            No |      Propio |       No |
| Actualizar matrícula ajena                    |         No |            No |          No |       No |
| Activar matrícula propia                      |         No |            No |      Propio |       No |
| Inactivar matrícula propia                    |         No |            No | Condicional |       No |
| Activar o inactivar matrícula ajena           |         No |            No |          No |       No |
| Inactivar la última matrícula activa          |         No |            No |          No |       No |

### 6.2 Autorización actual

La autorización está implementada dentro de
`MisMatriculasProfesionalesController`.

El método `obtenerKinesiologaAutenticada()` exige que:

1. exista un usuario autenticado;
2. tenga rol `kinesiologo`;
3. posea un perfil profesional asociado.

Para las operaciones sobre una matrícula existente se exige además:

`matricula.kinesiologa_id === kinesiologa_autenticada.id`

### 6.3 Restricción de última matrícula activa

Una kinesióloga no puede inactivar su última matrícula profesional activa.

Puede inactivar una matrícula cuando conserva al menos otra matrícula activa.

## 7. Perfil general

| Operación                       | Superadmin | Administrador | Kinesiólogo | Paciente |
| ------------------------------- | ---------: | ------------: | ----------: | -------: |
| Ver su perfil general           |     Propio |        Propio |      Propio |   Propio |
| Actualizar nombre propio        |     Propio |        Propio |      Propio |   Propio |
| Actualizar apellido propio      |     Propio |        Propio |      Propio |   Propio |
| Actualizar correo propio        |     Propio |        Propio |      Propio |   Propio |
| Actualizar perfil general ajeno |         No |            No |          No |       No |
| Eliminar su propia cuenta       |         No |            No |          No |       No |

El perfil general utiliza siempre al usuario autenticado obtenido desde la petición.

Cuando el correo electrónico cambia:

- `email_verified_at` se establece en `null`;
- el nuevo correo queda pendiente de verificación.

La ruta exige actualmente:

- `auth`;
- `perfil.completo`.

No exige explícitamente el middleware `verified`.

## 8. Catálogos administrativos

La protección actual de los catálogos está centralizada mediante:

- `auth`;
- `perfil.completo`;
- `admin.or.superadmin`.

| Catálogo u operación               | Superadmin | Administrador | Kinesiólogo | Paciente |
| ---------------------------------- | ---------: | ------------: | ----------: | -------: |
| Acceder al módulo de catálogos     |         Sí |            Sí |          No |       No |
| Listar jurisdicciones              |         Sí |            Sí |          No |       No |
| Crear jurisdicciones               |         Sí |            Sí |          No |       No |
| Editar jurisdicciones              |         Sí |            Sí |          No |       No |
| Activar o inactivar jurisdicciones |         Sí |            Sí |          No |       No |
| Listar títulos profesionales       |         Sí |            Sí |          No |       No |
| Crear títulos profesionales        |         Sí |            Sí |          No |       No |
| Editar títulos profesionales       |         Sí |            Sí |          No |       No |
| Activar o inactivar títulos        |         Sí |            Sí |          No |       No |
| Listar colegios emisores           |         Sí |            Sí |          No |       No |
| Crear colegios emisores            |         Sí |            Sí |          No |       No |
| Editar colegios emisores           |         Sí |            Sí |          No |       No |
| Activar o inactivar colegios       |         Sí |            Sí |          No |       No |
| Listar especialidades              |         Sí |            Sí |          No |       No |
| Crear especialidades               |         Sí |            Sí |          No |       No |
| Editar especialidades              |         Sí |            Sí |          No |       No |
| Activar o inactivar especialidades |         Sí |            Sí |          No |       No |

## 9. Reglas transversales

### 9.1 Usuario activo

Todo usuario inactivo debe perder su sesión y no puede volver a autenticarse.

### 9.2 Perfil completo

Los pacientes y kinesiólogos con perfil incompleto solo pueden acceder al formulario correspondiente para completar su perfil y cerrar sesión.

### 9.3 Correo verificado

El middleware `verified` se aplica actualmente de forma explícita únicamente al dashboard.

No se encuentra aplicado explícitamente a:

- completar perfil de paciente;
- completar perfil profesional;
- perfil general;
- matrículas profesionales;
- gestión de usuarios;
- gestión de catálogos.

Esto permite que un usuario autenticado con correo no verificado acceda potencialmente a dichas áreas, siempre que cumpla los demás middlewares y condiciones.

La política objetivo se encuentra adoptada en la sección 12.6. Su implementación deberá extender el middleware `verified` a todas las áreas privadas y operativas, conservando únicamente las excepciones allí documentadas.

### 9.4 Propiedad del recurso

Una kinesióloga solo puede operar sobre sus propias matrículas profesionales.

### 9.5 Eliminación física

La eliminación física de cuentas de usuario está deshabilitada.

### 9.6 Superadministrador mínimo

Debe existir al menos un superadministrador activo.

## 10. Observaciones arquitectónicas iniciales

### 10.1 Matrículas profesionales

Las rutas de matrículas poseen actualmente los middlewares:

- `auth`;
- `perfil.completo`.

No poseen explícitamente el middleware `perfil.kinesiologo`.

La autorización por rol y propiedad debe verificarse en el controlador y posteriormente trasladarse a una Policy.

### 10.2 Administración

El middleware `admin.or.superadmin` autoriza el acceso general al módulo administrativo.

Las diferencias entre administrador y superadministrador se resuelven actualmente dentro de los controladores y deberán representarse mediante Policies o permisos específicos.

### 10.3 Middleware y Policies

La estrategia objetivo será:

- middleware para restringir áreas generales;
- Policies para autorizar acciones sobre recursos concretos;
- permisos para representar capacidades específicas;
- reglas de dominio para restricciones dependientes del estado.

## 11. Archivos que respaldan esta matriz

- `app/Enums/RoleEnum.php`
- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Middleware/EnsureAdminOrSuperadmin.php`
- `app/Http/Middleware/EnsureUserIsKinesiologist.php`
- `app/Http/Middleware/EnsureUserIsPatient.php`
- `app/Http/Middleware/EnsureUserProfileIsComplete.php`

## 12. Política objetivo y decisiones arquitectónicas

Las decisiones de esta sección pueden encontrarse en uno de los siguientes estados:

- **Pendiente:** todavía requiere una definición funcional.
- **Adoptada:** ya forma parte de la política objetivo.
- **Implementada:** además de estar adoptada, ya se encuentra aplicada y probada en el código.

Las reglas documentadas como adoptadas no describen necesariamente el comportamiento actual. Representan el comportamiento que deberá alcanzarse durante los siguientes incrementos arquitectónicos.

### 12.1 Creación y promoción de superadministradores

**Estado: decisión adoptada.**

KinesioSoft no permitirá crear usuarios con rol `superadmin` desde el formulario común de creación de usuarios.

La asignación del rol `superadmin` se realizará únicamente mediante una acción específica de promoción.

#### Reglas adoptadas

- Solo un superadministrador activo podrá promover a otro usuario.
- No se ofrecerá el rol `superadmin` dentro del formulario común de creación.
- La promoción deberá ejecutarse mediante una acción separada de la edición general del usuario.
- El usuario que realiza la promoción deberá confirmar nuevamente su contraseña.
- La acción deberá requerir una confirmación explícita antes de ejecutarse.
- La promoción deberá registrarse obligatoriamente en la auditoría.
- El registro de auditoría deberá identificar:
    - al superadministrador que realizó la promoción;
    - al usuario promovido;
    - el rol anterior;
    - el nuevo rol;
    - la fecha y hora;
    - el motivo ingresado.
- Un superadministrador no podrá quitarse a sí mismo el rol.
- La operación deberá ejecutarse dentro de una transacción de base de datos.

#### Diferencia respecto del comportamiento actual

Actualmente un superadministrador puede promover a otro usuario cambiando su rol desde el formulario general de edición.

Esta posibilidad deberá eliminarse cuando se implemente la política objetivo. La promoción quedará disponible únicamente mediante la acción específica y auditada.

### 12.2 Modificación entre superadministradores

**Estado: decisión adoptada.**

Un superadministrador no podrá editar los datos personales ni el correo electrónico de otro superadministrador desde el formulario general de gestión de usuarios.

#### Reglas adoptadas

- Cada superadministrador administrará sus propios datos mediante su perfil general.
- Un superadministrador podrá visualizar a los demás superadministradores en el listado administrativo.
- La pantalla general de edición no estará disponible para modificar a otro superadministrador.
- No se permitirá cambiar el nombre, apellido o correo electrónico de otro superadministrador mediante `usuarios.update`.
- El rol de un superadministrador no podrá modificarse desde la edición general.
- La activación o inactivación será una operación independiente y estará sujeta a las reglas de seguridad correspondientes.
- La promoción de nuevos superadministradores se realizará únicamente mediante la acción específica definida en la sección 12.1.

#### Correcciones excepcionales

Una futura corrección administrativa sobre la cuenta de otro superadministrador deberá:

- realizarse mediante una acción específica;
- requerir confirmación de la contraseña del operador;
- exigir un motivo;
- registrarse obligatoriamente en la auditoría;
- identificar los valores anteriores y posteriores;
- notificar al titular de la cuenta afectada;
- ejecutarse dentro de una transacción.

La modificación del correo electrónico de otro superadministrador no estará disponible como una operación administrativa ordinaria.

#### Diferencia respecto del comportamiento actual

Actualmente `UserController` permite que un superadministrador abra el formulario de edición de otro superadministrador y modifique su nombre, apellido y correo electrónico, manteniendo obligatoriamente el rol `superadmin`.

Esta posibilidad deberá eliminarse al implementar la política objetivo.

### 12.3 Autoedición y autoinactivación

**Estado: decisión adoptada.**

Un superadministrador podrá administrar sus propios datos personales mediante el perfil general, pero no desde el formulario administrativo de gestión de usuarios.

Un superadministrador no podrá inactivar su propia cuenta.

#### Autoedición

Se adoptan las siguientes reglas:

- El superadministrador podrá modificar su propio nombre, apellido y correo electrónico.
- La modificación deberá realizarse mediante el perfil general.
- La edición administrativa de usuarios no deberá utilizarse para modificar la propia cuenta.
- El superadministrador no podrá modificar su propio rol.
- El cambio de correo electrónico dejará la cuenta pendiente de verificación.
- Las operaciones sensibles podrán requerir confirmación de contraseña reciente.
- Los cambios relevantes deberán quedar registrados en la auditoría.

#### Autoinactivación

Se adoptan las siguientes reglas:

- Un superadministrador no podrá inactivar su propia cuenta.
- La interfaz no deberá mostrar la acción de inactivación sobre la propia cuenta.
- Una petición manual destinada a inactivar la propia cuenta deberá ser rechazada por el servidor.
- La inactivación deberá ser realizada por otro superadministrador activo.
- Después de la operación deberá permanecer al menos un superadministrador activo.
- La inactivación deberá requerir confirmación explícita.
- La operación deberá exigir un motivo.
- La operación deberá registrarse obligatoriamente en la auditoría.
- Al inactivar la cuenta se deberán revocar sus sesiones activas y su token de recordatorio.
- La operación deberá ejecutarse dentro de una transacción.

#### Reactivación

La reactivación de un superadministrador inactivo deberá:

- ser realizada por otro superadministrador activo;
- requerir confirmación explícita;
- registrarse en la auditoría;
- incluir el motivo de la reactivación.

#### Diferencia respecto del comportamiento actual

Actualmente un superadministrador puede editar su propia cuenta desde la gestión administrativa y puede inactivarse cuando existe otro superadministrador activo.

La política objetivo trasladará la autoedición al perfil general y prohibirá completamente la autoinactivación.

### 12.4 Cambios de rol

**Estado: decisión adoptada.**

KinesioSoft utilizará un único rol principal por usuario.

Todo cambio de rol deberá distinguir entre:

- cambios funcionales ordinarios;
- promociones administrativas;
- degradaciones administrativas;
- promociones o degradaciones de superadministradores.

#### Principios generales

- Un usuario deberá poseer exactamente un rol principal después de la operación.
- Ningún usuario podrá cambiar su propio rol.
- Los cambios de rol deberán realizarse únicamente por operadores autorizados.
- Los perfiles y registros históricos no se eliminarán como consecuencia del cambio.
- Cuando el usuario vuelva a un rol anterior, se reutilizará el perfil existente.
- Todo cambio efectivo de rol revocará las sesiones activas y el token de recordatorio.
- La operación deberá ejecutarse dentro de una transacción.
- Deberá registrarse el operador, usuario afectado, rol anterior, rol nuevo, fecha, hora y motivo.
- La modificación deberá quedar obligatoriamente registrada en la auditoría.
- El estado activo o inactivo del usuario no cambiará automáticamente junto con el rol.

#### Matriz objetivo de transiciones

| Rol actual      | Rol nuevo       |   Permitido | Operador autorizado        | Mecanismo              |
| --------------- | --------------- | ----------: | -------------------------- | ---------------------- |
| `paciente`      | `kinesiologo`   |          Sí | Administrador o superadmin | Cambio ordinario       |
| `kinesiologo`   | `paciente`      |          Sí | Administrador o superadmin | Cambio ordinario       |
| `paciente`      | `administrador` |          Sí | Superadmin                 | Promoción específica   |
| `kinesiologo`   | `administrador` |          Sí | Superadmin                 | Promoción específica   |
| `administrador` | `paciente`      |          Sí | Superadmin                 | Degradación específica |
| `administrador` | `kinesiologo`   |          Sí | Superadmin                 | Degradación específica |
| `paciente`      | `superadmin`    |          Sí | Otro superadmin activo     | Promoción crítica      |
| `kinesiologo`   | `superadmin`    |          Sí | Otro superadmin activo     | Promoción crítica      |
| `administrador` | `superadmin`    |          Sí | Otro superadmin activo     | Promoción crítica      |
| `superadmin`    | `administrador` | Condicional | Otro superadmin activo     | Degradación crítica    |
| `superadmin`    | `paciente`      | Condicional | Otro superadmin activo     | Degradación crítica    |
| `superadmin`    | `kinesiologo`   | Condicional | Otro superadmin activo     | Degradación crítica    |

#### Cambio entre paciente y kinesiólogo

Un administrador o superadministrador podrá realizar las transiciones:

- `paciente` a `kinesiologo`;
- `kinesiologo` a `paciente`.

Cuando el nuevo rol sea `paciente`:

- se reutilizará el perfil `Paciente` existente;
- si no existe, deberá crearse;
- deberá generarse el número de historia clínica mediante el servicio central correspondiente;
- el perfil profesional anterior no será eliminado;
- las matrículas y especialidades anteriores se conservarán como información histórica.

Cuando el nuevo rol sea `kinesiologo`:

- se reutilizará el perfil `Kinesiologa` existente;
- si no existe, el usuario deberá completar el perfil profesional;
- el perfil de paciente anterior no será eliminado;
- la historia clínica existente se conservará.

El acceso a cada perfil dependerá siempre del rol actual.

#### Promoción a administrador

La promoción a `administrador`:

- solo podrá ser realizada por un superadministrador;
- se realizará mediante una acción específica;
- no estará incluida en el formulario general de edición;
- requerirá confirmación de contraseña reciente;
- exigirá confirmación explícita y motivo;
- revocará las sesiones activas del usuario promovido;
- quedará obligatoriamente auditada.

Los perfiles anteriores del usuario se conservarán, pero no otorgarán acceso mientras el rol actual sea `administrador`.

#### Degradación de administrador

La degradación de un administrador:

- solo podrá ser realizada por un superadministrador;
- se realizará mediante una acción específica;
- podrá asignar como nuevo rol `paciente` o `kinesiologo`;
- deberá garantizar la existencia del perfil requerido;
- requerirá confirmación explícita y motivo;
- revocará las sesiones activas;
- quedará obligatoriamente auditada.

#### Promoción a superadministrador

La promoción a `superadmin` seguirá la política establecida en la sección 12.1.

No estará disponible en el formulario general de edición de usuarios.

#### Degradación de superadministrador

Un superadministrador no podrá quitarse a sí mismo el rol.

Otro superadministrador activo podrá degradarlo únicamente cuando:

- después de la operación permanezca al menos un superadministrador activo;
- confirme nuevamente su contraseña;
- seleccione el nuevo rol;
- ingrese un motivo;
- confirme explícitamente la operación;
- la operación quede registrada en la auditoría;
- se revoquen inmediatamente las sesiones del usuario afectado.

La degradación se realizará mediante una acción crítica independiente del formulario general de edición.

#### Conservación histórica

Los cambios de rol no eliminarán:

- perfiles de paciente;
- perfiles profesionales;
- números de historia clínica;
- matrículas profesionales;
- especialidades;
- relaciones clínicas;
- registros de auditoría.

Estos datos permanecerán asociados al usuario para preservar la trazabilidad.

La existencia de un perfil histórico no otorgará permisos. Los permisos dependerán exclusivamente del rol actual y de las reglas de autorización.

#### Diferencia respecto del comportamiento actual

Actualmente los cambios de rol se realizan desde el formulario general de edición.

El sistema:

- crea el perfil de paciente cuando resulta necesario;
- no crea automáticamente el perfil profesional;
- conserva los perfiles anteriores;
- no solicita un motivo;
- no exige confirmación reforzada;
- no revoca explícitamente las sesiones;
- permite promociones administrativas desde la edición común.

La política objetivo separará las transiciones ordinarias de las promociones y degradaciones sensibles.

### 12.5 Administración de matrículas profesionales

**Estado: decisión adoptada.**

La titular de una matrícula profesional será responsable de registrar y mantener sus propios datos.

Los roles administrativos podrán consultar y validar matrículas, pero no modificar ordinariamente los datos declarados por la profesional.

#### Principios generales

- Una kinesióloga solo podrá administrar sus propias matrículas.
- Administradores y superadministradores podrán consultar las matrículas registradas.
- La creación de una matrícula corresponderá exclusivamente a la kinesióloga titular.
- No se permitirá la eliminación física de matrículas.
- Las matrículas deberán conservarse como información histórica.
- Toda intervención administrativa deberá quedar auditada.
- La propiedad del recurso deberá comprobarse mediante una Policy.
- Los estados de actividad y validación administrativa deberán tratarse por separado.

#### Matriz objetivo

| Operación                                   | Superadmin | Administrador | Kinesiólogo | Paciente |
| ------------------------------------------- | ---------: | ------------: | ----------: | -------: |
| Listar todas las matrículas                 |         Sí |            Sí |          No |       No |
| Listar matrículas propias                   |         No |            No |      Propio |       No |
| Consultar detalle de cualquier matrícula    |         Sí |            Sí |          No |       No |
| Consultar detalle de matrícula propia       |         No |            No |      Propio |       No |
| Crear matrícula propia                      |         No |            No |      Propio |       No |
| Editar matrícula propia                     |         No |            No |      Propio |       No |
| Editar matrícula ajena ordinariamente       |         No |            No |          No |       No |
| Activar matrícula propia                    |         No |            No |      Propio |       No |
| Inactivar matrícula propia                  |         No |            No | Condicional |       No |
| Verificar documentación                     |         Sí |            Sí |          No |       No |
| Observar documentación                      |         Sí |            Sí |          No |       No |
| Rechazar documentación                      |         Sí |            Sí |          No |       No |
| Realizar corrección excepcional             |         Sí |            No |          No |       No |
| Invalidar administrativamente una matrícula |         Sí |            No |          No |       No |
| Eliminar físicamente una matrícula          |         No |            No |          No |       No |

#### Actividad declarada por la profesional

El campo que representa si una matrícula está activa indicará si la kinesióloga declara que actualmente la utiliza para ejercer.

La kinesióloga podrá:

- activar una matrícula propia;
- inactivar una matrícula propia;
- conservar varias matrículas activas en distintas jurisdicciones;
- mantener matrículas inactivas como antecedentes históricos.

No podrá inactivar su última matrícula activa mientras su perfil profesional requiera al menos una matrícula habilitada.

Administradores y superadministradores no utilizarán esta acción para modificar ordinariamente la selección profesional de matrículas activas.

#### Validación administrativa

La validación administrativa se representará mediante un estado independiente del campo de actividad.

Los estados previstos serán:

- `pendiente`;
- `verificada`;
- `observada`;
- `rechazada`.

Un administrador o superadministrador podrá:

- consultar los datos declarados;
- revisar la documentación respaldatoria;
- marcar la matrícula como verificada;
- formular una observación;
- rechazar la documentación;
- registrar el motivo de la decisión.

Toda decisión deberá registrar:

- operador;
- matrícula afectada;
- estado anterior;
- estado nuevo;
- fecha y hora;
- motivo;
- observaciones;
- documentación revisada, cuando corresponda.

Una kinesióloga no podrá verificar su propia matrícula.

#### Observaciones y correcciones

Cuando un administrador detecte datos incorrectos:

- no modificará directamente la matrícula;
- marcará la documentación como observada;
- indicará el motivo;
- solicitará la corrección a la kinesióloga;
- la profesional modificará posteriormente su propia matrícula.

Esta regla preserva la autoría de los datos declarados.

#### Corrección excepcional por superadministrador

Un superadministrador podrá realizar una corrección excepcional únicamente cuando:

- exista un error institucional comprobable;
- la profesional no pueda realizar la corrección;
- exista una inconsistencia que afecte la integridad del sistema;
- la acción sea necesaria para resolver un incidente documentado.

La corrección deberá:

- realizarse mediante una acción específica;
- requerir confirmación de contraseña reciente;
- exigir confirmación explícita;
- exigir un motivo;
- registrar valores anteriores y posteriores;
- notificarse a la titular;
- quedar obligatoriamente auditada;
- ejecutarse dentro de una transacción.

Esta capacidad no formará parte del formulario ordinario de edición.

#### Invalidación administrativa

Una matrícula podrá ser invalidada administrativamente cuando exista:

- documentación fraudulenta;
- matrícula inexistente;
- revocación comunicada por la autoridad competente;
- duplicación indebida;
- error grave que impida considerarla válida.

La invalidación:

- estará reservada al superadministrador;
- no eliminará físicamente la matrícula;
- deberá conservar todos sus antecedentes;
- requerirá motivo y evidencia;
- revocará su condición de matrícula utilizable;
- será notificada a la titular;
- quedará obligatoriamente auditada.

La invalidación administrativa deberá diferenciarse de la inactivación voluntaria realizada por la kinesióloga.

#### Conservación histórica

Los siguientes datos no deberán eliminarse como consecuencia de una observación, rechazo o invalidación:

- número de matrícula;
- jurisdicción;
- colegio emisor;
- titular;
- estados anteriores;
- motivos;
- documentación asociada;
- registros de auditoría.

Los catálogos inactivos utilizados históricamente deberán seguir siendo visibles en el detalle de la matrícula.

#### Autorización objetivo

La autorización se distribuirá de la siguiente manera:

- middleware para restringir el área administrativa;
- `MatriculaProfesionalKinesiologiaPolicy` para acciones sobre cada matrícula;
- permisos de Spatie para consulta, validación e intervención excepcional;
- servicios o acciones de dominio para validar, observar, rechazar e invalidar.

#### Diferencia respecto del comportamiento actual

Actualmente:

- solo la kinesióloga consulta sus propias matrículas;
- administradores y superadministradores no pueden consultarlas;
- no existe validación administrativa;
- no existen estados de verificación;
- no existe una acción de corrección excepcional;
- la actividad y la validez administrativa no se encuentran diferenciadas.

La política objetivo incorporará consulta administrativa y validación sin quitar a la profesional la responsabilidad ordinaria sobre sus datos.

### 12.6 Correo electrónico verificado

**Estado: decisión adoptada.**

Todos los usuarios humanos de KinesioSoft deberán verificar su dirección de correo electrónico antes de acceder a funcionalidades operativas o información privada del sistema.

Esta obligación se aplicará por igual a los roles:

- `superadmin`;
- `administrador`;
- `kinesiologo`;
- `paciente`.

#### Alcance de la verificación

El correo verificado será obligatorio para acceder a:

- dashboard;
- completar perfil de paciente;
- completar perfil profesional;
- perfil general;
- matrículas profesionales;
- gestión de usuarios;
- gestión de catálogos;
- futuros módulos de pacientes;
- turnos;
- historias clínicas;
- consultas;
- evoluciones;
- planes de tratamiento;
- cualquier otra funcionalidad autenticada que contenga información privada u operaciones del sistema.

#### Acciones permitidas sin verificación

Un usuario autenticado cuyo correo todavía no esté verificado solo podrá:

- visualizar el aviso de verificación;
- solicitar el reenvío del enlace de verificación;
- acceder al enlace firmado de verificación;
- corregir su dirección de correo mediante una acción específica y limitada;
- cerrar sesión.

No podrá completar perfiles, administrar recursos ni acceder al dashboard mientras continúe sin verificar.

#### Corrección de un correo no verificado

El sistema deberá proporcionar una acción específica para corregir una dirección ingresada incorrectamente antes de la verificación.

Esta acción:

- requerirá autenticación;
- solicitará la contraseña actual;
- solo permitirá modificar el correo electrónico;
- validará que el nuevo correo sea único;
- mantendrá `email_verified_at` en `null`;
- invalidará los enlaces de verificación asociados al correo anterior;
- enviará un nuevo mensaje de verificación;
- quedará registrada en la auditoría.

La corrección previa a la verificación no utilizará el formulario completo del perfil general.

#### Cambio de un correo ya verificado

Cuando un usuario con correo verificado cambie su dirección:

- deberá confirmar su contraseña actual;
- el nuevo correo deberá ser único;
- `email_verified_at` deberá establecerse en `null`;
- deberá enviarse un nuevo enlace de verificación;
- el usuario perderá temporalmente el acceso a las áreas protegidas;
- conservará únicamente las acciones permitidas a usuarios no verificados;
- las demás sesiones activas deberán revocarse;
- el cambio deberá quedar auditado;
- deberá notificarse la modificación a la dirección anterior, cuando sea técnicamente posible.

El acceso operativo se restablecerá después de verificar la nueva dirección.

#### Usuarios creados administrativamente

Cuando un administrador o superadministrador cree una cuenta:

- la cuenta se creará con el correo sin verificar;
- se enviará una notificación de verificación al titular;
- el operador no podrá marcar el correo como verificado manualmente;
- el usuario deberá demostrar el control del correo;
- la cuenta podrá existir como activa, pero no tendrá acceso operativo hasta completar la verificación.

La activación administrativa y la verificación del correo serán estados independientes.

#### Promociones y cambios de rol

Un usuario no podrá ser promovido a `administrador` o `superadmin` mientras su correo no esté verificado.

Para cambios entre `paciente` y `kinesiologo`, la cuenta deberá tener el correo verificado antes de ejecutar la transición.

La verificación no se perderá por un cambio de rol mientras el correo permanezca sin modificaciones.

#### Prohibición de verificación manual

Administradores y superadministradores no podrán asignar directamente un valor a `email_verified_at` para verificar a otro usuario desde la interfaz ordinaria.

Una eventual recuperación excepcional deberá:

- estar expresamente definida;
- requerir evidencia;
- exigir confirmación reforzada;
- registrar un motivo;
- quedar obligatoriamente auditada.

Hasta que esa operación excepcional exista formalmente, la verificación solo podrá completarse mediante el mecanismo de verificación enviado al correo del titular.

#### Protección de rutas

La política objetivo aplicará los middlewares:

- `auth`;
- `verified`;

a todas las áreas privadas y operativas.

Las rutas destinadas a completar perfiles añadirán además el middleware correspondiente al rol.

Las rutas administrativas conservarán también:

- `perfil.completo`;
- `admin.or.superadmin`.

Las rutas de matrículas profesionales conservarán:

- `perfil.completo`;
- `perfil.kinesiologo`.

#### Limitación de la verificación

La verificación del correo únicamente confirma el control de la dirección informada.

No acredita por sí misma:

- identidad personal;
- título profesional;
- matrícula profesional;
- vigencia de la matrícula;
- documentación presentada;
- autorización para ejercer.

Estas comprobaciones pertenecerán a procesos administrativos independientes.

#### Diferencia respecto del comportamiento actual

Actualmente el middleware `verified` se aplica explícitamente solo al dashboard.

Un usuario autenticado no verificado puede acceder potencialmente a otras rutas si cumple los middlewares restantes.

La política objetivo exigirá correo verificado para todas las áreas privadas y operativas, dejando disponibles únicamente el aviso, reenvío, corrección limitada del correo, verificación y cierre de sesión.

### 12.7 Recursos futuros

**Estado: decisión adoptada para los principios generales. Los permisos detallados deberán completarse con los casos de uso de cada módulo.**

Los módulos futuros deberán respetar una política de autorización basada en:

- rol;
- permiso específico;
- propiedad del recurso;
- relación asistencial;
- asignación profesional;
- estado del recurso;
- trazabilidad de la operación.

La pertenencia a un rol no será suficiente por sí sola para acceder a cualquier registro.

#### Principios generales adoptados

- Todo acceso estará denegado por defecto.
- Cada operación requerirá un permiso específico.
- Las Policies determinarán sobre qué registros concretos puede operar el usuario.
- Un paciente solo podrá acceder a información relacionada consigo mismo.
- Una kinesióloga solo podrá acceder a pacientes, turnos y recursos clínicos que estén bajo su atención o asignación.
- Un administrador podrá realizar operaciones administrativas y operativas, pero no acceder automáticamente al contenido clínico.
- Un superadministrador no tendrá acceso automático a historias clínicas, consultas, evoluciones o planes de tratamiento por el solo hecho de ser superadministrador.
- Los datos clínicos no se eliminarán físicamente.
- Las correcciones deberán preservar los valores históricos.
- Las acciones sensibles deberán quedar auditadas.
- La interfaz no reemplazará la autorización del servidor.
- Las consultas por URL deberán estar protegidas mediante Policies.
- El acceso requerirá usuario activo, correo verificado y perfil completo cuando corresponda.

#### Separación entre acceso administrativo y acceso clínico

Los permisos administrativos y clínicos se tratarán de forma independiente.

El rol `administrador` podrá gestionar:

- usuarios;
- catálogos;
- configuración operativa;
- información administrativa mínima de los pacientes;
- tareas no clínicas expresamente autorizadas.

El rol `administrador` no podrá, por defecto:

- redactar consultas;
- registrar evoluciones;
- modificar historias clínicas;
- crear o modificar planes de tratamiento;
- alterar observaciones clínicas;
- acceder a información clínica sin una finalidad expresamente autorizada.

El rol `superadmin` administrará la seguridad y configuración del sistema, pero no recibirá un acceso clínico universal.

No se implementará un bypass global que permita al superadministrador acceder automáticamente a todos los recursos clínicos.

#### Matriz preliminar de módulos futuros

| Recurso u operación                          |    Superadmin | Administrador |          Kinesiólogo | Paciente |
| -------------------------------------------- | ------------: | ------------: | -------------------: | -------: |
| Consultar datos personales propios           |        Propio |        Propio |               Propio |   Propio |
| Consultar datos administrativos de pacientes |   Condicional |            Sí |            Asignados |   Propio |
| Modificar datos clínicos de pacientes        |            No |            No |            Asignados |       No |
| Solicitar un turno                           |            No |            No |            Pendiente |   Propio |
| Consultar turnos propios                     |            No |            No |  Propios o asignados |   Propio |
| Gestionar agenda profesional                 |            No |     Pendiente |               Propio |       No |
| Confirmar o cancelar turno propio            |            No |     Pendiente |  Propios o asignados |   Propio |
| Consultar historia clínica                   | No automático |            No |  Pacientes asignados |   Propio |
| Crear o actualizar historia clínica          |            No |            No |  Pacientes asignados |       No |
| Registrar una consulta                       |            No |            No |               Propio |       No |
| Modificar una consulta clínica               |            No |            No | Propio y condicional |       No |
| Registrar una evolución                      |            No |            No |  Pacientes asignados |       No |
| Consultar seguimiento propio                 |            No |            No |  Pacientes asignados |   Propio |
| Crear un plan de tratamiento                 |            No |            No |  Pacientes asignados |       No |
| Modificar un plan de tratamiento             |            No |            No | Propio y condicional |       No |
| Consultar plan de tratamiento propio         |            No |            No |  Pacientes asignados |   Propio |
| Registrar cumplimiento de ejercicios         |            No |            No |            Pendiente |   Propio |
| Gestionar catálogos clínicos                 |   Condicional |            Sí |                   No |       No |
| Consultar y seleccionar catálogos clínicos   |   Condicional |            Sí |                   Sí |       No |
| Gestionar consultorios                       |     Pendiente |     Pendiente |            Pendiente |       No |
| Gestionar profesionales asociados            |     Pendiente |     Pendiente |            Pendiente |       No |

Los valores `Pendiente` deberán resolverse mediante los casos de uso correspondientes.

#### Pacientes

El paciente podrá:

- consultar y actualizar sus propios datos autorizados;
- solicitar turnos;
- consultar sus propios turnos;
- confirmar o cancelar sus propios turnos dentro de las reglas definidas;
- acceder a su propia historia clínica según el alcance funcional aprobado;
- consultar su propio seguimiento;
- consultar sus planes de tratamiento;
- visualizar ejercicios e indicaciones asignadas;
- registrar el cumplimiento de ejercicios cuando el módulo lo permita.

El paciente no podrá:

- consultar información de otros pacientes;
- modificar diagnósticos;
- modificar consultas;
- modificar evoluciones clínicas;
- modificar indicaciones profesionales;
- acceder a notas reservadas exclusivamente al profesional.

La definición de qué partes de la historia clínica serán visibles deberá establecerse en el caso de uso correspondiente.

#### Turnos

El paciente podrá solicitar y administrar únicamente sus propios turnos.

La kinesióloga podrá administrar:

- su propia agenda;
- los turnos que le hayan sido asignados;
- confirmaciones;
- cancelaciones;
- reprogramaciones;
- lista de espera;
- reservas de equipos relacionadas con su atención.

La administración operativa de turnos por parte del rol `administrador` deberá definirse en el caso de uso.

Una Policy deberá verificar:

- titular del turno;
- profesional asignado;
- estado del turno;
- ventana permitida para cancelar o reprogramar;
- relación con equipos o consultorios reservados.

#### Historias clínicas

La historia clínica será un recurso protegido.

El paciente podrá consultar únicamente su propia historia clínica dentro del alcance que defina el caso de uso.

La kinesióloga podrá consultar y modificar historias clínicas solamente cuando exista una relación asistencial válida con el paciente.

El administrador y el superadministrador no podrán acceder al contenido clínico por defecto.

Las operaciones deberán diferenciar:

- consulta;
- creación;
- actualización;
- incorporación de nuevos registros;
- corrección excepcional;
- exportación;
- impresión.

Las entradas históricas no deberán sobrescribirse cuando corresponda preservar su trazabilidad.

#### Consultas y evoluciones

Solo la kinesióloga autorizada podrá registrar consultas y evoluciones clínicas.

La autorización deberá verificar:

- profesional responsable;
- paciente atendido;
- turno o atención relacionada;
- estado de la consulta;
- posibilidad de edición según el tiempo transcurrido;
- existencia de una corrección posterior en lugar de sobrescribir información histórica.

El paciente podrá consultar el seguimiento propio que haya sido definido como visible.

El contenido reservado al profesional deberá permanecer restringido.

#### Planes de tratamiento

La kinesióloga podrá:

- crear planes para pacientes bajo su atención;
- consultar sus propios planes;
- modificarlos mientras el estado lo permita;
- seleccionar ejercicios y técnicas;
- ajustar parámetros terapéuticos;
- registrar cambios y finalización.

El paciente podrá:

- consultar su propio plan;
- visualizar instrucciones;
- consultar ejercicios asignados;
- registrar cumplimiento cuando corresponda.

El paciente no podrá modificar prescripciones, ejercicios, series, repeticiones, frecuencia ni duración.

Los planes anteriores deberán conservarse como antecedentes históricos.

#### Catálogos clínicos

El administrador podrá realizar:

- alta;
- edición;
- publicación;
- inactivación;
- control de vigencia.

La kinesióloga podrá:

- consultar elementos activos;
- seleccionarlos en consultas, planes y seguimientos;
- conservar referencias históricas a elementos inactivos.

No se eliminarán físicamente elementos que hayan sido utilizados.

La posibilidad de que una kinesióloga proponga nuevos elementos como borrador deberá definirse en un caso de uso específico.

#### Consultorios

Los permisos sobre consultorios todavía no están definidos en la documentación funcional disponible.

Antes de implementar el módulo deberá precisarse:

- quién crea y modifica consultorios;
- quién define horarios disponibles;
- quién asigna profesionales;
- quién reserva espacios;
- cómo se evitan superposiciones;
- qué relación existe con turnos y equipos;
- quién puede activar o inactivar un consultorio.

Hasta completar esa definición, el acceso permanecerá denegado por defecto.

#### Profesionales asociados

Los permisos sobre profesionales asociados también requieren un caso de uso específico.

Deberá definirse:

- quién registra al profesional;
- quién valida su vinculación;
- qué información puede consultar;
- qué pacientes puede atender;
- qué agenda puede gestionar;
- si utiliza su propia matrícula;
- cómo finaliza la asociación;
- qué ocurre con los registros clínicos generados durante la relación.

La finalización de una asociación no deberá eliminar los registros históricos creados por el profesional.

#### Auditoría clínica

Como mínimo deberán auditarse:

- creación y modificación de historias clínicas;
- creación y modificación de consultas;
- registro de evoluciones;
- creación y modificación de planes;
- correcciones clínicas;
- cambios de profesional responsable;
- exportaciones e impresiones;
- accesos excepcionales;
- cambios de asignación entre paciente y profesional.

La auditoría deberá identificar:

- usuario;
- rol;
- acción;
- recurso;
- fecha y hora;
- valores anteriores y posteriores cuando corresponda;
- motivo en operaciones sensibles.

#### Policies previstas

Los módulos futuros podrán requerir, como mínimo:

- `PacientePolicy`;
- `TurnoPolicy`;
- `HistoriaClinicaPolicy`;
- `ConsultaPolicy`;
- `EvolucionPolicy`;
- `PlanTratamientoPolicy`;
- `ConsultorioPolicy`;
- `ProfesionalAsociadoPolicy`.

Cada Policy combinará el permiso general con la propiedad, asignación o relación asistencial del recurso.

#### Decisiones que permanecen pendientes

Todavía deberán definirse mediante casos de uso:

- alcance exacto de la historia clínica visible para el paciente;
- administración operativa de turnos;
- posibilidad de reasignar pacientes entre profesionales;
- plazo permitido para modificar consultas y evoluciones;
- procedimiento de corrección clínica;
- permisos sobre consultorios;
- permisos sobre profesionales asociados;
- exportación e impresión de información clínica;
- acceso excepcional ante reemplazos o ausencias profesionales;
- reglas para pacientes atendidos por más de una kinesióloga.

### 12.8 Facultades sobre catálogos administrativos

**Estado: decisión adoptada.**

Los roles `administrador` y `superadmin` conservarán las mismas facultades para el mantenimiento ordinario de los catálogos administrativos actuales.

Las correcciones estructurales excepcionales quedarán reservadas al superadministrador.

#### Catálogos comprendidos

Esta política se aplicará inicialmente a:

- jurisdicciones;
- títulos profesionales;
- colegios emisores;
- especialidades de kinesiología.

#### Matriz objetivo

| Operación                                    |      Superadmin | Administrador | Kinesiólogo | Paciente |
| -------------------------------------------- | --------------: | ------------: | ----------: | -------: |
| Acceder al módulo de catálogos               |              Sí |            Sí |          No |       No |
| Listar registros                             |              Sí |            Sí |          No |       No |
| Consultar detalle                            |              Sí |            Sí |          No |       No |
| Crear registros                              |              Sí |            Sí |          No |       No |
| Editar datos ordinarios                      |              Sí |            Sí |          No |       No |
| Activar registros                            |              Sí |            Sí |          No |       No |
| Inactivar registros                          |              Sí |            Sí |          No |       No |
| Consultar referencias históricas             |              Sí |            Sí |          No |       No |
| Fusionar registros duplicados                | Sí, excepcional |            No |          No |       No |
| Reasignar referencias históricas             | Sí, excepcional |            No |          No |       No |
| Corregir relaciones estructurales utilizadas | Sí, excepcional |            No |          No |       No |
| Eliminar físicamente registros               |              No |            No |          No |       No |
| Omitir reglas de integridad                  |              No |            No |          No |       No |

#### Mantenimiento ordinario

Un administrador o superadministrador podrá:

- listar los registros;
- crear nuevos registros;
- modificar sus datos descriptivos;
- activar registros;
- inactivar registros;
- consultar su utilización histórica.

Estas operaciones deberán respetar las mismas validaciones de dominio para ambos roles.

El superadministrador no podrá omitir validaciones por el solo hecho de poseer un rol de mayor jerarquía.

#### Inactivación de catálogos

La inactivación seguirá disponible para administradores y superadministradores.

Antes de confirmar la operación, el sistema deberá informar cuando el registro esté relacionado con:

- perfiles profesionales;
- matrículas profesionales;
- colegios emisores;
- especialidades asignadas;
- cualquier otro recurso operativo o histórico.

La inactivación:

- no eliminará el registro;
- no romperá relaciones históricas;
- impedirá utilizarlo en nuevas asociaciones;
- permitirá conservarlo en formularios que deban mostrar una selección histórica existente;
- requerirá confirmación explícita;
- deberá registrar el motivo;
- quedará auditada.

La reactivación también deberá quedar auditada.

#### Edición de registros históricos

Los registros utilizados históricamente podrán recibir correcciones descriptivas ordinarias cuando estas no alteren su identidad ni el significado de las relaciones existentes.

Por ejemplo, podrán corregirse:

- errores ortográficos;
- uso de mayúsculas y minúsculas;
- siglas incorrectamente escritas;
- nombres incompletos;
- slugs derivados del nombre, cuando corresponda.

No deberá utilizarse la edición ordinaria para transformar un registro existente en una entidad diferente.

#### Relaciones estructurales

Una modificación estructural es aquella que cambia el significado de relaciones históricas.

Por ejemplo:

- trasladar un colegio emisor utilizado a otra jurisdicción;
- fusionar dos jurisdicciones;
- fusionar títulos profesionales duplicados;
- unificar especialidades que ya estén asignadas;
- reasignar matrículas desde un colegio hacia otro;
- modificar referencias históricas masivamente.

Estas operaciones no estarán disponibles en los formularios ordinarios.

#### Corrección estructural excepcional

Una corrección estructural excepcional solo podrá ser realizada por un superadministrador mediante una acción específica.

La operación deberá:

- requerir confirmación de contraseña reciente;
- exigir confirmación explícita;
- exigir un motivo;
- mostrar previamente los registros afectados;
- preservar la trazabilidad histórica;
- registrar valores anteriores y posteriores;
- ejecutarse dentro de una transacción;
- quedar obligatoriamente auditada;
- impedir resultados que violen restricciones de unicidad o integridad referencial.

Cuando la corrección pueda resolverse creando un nuevo registro e inactivando el anterior, se preferirá esa alternativa antes que modificar referencias históricas.

#### Registros duplicados

La detección de duplicados podrá estar disponible para administradores y superadministradores.

La decisión de fusionar registros duplicados quedará reservada al superadministrador.

Una fusión deberá definir:

- registro que se conservará;
- registro que quedará absorbido;
- relaciones que serán reasignadas;
- conflictos de unicidad;
- tratamiento de nombres, siglas y slugs;
- conservación del registro anterior;
- trazabilidad completa de la operación.

La fusión no implicará eliminación física sin conservación histórica.

#### Eliminación física

Ni el administrador ni el superadministrador podrán eliminar físicamente registros de catálogo utilizados por el sistema.

Los mecanismos admitidos serán:

- activación;
- inactivación;
- corrección controlada;
- fusión excepcional con conservación histórica.

#### Justificación de la igualdad ordinaria

La administración cotidiana de catálogos forma parte de las responsabilidades del rol `administrador`.

Reservar todas las acciones al superadministrador:

- generaría un cuello de botella operativo;
- reduciría la utilidad del rol administrador;
- concentraría tareas rutinarias en una cuenta de máxima autoridad;
- no aportaría una mejora proporcional de seguridad.

Por ello, ambos roles tendrán las mismas facultades ordinarias, mientras que las intervenciones históricas o estructurales quedarán reservadas al superadministrador.

#### Diferencia respecto del comportamiento actual

Actualmente administrador y superadministrador poseen las mismas rutas y acciones sobre los catálogos.

La política objetivo conservará esa igualdad para las operaciones ordinarias, pero añadirá una separación explícita para:

- fusiones;
- reasignaciones históricas;
- correcciones estructurales;
- intervenciones masivas.

Estas operaciones excepcionales no existen actualmente y deberán implementarse únicamente cuando haya casos de uso que las requieran.
