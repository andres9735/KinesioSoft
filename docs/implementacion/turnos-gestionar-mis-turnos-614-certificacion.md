# UC–06 — Subpaso 6.14: certificación técnica y manual

**Estado de 6.14:** IMPLEMENTADO, VERIFICADO Y CERRADO en el commit definitivo `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`.

## Referencia y alcance

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD de referencia: `616085b57b425e873ff0e957b0d558cb26add136`, cierre certificado de 6.13.
- Alcance: Confirmar asistencia y Cancelar turno desde el detalle autorizado de Mis turnos para Primera consulta y Seguimiento; proyección de acciones disponibles, diálogos con consentimiento explícito, motivo administrativo opcional de cancelación, protección frente a doble envío y recuperación del detalle vigente conservando el contexto de navegación.
- Se reutilizan `ConfirmarAsistenciaTurnoAction`, `CancelarTurnoAction`, `TransaccionTurnoService`, Policies y auditoría existentes. No se incorporan migraciones ni se modifican las Actions o la infraestructura certificada en 6.13. Las fuentes UC–06 V1.0 y sus decisiones congeladas permanecen sin modificaciones.

## Contrato HTTP y referencia de programación

| Operación | Ruta y entrada | Regla para una transición nueva |
| --- | --- | --- |
| Confirmar asistencia | `POST /turnos/mis-turnos/{turno}/confirmar-asistencia`; `referencia_programacion` obligatoria. | Pendiente → Confirmado, con inicio estrictamente futuro. No acepta motivo. |
| Cancelar turno | `POST /turnos/mis-turnos/{turno}/cancelar`; `referencia_programacion` obligatoria y `motivo` textual opcional. | Pendiente/Confirmado → Cancelado, con anticipación inclusiva de 12 horas corridas. |

Ambas rutas conservan middleware web y CSRF, autenticación, correo verificado, perfil completo de paciente y autorización individual por Policy. El servidor deriva actor, paciente, canal y programación; rechaza los campos de autoridad enviados por el cliente.

- **200:** transición consolidada o replay autorizado. JSON limitado a `code`, `es_replay` y `message`; códigos `asistencia_confirmada` o `turno_cancelado`. El replay no duplica auditorías ni intenciones y no reactiva estados posteriores.
- **409:** programación o recursos modificados; código `programacion_modificada`, sin operar sobre una programación diferente de la visualizada.
- **422:** entrada o referencia inválida/incompatible, estado, tipo o plazo no permitido. Los códigos de dominio incluyen `estado_no_permitido`, `tipo_no_permitido`, `inicio_no_futuro` y `fuera_de_plazo`.
- **403 / 404:** falta de autorización o recurso inexistente/no disponible. El middleware conserva sus respuestas de autenticación y perfil; CSRF inválido produce **419**.
- **500:** error técnico con respuesta sanitizada; no se exponen excepciones, SQL ni trazas al paciente ni se informa éxito parcial.

`ReferenciaProgramacionTurnoService` emite mediante `Crypt::encryptString()` una captura cifrada y autenticada con propósito versionado, actor, turno y programación: paciente, profesional, tipo, inicio y fin. Se comprueban integridad, propósito y correspondencia con actor/turno/programación en el POST. El estado no integra esa captura, para permitir la semántica de replay de las Actions originales. La referencia no sustituye autorización, relectura ni locks.

Las Actions vuelven a autorizar y comparar la programación dentro de la transacción, con el orden kinesióloga → paciente → turno y reloj autoritativo. Se conserva la atomicidad de transición, auditoría e intención durable cuando corresponde, la recuperación UNIQUE de 6.13 y la ausencia de reintentos automáticos. El motivo administrativo se normaliza y conserva sólo en la auditoría de cancelación efectiva; no sobrescribe el motivo original ni altera documentos privados.

## Interacción y recuperación

Blade, Alpine y SweetAlert2 reutilizan la integración global existente. Desistir, cerrar el diálogo o no disponer de SweetAlert2 no ejecuta POST. Se bloquean operaciones simultáneas, se recuperan los controles y el transporte tiene timeout de 30 segundos sin reenvío automático.

Después de éxito, replay o rechazo recuperable se consulta el detalle vigente mediante GET. Ante respuesta perdida, inválida o error técnico se informa incertidumbre y se ofrece consultar el estado; no se presupone rollback ni se repite la transición. Se preservan filtros, paginación y navegación mediante contexto permitido, sin redirecciones arbitrarias.

## Certificaciones aprobadas

Los resultados siguientes se registran conforme a las certificaciones ya aprobadas y al resultado manual informado por el usuario. No se reejecutaron pruebas, builds ni migraciones durante esta preparación documental.

| Verificación | Resultado |
| --- | --- |
| Pruebas PHP acotadas de 6.14 | **1207 tests / 8555 assertions PASS**. |
| Regresión de integración UC–07 | **62 tests / 586 assertions PASS**. |
| JavaScript nuevo de Mis turnos | **26 pruebas PASS**. |
| JavaScript existente | **155 pruebas PASS**. |
| Lint y Pint acotados | **PASS**, según certificación técnica previa. |
| Build | **PASS, 95 módulos**. |
| Revisión técnica estática R1 | **Satisfactoria**, sin bloqueantes estáticos identificados. |
| Validación manual principal | **Satisfactoria** para el flujo principal de confirmación y cancelación desde el detalle. |

Los conjuntos PHP indicados no se suman como si fueran una única ejecución ni constituyen una nueva regresión PHP global. El baseline global y la concurrencia MariaDB de 6.13 conservan su certificación histórica independiente, documentada en `turnos-gestionar-mis-turnos-613-certificacion.md`; las 27 pruebas MariaDB no se repitieron.

## Entorno y auditoría manual

La validación principal se realizó sobre **SQLite temporal aislado**, persistente entre solicitudes HTTP, con **48 migraciones y 44 tablas**, configuración de testing separada del entorno de desarrollo, clave temporal estable, storage/sesiones/caché/logs propios, comunicaciones externas bloqueadas y servidor limitado a `127.0.0.1`. Se mantuvieron autenticación, autorización, CSRF y validaciones originales.

Auditoría final informada: **5 registros en `auditorias`, 3 en `intenciones_evaluacion_cupo` y 12 turnos sintéticos**. Son datos esperados del entorno manual, conservados fuera del repositorio; no se declara una base vacía ni se autoriza limpiarla. Las bases persistentes de desarrollo y certificación MariaDB no son destinos alternativos del procedimiento. En esta fase documental no se abre ninguna conexión a ellas ni se limpia ninguna sesión temporal.

No se incorporan claves, contraseñas, cookies, datos personales o clínicos, auxiliares de lanzamiento ni evidencia temporal al repositorio. Los ajustes de los auxiliares externos no forman parte de la implementación de 6.14.

## Limitaciones y transición

- La validación manual principal no equivale a una auditoría exhaustiva de accesibilidad, dispositivos o navegadores. No exige reproducir manualmente todos los bordes temporales exactos, CSRF, propiedad, replay/idempotencia y concurrencia ya cubiertos automáticamente; esos resultados mantienen su alcance y evidencia propios. SQLite no certifica locking real de MariaDB.
- **Observaciones UX para 6.15:** la CSP del entorno temporal bloquea la fuente externa y utiliza la tipografía de respaldo; revisar su presentación. Revisar también la diferenciación entre el estado ocupado durante el diálogo y el indicador de progreso del POST. Estas observaciones no modifican el resultado satisfactorio del flujo principal ni autorizan cambios anticipados.
- **C-02 / DT-03 PENDIENTE:** la integración clínica de planes y sesiones reales de Seguimiento no queda resuelta ni certificada por esta interfaz.
- **Integraciones externas PENDIENTES:** evaluación efectiva de cupos, candidatos, ofertas, enlaces seguros y comunicaciones. Registrar una intención durable no implementa estos procesos.
- **6.14: IMPLEMENTADO, VERIFICADO Y CERRADO en `5a9c6edce1a0b18ff4c031cb542d4a646b0e8323`. 6.15 es el siguiente subpaso, pendiente y sin iniciar; 6.16–6.18 continúan pendientes.** Esta documentación no autoriza por sí sola staging, commit, push ni nuevos trabajos.
