# Política de cambios de rol de KinesioSoft

## 1. Objetivo

Este documento especifica las reglas funcionales, de seguridad e integridad aplicables a los cambios de rol de los usuarios de KinesioSoft.

Complementa la matriz general ubicada en:

`docs/arquitectura/matriz-permisos.md`

La especificación servirá como base para:

- acciones de dominio;
- Form Requests;
- Policies;
- permisos de Spatie;
- transacciones;
- auditoría;
- revocación de sesiones;
- pruebas automatizadas.

## 2. Roles comprendidos

KinesioSoft reconoce los siguientes roles principales:

| Rol                | Valor           |
| ------------------ | --------------- |
| Superadministrador | `superadmin`    |
| Administrador      | `administrador` |
| Kinesiólogo        | `kinesiologo`   |
| Paciente           | `paciente`      |

Los valores oficiales se encuentran centralizados en:

`app/Enums/RoleEnum.php`

## 3. Invariante de rol principal

Cada usuario deberá poseer exactamente un rol principal.

Después de cualquier operación de creación, promoción, degradación o cambio funcional:

- el usuario deberá conservar un único rol;
- el nuevo rol deberá existir en el catálogo de roles;
- la transición deberá estar permitida;
- la asignación deberá realizarse de forma atómica;
- no podrán quedar roles anteriores asignados accidentalmente.

La presencia de perfiles históricos no otorgará permisos.

Los permisos dependerán exclusivamente del rol principal actual y de las reglas de autorización vigentes.

## 4. Comportamiento actual

Actualmente los cambios de rol se realizan desde el formulario general de edición de usuarios.

El controlador:

- permite a administradores cambiar entre `paciente` y `kinesiologo`;
- permite a superadministradores asignar cualquier rol reconocido;
- impide que un administrador modifique administradores o superadministradores;
- impide quitar el rol a un superadministrador existente;
- crea un perfil `Paciente` cuando el nuevo rol es `paciente` y todavía no existe;
- no crea automáticamente el perfil `Kinesiologa`;
- conserva perfiles anteriores;
- conserva matrículas y especialidades;
- no solicita un motivo;
- no utiliza una acción separada para promociones sensibles;
- no revoca explícitamente las sesiones al cambiar el rol;
- no registra todavía una auditoría específica de la transición.

Este comportamiento representa el estado actual y no necesariamente la política objetivo.

## 5. Clasificación de transiciones

Las transiciones se clasificarán en cuatro grupos.

### 5.1 Cambio funcional ordinario

Comprende:

- `paciente` a `kinesiologo`;
- `kinesiologo` a `paciente`.

Puede ser realizado por:

- administrador;
- superadministrador.

### 5.2 Promoción administrativa

Comprende:

- `paciente` a `administrador`;
- `kinesiologo` a `administrador`.

Solo puede ser realizada por un superadministrador.

### 5.3 Degradación administrativa

Comprende:

- `administrador` a `paciente`;
- `administrador` a `kinesiologo`.

Solo puede ser realizada por un superadministrador.

### 5.4 Operación crítica de superadministración

Comprende:

- promoción hacia `superadmin`;
- degradación desde `superadmin`.

Solo puede ser realizada por otro superadministrador activo mediante una acción específica y reforzada.

## 6. Matriz objetivo de transiciones

| Rol actual      | Rol nuevo               |      Permitido | Operador autorizado        | Clasificación              |
| --------------- | ----------------------- | -------------: | -------------------------- | -------------------------- |
| `paciente`      | `kinesiologo`           |             Sí | Administrador o superadmin | Ordinaria                  |
| `kinesiologo`   | `paciente`              |             Sí | Administrador o superadmin | Ordinaria                  |
| `paciente`      | `administrador`         |             Sí | Superadmin                 | Promoción administrativa   |
| `kinesiologo`   | `administrador`         |             Sí | Superadmin                 | Promoción administrativa   |
| `administrador` | `paciente`              |             Sí | Superadmin                 | Degradación administrativa |
| `administrador` | `kinesiologo`           |             Sí | Superadmin                 | Degradación administrativa |
| `paciente`      | `superadmin`            |             Sí | Otro superadmin activo     | Promoción crítica          |
| `kinesiologo`   | `superadmin`            |             Sí | Otro superadmin activo     | Promoción crítica          |
| `administrador` | `superadmin`            |             Sí | Otro superadmin activo     | Promoción crítica          |
| `superadmin`    | `administrador`         |    Condicional | Otro superadmin activo     | Degradación crítica        |
| `superadmin`    | `paciente`              |    Condicional | Otro superadmin activo     | Degradación crítica        |
| `superadmin`    | `kinesiologo`           |    Condicional | Otro superadmin activo     | Degradación crítica        |
| Cualquier rol   | Mismo rol               | Sin transición | —                          | Actualización ordinaria    |
| Cualquier rol   | Otro rol sobre sí mismo |             No | Ninguno                    | Prohibida                  |

## 7. Reglas generales

Toda transición efectiva deberá:

- ser iniciada por un operador autorizado;
- impedir que el operador cambie su propio rol;
- exigir que el operador se encuentre activo;
- exigir que el operador tenga el correo electrónico verificado;
- exigir que el usuario afectado tenga el correo electrónico verificado;
- exigir un motivo;
- ejecutarse dentro de una transacción;
- sincronizar un único rol principal;
- conservar los perfiles históricos;
- garantizar la existencia del perfil requerido;
- revocar las sesiones del usuario afectado;
- eliminar su token de recordatorio;
- registrar el cambio en la auditoría;
- mantener sin cambios el estado activo del usuario;
- informar claramente el resultado de la operación.

## 8. Conservación histórica

Un cambio de rol no eliminará:

- perfil de paciente;
- número de historia clínica;
- perfil profesional;
- matrículas profesionales;
- especialidades;
- turnos;
- consultas;
- tratamientos;
- relaciones clínicas;
- registros de auditoría.

Cuando el usuario vuelva a un rol anterior, deberá reutilizarse el perfil existente.

Los datos históricos conservados no otorgarán acceso mientras el rol actual no lo permita.

## 9. Efectos al cambiar a paciente

Cuando el nuevo rol sea `paciente`:

- se reutilizará el perfil `Paciente` existente;
- si no existe, deberá crearse;
- se generará el número de historia clínica mediante el generador central;
- se conservará cualquier perfil profesional anterior;
- se conservarán matrículas y especialidades;
- el usuario accederá únicamente a las funciones permitidas al paciente.

La creación del perfil y el cambio de rol deberán formar parte de la misma transacción.

## 10. Efectos al cambiar a kinesiólogo

Cuando el nuevo rol sea `kinesiologo`:

- se reutilizará el perfil `Kinesiologa` existente;
- si no existe, el usuario deberá completar el perfil profesional;
- se conservará el perfil de paciente anterior;
- se conservará el número de historia clínica;
- se conservarán matrículas profesionales anteriores;
- el acceso profesional dependerá de que el perfil se encuentre completo.

El cambio de rol no deberá crear automáticamente una matrícula profesional.

## 11. Efectos al cambiar a administrador

Cuando el nuevo rol sea `administrador`:

- se conservarán los perfiles anteriores;
- los perfiles históricos no otorgarán permisos clínicos;
- deberá revocarse el acceso correspondiente al rol anterior;
- la promoción requerirá confirmación de contraseña reciente;
- deberá solicitarse un motivo;
- la operación deberá quedar auditada.

La promoción no estará disponible en el formulario general de edición.

## 12. Efectos al cambiar a superadministrador

La promoción a `superadmin`:

- no estará disponible en el formulario general;
- será realizada mediante una acción específica;
- solo podrá ejecutarla otro superadministrador activo;
- requerirá contraseña reciente;
- requerirá confirmación explícita;
- exigirá un motivo;
- revocará las sesiones del usuario promovido;
- quedará obligatoriamente auditada.

Un usuario no podrá promoverse a sí mismo.

## 13. Degradación de superadministrador

Un superadministrador no podrá quitarse a sí mismo el rol.

Otro superadministrador activo podrá degradarlo cuando:

- el operador no sea el usuario afectado;
- permanezca al menos un superadministrador activo;
- el nuevo rol esté permitido;
- se garantice la existencia del perfil requerido;
- se confirme la contraseña del operador;
- se ingrese un motivo;
- se confirme expresamente la operación;
- se revoquen las sesiones del afectado;
- la operación quede auditada.

## 14. Revocación de sesiones

Después de todo cambio efectivo de rol:

- se eliminará el `remember_token`;
- se eliminarán las sesiones persistidas del usuario afectado;
- el usuario deberá autenticarse nuevamente;
- los permisos deberán recalcularse según el nuevo rol.

No se revocarán sesiones cuando el rol enviado sea igual al rol actual y solo se actualicen datos personales.

## 15. Auditoría

Todo cambio efectivo deberá registrar:

- identificador del operador;
- rol del operador;
- identificador del usuario afectado;
- rol anterior;
- rol nuevo;
- fecha y hora;
- motivo;
- dirección IP cuando esté disponible;
- valores relevantes anteriores y posteriores;
- resultado de la operación.

Las promociones y degradaciones administrativas deberán considerarse acciones sensibles.

## 16. Operaciones que no deben permitirse

El sistema deberá rechazar:

- cambio de rol realizado por el propio usuario;
- rol nuevo inexistente;
- transición no autorizada;
- administrador intentando modificar a otro administrador;
- administrador intentando modificar a un superadministrador;
- promoción a `superadmin` desde la edición ordinaria;
- degradación del único superadministrador activo;
- degradación propia de un superadministrador;
- transición sin motivo cuando este sea obligatorio;
- operación ejecutada por un usuario inactivo;
- transición que deje al usuario sin rol;
- transición que deje al usuario con más de un rol principal.

## 17. Criterios de aceptación

La política se considerará implementada cuando:

1. todas las transiciones permitidas cuenten con pruebas positivas;
2. todas las transiciones prohibidas cuenten con pruebas negativas;
3. ningún usuario pueda cambiar su propio rol;
4. las promociones administrativas estén separadas de la edición ordinaria;
5. las operaciones sobre superadministradores sean específicas;
6. se conserve como mínimo un superadministrador activo;
7. se creen o reutilicen correctamente los perfiles;
8. no se eliminen perfiles históricos;
9. se revoquen las sesiones después de cada cambio efectivo;
10. cada transición quede auditada;
11. las operaciones sean transaccionales;
12. el usuario conserve exactamente un rol principal.

## 18. Componentes previstos para la implementación

La implementación podrá requerir:

- `UserPolicy`;
- Form Requests específicos;
- acciones o servicios de cambio de rol;
- servicio central de generación de historia clínica;
- servicio de revocación de sesiones;
- eventos o registros de auditoría;
- permisos de Spatie;
- pruebas Feature y Unit.

Las responsabilidades sensibles no deberán permanecer concentradas dentro de `UserController`.

## 19. Casos de prueba previstos

Los siguientes casos deberán implementarse antes de considerar aplicada esta política.

### 19.1 Cambios funcionales ordinarios

| Código   | Escenario                                   | Resultado esperado   |
| -------- | ------------------------------------------- | -------------------- |
| `CR-001` | Administrador cambia paciente a kinesiólogo | Transición permitida |
| `CR-002` | Administrador cambia kinesiólogo a paciente | Transición permitida |
| `CR-003` | Superadmin cambia paciente a kinesiólogo    | Transición permitida |
| `CR-004` | Superadmin cambia kinesiólogo a paciente    | Transición permitida |
| `CR-005` | Kinesiólogo intenta cambiar su propio rol   | Respuesta 403        |
| `CR-006` | Paciente intenta cambiar su propio rol      | Respuesta 403        |

### 19.2 Promociones administrativas

| Código   | Escenario                                                  | Resultado esperado   |
| -------- | ---------------------------------------------------------- | -------------------- |
| `CR-007` | Superadmin promueve paciente a administrador               | Promoción permitida  |
| `CR-008` | Superadmin promueve kinesiólogo a administrador            | Promoción permitida  |
| `CR-009` | Administrador intenta promover paciente a administrador    | Respuesta 403        |
| `CR-010` | Administrador intenta promover kinesiólogo a administrador | Respuesta 403        |
| `CR-011` | Promoción administrativa sin motivo                        | Validación rechazada |
| `CR-012` | Promoción administrativa sin contraseña confirmada         | Operación rechazada  |

### 19.3 Degradaciones administrativas

| Código   | Escenario                                                  | Resultado esperado   |
| -------- | ---------------------------------------------------------- | -------------------- |
| `CR-013` | Superadmin cambia administrador a paciente                 | Transición permitida |
| `CR-014` | Superadmin cambia administrador a kinesiólogo              | Transición permitida |
| `CR-015` | Administrador intenta cambiar el rol de otro administrador | Respuesta 403        |
| `CR-016` | Degradación sin motivo                                     | Validación rechazada |

### 19.4 Superadministradores

| Código   | Escenario                                                            | Resultado esperado   |
| -------- | -------------------------------------------------------------------- | -------------------- |
| `CR-017` | Superadmin promueve paciente a superadmin mediante acción específica | Promoción permitida  |
| `CR-018` | Superadmin intenta promoverse a sí mismo                             | Respuesta 403        |
| `CR-019` | Promoción a superadmin desde edición ordinaria                       | Operación rechazada  |
| `CR-020` | Otro superadmin degrada a un superadmin cuando quedan dos activos    | Transición permitida |
| `CR-021` | Superadmin intenta degradarse a sí mismo                             | Respuesta 403        |
| `CR-022` | Se intenta degradar al único superadmin activo                       | Operación rechazada  |
| `CR-023` | Promoción o degradación crítica sin contraseña confirmada            | Operación rechazada  |
| `CR-024` | Promoción o degradación crítica sin motivo                           | Validación rechazada |

### 19.5 Perfiles y conservación histórica

| Código   | Escenario                                           | Resultado esperado                                    |
| -------- | --------------------------------------------------- | ----------------------------------------------------- |
| `CR-025` | Usuario cambia a paciente sin perfil previo         | Se crea `Paciente`                                    |
| `CR-026` | Usuario vuelve a paciente con perfil existente      | Se reutiliza el perfil                                |
| `CR-027` | Se crea un nuevo perfil de paciente                 | Se genera historia clínica única                      |
| `CR-028` | Usuario cambia a kinesiólogo sin perfil profesional | Queda obligado a completar el perfil                  |
| `CR-029` | Usuario vuelve a kinesiólogo con perfil existente   | Se reutiliza `Kinesiologa`                            |
| `CR-030` | Kinesiólogo cambia a paciente                       | Se conservan matrículas y especialidades              |
| `CR-031` | Paciente cambia a kinesiólogo                       | Se conserva su historia clínica                       |
| `CR-032` | Usuario cambia a administrador                      | Se conservan perfiles anteriores sin otorgar permisos |

### 19.6 Sesiones y seguridad

| Código   | Escenario                                             | Resultado esperado                       |
| -------- | ----------------------------------------------------- | ---------------------------------------- |
| `CR-033` | Se realiza un cambio efectivo de rol                  | Se eliminan las sesiones del afectado    |
| `CR-034` | Se realiza un cambio efectivo de rol                  | Se elimina el `remember_token`           |
| `CR-035` | Se envía el mismo rol actual                          | No se revocan sesiones por cambio de rol |
| `CR-036` | Operador inactivo intenta cambiar un rol              | Operación rechazada                      |
| `CR-037` | Operador sin correo verificado intenta cambiar un rol | Operación rechazada                      |
| `CR-038` | Usuario afectado sin correo verificado                | Operación rechazada                      |
| `CR-039` | Se envía un rol inexistente                           | Validación rechazada                     |

### 19.7 Integridad y transacciones

| Código   | Escenario                                  | Resultado esperado                     |
| -------- | ------------------------------------------ | -------------------------------------- |
| `CR-040` | Cambio exitoso de rol                      | El usuario conserva exactamente un rol |
| `CR-041` | Falla la creación del perfil requerido     | Se revierte el cambio de rol           |
| `CR-042` | Falla la auditoría dentro de la operación  | Se revierte la transacción             |
| `CR-043` | Se intenta dejar al usuario sin rol        | Operación rechazada                    |
| `CR-044` | Se intenta asignar más de un rol principal | Operación rechazada                    |

### 19.8 Auditoría

| Código   | Escenario                         | Resultado esperado                             |
| -------- | --------------------------------- | ---------------------------------------------- |
| `CR-045` | Cambio ordinario exitoso          | Se registra operador, afectado, roles y motivo |
| `CR-046` | Promoción administrativa exitosa  | Se registra como acción sensible               |
| `CR-047` | Promoción a superadmin exitosa    | Se registra la promoción crítica               |
| `CR-048` | Degradación de superadmin exitosa | Se registra la degradación crítica             |
| `CR-049` | Operación rechazada               | No se registra un cambio de rol exitoso        |
| `CR-050` | Cambio de rol ejecutado           | La auditoría conserva rol anterior y nuevo     |

## 20. Trazabilidad con la matriz de permisos

Esta especificación desarrolla principalmente las decisiones documentadas en:

- sección `5. Gestión de usuarios`;
- sección `9. Reglas transversales`;
- sección `12.1 Creación y promoción de superadministradores`;
- sección `12.2 Modificación entre superadministradores`;
- sección `12.3 Autoedición y autoinactivación`;
- sección `12.4 Cambios de rol`;
- sección `12.6 Correo electrónico verificado`.

La matriz de permisos representa la política general.

Este documento representa la especificación detallada de los cambios de rol.

En caso de detectar una contradicción, ambos documentos deberán actualizarse dentro del mismo incremento antes de modificar el código.
