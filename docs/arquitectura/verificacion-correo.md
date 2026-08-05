# Política de verificación de correo electrónico de KinesioSoft

## 1. Objetivo

Este documento especifica las reglas funcionales, de seguridad e integridad relacionadas con la verificación y modificación del correo electrónico de los usuarios de KinesioSoft.

Complementa la política general definida en:

`docs/arquitectura/matriz-permisos.md`

La especificación servirá como base para:

- protección de rutas;
- middlewares;
- formularios de actualización;
- acciones de corrección de correo;
- revocación de sesiones;
- auditoría;
- notificaciones;
- pruebas automatizadas.

## 2. Alcance

La política se aplicará a todos los usuarios humanos del sistema, independientemente de su rol:

| Rol                | Valor           |
| ------------------ | --------------- |
| Superadministrador | `superadmin`    |
| Administrador      | `administrador` |
| Kinesiólogo        | `kinesiologo`   |
| Paciente           | `paciente`      |

La verificación del correo será un requisito de seguridad independiente de:

- estado activo de la cuenta;
- rol asignado;
- perfil completo;
- matrícula profesional;
- validación administrativa de documentación.

## 3. Definiciones

### 3.1 Correo no verificado

Un usuario posee correo no verificado cuando:

`email_verified_at` es `null`.

### 3.2 Correo verificado

Un usuario posee correo verificado cuando:

`email_verified_at` contiene la fecha y hora en que se completó correctamente la verificación.

### 3.3 Cuenta activa

Una cuenta activa puede autenticarse, pero no necesariamente acceder a las funciones operativas si su correo todavía no fue verificado.

### 3.4 Funcionalidad operativa

Se considera funcionalidad operativa toda área privada que permita:

- acceder a información personal;
- modificar perfiles;
- gestionar matrículas;
- administrar usuarios;
- administrar catálogos;
- utilizar módulos clínicos;
- ejecutar operaciones del sistema.

## 4. Comportamiento actual

KinesioSoft posee actualmente la estructura de rutas y controladores generada para la verificación de correo electrónico.

Se encuentran registradas las siguientes operaciones:

- visualización del aviso de verificación;
- acceso al enlace firmado;
- reenvío de la notificación;
- cierre de sesión;
- confirmación de contraseña.

La ruta del dashboard declara los middlewares:

- `auth`;
- `verified`;
- `perfil.completo`.

Sin embargo, el modelo `App\Models\User` no implementa actualmente el contrato:

`Illuminate\Contracts\Auth\MustVerifyEmail`

La importación correspondiente se encuentra comentada y la clase no declara la implementación del contrato.

### 4.1 Consecuencias del estado actual

Mientras el modelo no implemente `MustVerifyEmail`:

- el middleware `verified` no restringe efectivamente a los usuarios con `email_verified_at` igual a `null`;
- el evento `Registered` no provoca el envío automático de la notificación de verificación;
- un usuario recién registrado puede continuar hacia el dashboard según los demás middlewares;
- la presencia de las rutas y controladores no demuestra que la política esté habilitada;
- las pruebas actuales de verificación manual pueden aprobar sin detectar esta brecha.

### 4.2 Registro público actual

El registro público:

- crea un usuario activo;
- asigna el rol `paciente`;
- crea el perfil `Paciente`;
- genera el número de historia clínica;
- dispara el evento `Registered`;
- autentica al usuario;
- redirige hacia el dashboard.

Debido a que `User` no implementa `MustVerifyEmail`, el evento `Registered` no envía actualmente la notificación automática de verificación.

### 4.3 Rutas de verificación disponibles

Las rutas actuales incluyen:

- `verification.notice`;
- `verification.verify`;
- `verification.send`.

El enlace de verificación utiliza:

- firma;
- identificador de usuario;
- hash del correo;
- limitación de solicitudes.

El reenvío también posee limitación de solicitudes.

### 4.4 Pruebas actuales

Las pruebas actuales verifican:

- que la pantalla de verificación puede mostrarse;
- que un enlace firmado válido permite verificar el correo;
- que un hash incorrecto no verifica el correo;
- que cambiar el correo desde el perfil establece `email_verified_at` en `null`;
- que conservar el mismo correo mantiene la verificación.

Actualmente no verifican:

- implementación del contrato `MustVerifyEmail`;
- envío automático después del registro;
- bloqueo del dashboard para usuarios no verificados;
- bloqueo de las demás áreas privadas;
- reenvío de la notificación;
- enlaces vencidos;
- enlaces asociados a un correo anterior;
- corrección limitada de un correo no verificado;
- confirmación de contraseña al cambiar el correo;
- revocación de sesiones;
- auditoría.

Este comportamiento representa el estado actual y no la política objetivo.

## 5. Política general adoptada

Todos los usuarios deberán verificar su correo electrónico antes de acceder a funcionalidades privadas u operativas de KinesioSoft.

La regla se aplicará por igual a:

- superadministradores;
- administradores;
- kinesiólogos;
- pacientes.

El hecho de poseer un rol privilegiado no permitirá omitir la verificación.

## 6. Acciones permitidas según el estado del correo

| Acción                                   | Correo no verificado | Correo verificado |
| ---------------------------------------- | -------------------: | ----------------: |
| Iniciar sesión                           |                   Sí |                Sí |
| Cerrar sesión                            |                   Sí |                Sí |
| Ver aviso de verificación                |                   Sí |      No necesario |
| Solicitar reenvío del correo             |                   Sí |      No necesario |
| Abrir enlace firmado de verificación     |                   Sí |      No necesario |
| Corregir correo mediante acción limitada |                   Sí |                No |
| Acceder al dashboard                     |                   No |                Sí |
| Completar perfil de paciente             |                   No |                Sí |
| Completar perfil profesional             |                   No |                Sí |
| Editar perfil general                    |                   No |                Sí |
| Gestionar matrículas                     |                   No |                Sí |
| Gestionar usuarios                       |                   No |                Sí |
| Gestionar catálogos                      |                   No |                Sí |
| Acceder a módulos clínicos               |                   No |                Sí |

## 7. Acciones permitidas sin verificación

Un usuario autenticado cuyo correo no esté verificado solo podrá:

- visualizar el aviso de verificación;
- solicitar el reenvío del enlace;
- abrir el enlace firmado de verificación;
- corregir el correo mediante una acción específica y limitada;
- cerrar sesión.

No podrá:

- completar perfiles;
- acceder al dashboard;
- modificar información personal ordinaria;
- gestionar matrículas;
- administrar usuarios;
- administrar catálogos;
- acceder a información clínica;
- ejecutar cambios de rol;
- realizar operaciones administrativas.

## 8. Protección objetivo de las rutas

Todas las áreas privadas y operativas deberán utilizar como mínimo:

- `auth`;
- `verified`.

Según el recurso, también podrán requerir:

- `perfil.completo`;
- `perfil.paciente`;
- `perfil.kinesiologo`;
- `admin.or.superadmin`;
- permisos de Spatie;
- Policies.

Las rutas de verificación y recuperación necesarias deberán mantenerse accesibles sin el middleware `verified`.

## 9. Registro público de pacientes

Cuando una persona se registre mediante el formulario público:

- la cuenta se creará con correo no verificado;
- se enviará una notificación de verificación;
- el usuario podrá autenticarse;
- no podrá completar su perfil ni acceder al dashboard hasta verificar;
- no será considerado operativo hasta completar la verificación.

La creación de la cuenta no implicará la verificación automática del correo.

## 10. Usuarios creados administrativamente

Cuando un administrador o superadministrador cree una cuenta:

- la cuenta se creará con correo no verificado;
- se enviará una notificación al titular;
- el operador no podrá marcar el correo como verificado manualmente;
- el titular deberá demostrar que controla la dirección;
- la cuenta podrá quedar activa;
- no tendrá acceso operativo hasta verificar.

Los estados `activo` y `correo verificado` serán independientes.

Un usuario podrá encontrarse:

| Activo | Verificado | Resultado                            |
| -----: | ---------: | ------------------------------------ |
|     Sí |         Sí | Puede acceder según rol y perfil     |
|     Sí |         No | Solo puede completar la verificación |
|     No |         Sí | No puede autenticarse                |
|     No |         No | No puede autenticarse                |

## 11. Envío de la verificación

La notificación inicial deberá enviarse cuando:

- se registre un usuario;
- se cree una cuenta administrativamente;
- se corrija un correo todavía no verificado;
- se cambie un correo que ya estaba verificado.

El envío de la notificación no deberá marcar automáticamente el correo como verificado.

## 12. Reenvío del enlace

Un usuario no verificado podrá solicitar un nuevo enlace.

El reenvío deberá:

- requerir autenticación;
- estar limitado para evitar abusos;
- informar un resultado comprensible;
- no revelar información adicional de otras cuentas;
- registrar los intentos relevantes cuando corresponda.

Si el correo ya se encuentra verificado, el sistema no deberá repetir innecesariamente el envío.

## 13. Enlace de verificación

El enlace deberá:

- estar firmado;
- estar asociado al usuario correspondiente;
- validar que el identificador coincida;
- validar que el correo utilizado para generar el enlace continúe siendo el actual;
- tener una vigencia limitada;
- rechazar firmas inválidas o vencidas.

Cuando la verificación sea exitosa:

- se establecerá `email_verified_at`;
- se registrará la fecha y hora;
- se emitirá el evento correspondiente;
- se redirigirá al flujo permitido según el rol y el estado del perfil.

## 14. Corrección del correo antes de verificar

Un usuario cuyo correo todavía no esté verificado podrá corregirlo mediante una acción específica.

La acción:

- requerirá autenticación;
- no requerirá acceso al perfil general;
- solicitará la contraseña actual;
- permitirá modificar únicamente el correo;
- exigirá que el nuevo correo sea válido y único;
- mantendrá `email_verified_at` en `null`;
- enviará una nueva verificación;
- invalidará funcionalmente los enlaces asociados al correo anterior;
- quedará auditada.

No se permitirá modificar en esa pantalla:

- nombre;
- apellido;
- rol;
- estado activo;
- perfiles;
- datos profesionales;
- datos clínicos.

## 15. Cambio de un correo ya verificado

Cuando un usuario con correo verificado cambie su dirección:

- deberá confirmar su contraseña;
- el nuevo correo deberá ser válido y único;
- `email_verified_at` deberá establecerse en `null`;
- se enviará una nueva notificación de verificación;
- perderá temporalmente el acceso operativo;
- deberá verificar la nueva dirección;
- las demás sesiones activas deberán revocarse;
- el token de recordatorio deberá eliminarse;
- el cambio deberá quedar auditado.

Cuando sea técnicamente posible, deberá notificarse la modificación a la dirección anterior.

## 16. Cambio de correo realizado administrativamente

Un administrador no podrá cambiar ordinariamente el correo de otro administrador o superadministrador.

Un superadministrador tampoco podrá modificar ordinariamente el correo de otro superadministrador.

Las correcciones administrativas excepcionales deberán:

- realizarse mediante una acción específica;
- exigir una causa documentada;
- requerir confirmación de contraseña reciente;
- registrar los valores anterior y nuevo;
- invalidar la verificación existente;
- revocar sesiones;
- enviar una nueva verificación;
- notificar al titular;
- quedar obligatoriamente auditadas.

La edición común de usuarios no deberá actuar como mecanismo ordinario para apropiarse o reemplazar el correo de una cuenta privilegiada.

## 17. Interacción con cambios de rol

No podrá ejecutarse un cambio efectivo de rol cuando el usuario afectado tenga el correo sin verificar.

La verificación será obligatoria antes de:

- cambiar entre paciente y kinesiólogo;
- promover a administrador;
- degradar a un administrador;
- promover a superadministrador;
- degradar a un superadministrador.

Un cambio de rol no eliminará la verificación cuando el correo permanezca sin modificaciones.

## 18. Interacción con el estado activo

Un usuario inactivo no podrá:

- iniciar sesión;
- solicitar desde una sesión normal el reenvío;
- corregir su correo;
- completar la verificación de forma operativa.

La activación de una cuenta no verificará automáticamente su correo.

La reactivación de una cuenta conservará su estado anterior de verificación, siempre que el correo no haya cambiado.

## 19. Revocación de sesiones

Deberán revocarse las sesiones del usuario cuando:

- cambie un correo previamente verificado;
- se realice una corrección administrativa excepcional;
- se detecte una modificación sensible del correo;
- la política de seguridad lo requiera.

La revocación deberá incluir:

- eliminación de sesiones persistidas;
- eliminación del `remember_token`;
- obligación de autenticarse nuevamente.

La sesión desde la cual el usuario modifica su propio correo podrá conservarse únicamente para completar el proceso restringido de verificación.

No deberá permitir acceso operativo mientras el nuevo correo permanezca sin verificar.

## 20. Auditoría

Deberán auditarse como mínimo:

- corrección de correo no verificado;
- cambio de correo verificado;
- corrección administrativa excepcional;
- reenvíos relevantes;
- verificación exitosa;
- intentos administrativos rechazados;
- revocación de sesiones asociada.

La auditoría deberá registrar, cuando corresponda:

- usuario afectado;
- operador;
- correo anterior;
- correo nuevo;
- fecha y hora;
- dirección IP;
- motivo;
- resultado;
- sesiones revocadas.

Los correos deberán protegerse adecuadamente cuando se visualicen en registros de auditoría.

## 21. Verificación y datos profesionales

La verificación del correo únicamente confirma que el usuario controla la dirección indicada.

No acredita:

- identidad personal;
- título profesional;
- matrícula profesional;
- vigencia de la matrícula;
- colegio emisor;
- jurisdicción;
- especialidades;
- documentación profesional.

La validación profesional será un proceso independiente.

## 22. Operaciones no permitidas

El sistema deberá rechazar:

- acceso operativo de usuarios no verificados;
- verificación manual ordinaria por un administrador;
- asignación directa arbitraria de `email_verified_at`;
- utilización de un enlace perteneciente a otro usuario;
- utilización de un enlace generado para un correo anterior;
- utilización de un enlace vencido o con firma inválida;
- cambio de rol de un usuario no verificado;
- cambio de correo sin validar unicidad;
- cambio de correo sensible sin contraseña;
- corrección administrativa sin motivo;
- modificación del correo de otro superadministrador desde la edición común.

## 23. Criterios de aceptación

La política se considerará implementada cuando:

1. todas las áreas privadas exijan correo verificado;
2. el usuario no verificado solo pueda acceder al flujo permitido;
3. los registros públicos generen una notificación;
4. las cuentas creadas administrativamente también requieran verificación;
5. pueda corregirse un correo incorrecto antes de verificar;
6. cambiar un correo verificado invalide su verificación;
7. las sesiones se revoquen cuando corresponda;
8. los enlaces inválidos, vencidos o desactualizados sean rechazados;
9. no exista verificación manual ordinaria;
10. los cambios de rol exijan correo verificado;
11. las operaciones sensibles queden auditadas;
12. existan pruebas positivas y negativas para cada flujo.

## 24. Componentes previstos para la implementación

La implementación podrá requerir:

- actualización de grupos de rutas;
- middleware `verified`;
- Form Request para corrección de correo;
- Form Request para cambio de correo verificado;
- acción o servicio de cambio de correo;
- servicio de revocación de sesiones;
- notificaciones;
- eventos;
- auditoría;
- pruebas Feature;
- pruebas Unit cuando corresponda.

La lógica sensible no deberá concentrarse exclusivamente en `ProfileController`.

## 25. Trazabilidad con la matriz de permisos

Esta especificación desarrolla principalmente:

- sección `4. Acceso general actual`;
- sección `7. Perfil general`;
- sección `9.3 Correo verificado`;
- sección `12.4 Cambios de rol`;
- sección `12.6 Correo electrónico verificado`.

La matriz de permisos define la política general.

Este documento define las reglas detalladas de verificación y modificación del correo.

En caso de contradicción, ambos documentos deberán corregirse dentro del mismo incremento antes de modificar código.

## 26. Casos de prueba previstos

Los siguientes casos deberán implementarse antes de considerar aplicada esta política.

### 26.1 Modelo y registro

| Código   | Escenario                                | Resultado esperado                                 |
| -------- | ---------------------------------------- | -------------------------------------------------- |
| `VC-001` | El modelo `User` es inspeccionado        | Implementa `MustVerifyEmail`                       |
| `VC-002` | Se registra un paciente                  | Se crea con `email_verified_at` en `null`          |
| `VC-003` | Se registra un paciente                  | Se envía la notificación de verificación           |
| `VC-004` | Se registra un paciente                  | Permanece autenticado dentro del flujo restringido |
| `VC-005` | El registrado intenta abrir el dashboard | Es redirigido al aviso de verificación             |

### 26.2 Protección de áreas privadas

| Código   | Escenario                                             | Resultado esperado                  |
| -------- | ----------------------------------------------------- | ----------------------------------- |
| `VC-006` | Usuario no verificado abre el dashboard               | Redirección a `verification.notice` |
| `VC-007` | Paciente no verificado intenta completar su perfil    | Acceso rechazado o redirigido       |
| `VC-008` | Kinesiólogo no verificado intenta completar su perfil | Acceso rechazado o redirigido       |
| `VC-009` | Usuario no verificado abre su perfil general          | Acceso rechazado o redirigido       |
| `VC-010` | Kinesiólogo no verificado abre matrículas             | Acceso rechazado o redirigido       |
| `VC-011` | Administrador no verificado abre usuarios             | Acceso rechazado o redirigido       |
| `VC-012` | Administrador no verificado abre catálogos            | Acceso rechazado o redirigido       |
| `VC-013` | Usuario verificado accede a un área autorizada        | Acceso permitido                    |
| `VC-014` | Usuario no autenticado abre un área privada           | Redirección al inicio de sesión     |

### 26.3 Enlace de verificación

| Código   | Escenario                             | Resultado esperado            |
| -------- | ------------------------------------- | ----------------------------- |
| `VC-015` | Usuario abre un enlace firmado válido | Correo verificado             |
| `VC-016` | La verificación es exitosa            | Se emite el evento `Verified` |
| `VC-017` | El hash del correo es incorrecto      | Verificación rechazada        |
| `VC-018` | La firma es inválida                  | Solicitud rechazada           |
| `VC-019` | El enlace está vencido                | Solicitud rechazada           |
| `VC-020` | El enlace corresponde a otro usuario  | Verificación rechazada        |

### 26.4 Reenvío de notificación

| Código   | Escenario                               | Resultado esperado              |
| -------- | --------------------------------------- | ------------------------------- |
| `VC-021` | Usuario no verificado solicita reenvío  | Se envía una nueva notificación |
| `VC-022` | Usuario verificado solicita reenvío     | No se envía innecesariamente    |
| `VC-023` | Usuario no autenticado solicita reenvío | Acceso rechazado                |
| `VC-024` | Se supera el límite de reenvíos         | Solicitud limitada              |

### 26.5 Corrección previa a la verificación

| Código   | Escenario                                                     | Resultado esperado                     |
| -------- | ------------------------------------------------------------- | -------------------------------------- |
| `VC-025` | Usuario no verificado corrige su correo con contraseña válida | Correo actualizado                     |
| `VC-026` | Se corrige un correo no verificado                            | `email_verified_at` continúa en `null` |
| `VC-027` | Se corrige un correo no verificado                            | Se envía un nuevo enlace               |
| `VC-028` | Se intenta usar el enlace del correo anterior                 | Verificación rechazada                 |
| `VC-029` | La contraseña ingresada es incorrecta                         | Corrección rechazada                   |

### 26.6 Cambio de correo verificado

| Código   | Escenario                                      | Resultado esperado                |
| -------- | ---------------------------------------------- | --------------------------------- |
| `VC-030` | Usuario verificado cambia su correo            | `email_verified_at` pasa a `null` |
| `VC-031` | Usuario verificado cambia su correo            | Se envía una nueva verificación   |
| `VC-032` | Usuario cambia su correo sin contraseña válida | Operación rechazada               |
| `VC-033` | Usuario cambia hacia un correo ya utilizado    | Validación rechazada              |
| `VC-034` | Usuario cambia su correo                       | Se revocan las demás sesiones     |
| `VC-035` | Usuario cambia su correo                       | Se elimina el `remember_token`    |

### 26.7 Administración y cambios de rol

| Código   | Escenario                                                    | Resultado esperado                            |
| -------- | ------------------------------------------------------------ | --------------------------------------------- |
| `VC-036` | Administrador crea una cuenta                                | Se crea sin verificar y se envía notificación |
| `VC-037` | Operador intenta verificar manualmente otra cuenta           | Operación rechazada                           |
| `VC-038` | Se intenta cambiar el rol de un usuario no verificado        | Operación rechazada                           |
| `VC-039` | Usuario inactivo intenta completar la verificación operativa | Acceso rechazado                              |
| `VC-040` | Se realiza un cambio sensible de correo                      | La operación queda auditada                   |
