# Casos de uso de adelanto de turnos

## UC-XX · Generar oferta de adelanto de turno

**Relación.** Extiende la cancelación efectiva de turno en UC-06, UC-28 y la cancelación desde la gestión profesional de agenda. Continúa con **Responder a oferta de adelanto de turno**.

**Objetivo y requisito asociado.** OBJ-01, gestionar los turnos y la agenda, y reducir los tiempos muertos. IRQ-01, información sobre turnos y consultas. RF12.2, enviar oferta de adelantamiento de turno.

**Actor principal.** Paciente o kinesióloga que realiza la cancelación. El sistema procesa la evaluación en segundo plano.

**Precondición.** Cancelación efectiva de un turno que ocupaba un intervalo y estaba Pendiente o Confirmado, con al menos 12 horas corridas de anticipación al momento de cancelar.

| Paso | Acción |
|---|---|
| 1 | El sistema procesa la intención de evaluación en segundo plano. Mantiene una evaluación por cancelación. |
| 2 | Comprueba que el cupo esté disponible, carezca de otra oferta activa y falten al menos 4 horas para su inicio. |
| 3 | Busca turnos agendados Pendientes o Confirmados de la misma kinesióloga, con inicio posterior al del cupo y dentro de los 7 días corridos siguientes, incluido el límite exacto. El primer incremento selecciona candidatos de Primera consulta. |
| 4 | Verifica que el turno comience al inicio exacto del cupo, conserve su duración íntegra y cumpla la configuración vigente, las franjas, los bloqueos y la ausencia de superposiciones del paciente y la kinesióloga. |
| 5 | Para Seguimiento, cuando se incorpore, verifica tipo admitido, disponibilidad de equipos requeridos y restricciones del plan y la sesión. Omite al candidato si una comprobación aplicable falla o no puede realizarse. |
| 6 | Ordena por inicio más lejano, registro más temprano e identificador menor. Selecciona al primer candidato elegible con correo de formato válido que todavía no haya recibido una oferta por esa cancelación. |
| 7 | Revalida la posibilidad de reservar y el margen de 4 horas. Activa una oferta y reserva el cupo por hasta 2 horas desde la activación. El turno original del candidato conserva su horario y estado. |
| 8 | Envía el correo con ambos horarios, la kinesióloga, el vencimiento y el enlace seguro. |
| 9 | La oferta queda a la espera de respuesta o vencimiento. La decisión del paciente se trata en **Responder a oferta de adelanto de turno**. |

**Postcondiciones.** El cupo queda reservado mientras exista una oferta activa. Si se agota la evaluación y el cupo sigue libre, vuelve a la disponibilidad habitual.

| Excepción | Acción |
|---|---|
| 1.a | Si la cancelación no cumple la precondición, no inicia la evaluación. Si ya se procesó, reutiliza la evaluación existente. |
| 2.a | Si el cupo está ocupado, reservado por otra oferta o fuera del margen, detiene la emisión. Si un cambio de agenda invalida una oferta activa, la cierra y libera su reserva junto con el cambio. |
| 6.a | Si se agotan los candidatos, el cupo libre vuelve a la disponibilidad habitual. |
| 8.a | Ante un fallo temporal, puede reintentar durante la vigencia con la misma oferta, enlace y vencimiento. |
| 8.b | Ante un fallo definitivo, registra el fallo en Auditoría, cierra la oferta y conserva el turno original. Puede pasar al siguiente candidato si el mismo cupo sigue disponible y cumple el plazo. Si no emite otra oferta y el cupo sigue libre, vuelve a la disponibilidad habitual. |
| 9.a | Tras rechazo o vencimiento, continúa la evaluación del mismo cupo y emite la siguiente oferta si hay disponibilidad, un candidato elegible y al menos 4 horas antes del inicio. Si el turno original se cancela o cambia de horario, se cierra la oferta y puede continuarse bajo las mismas condiciones. |

**Rendimiento.** Búsqueda y verificación de candidatos en menos de 2 segundos. La respuesta a la cancelación se completa sin esperar la búsqueda ni el correo.

**Frecuencia.** Por cancelación efectiva elegible. **Estabilidad.** Alta.

## UC-XX · Responder a oferta de adelanto de turno

**Relación.** Continúa **Generar oferta de adelanto de turno**.

**Objetivo y requisito asociado.** OBJ-01, gestionar los turnos y la agenda, y reducir los tiempos muertos. IRQ-01, información sobre turnos y consultas. RF12.2, enviar oferta de adelantamiento de turno.

**Actor principal.** Paciente destinatario de la oferta.

**Precondición.** Existe una oferta asociada al destinatario, su turno original y un cupo reservado temporalmente. Para responder, el enlace debe ser válido y la oferta debe seguir activa y vigente.

| Paso | Acción |
|---|---|
| 1 | El paciente abre el enlace. El sistema verifica su asociación con la oferta y el destinatario y comprueba la vigencia. |
| 2 | Muestra ambos horarios, la kinesióloga, el vencimiento y las acciones **Aceptar adelanto** y **Rechazar oferta**. Abrir el enlace solo muestra la propuesta. |
| 3 | El paciente pulsa **Aceptar adelanto**. |
| 4 | El sistema vuelve a comprobar vigencia, reserva para esa oferta, horario original, misma kinesióloga y estado Pendiente o Confirmado. |
| 5 | Revalida encaje, duración, configuración, bloqueos, superposiciones y tipo de consulta. Para Seguimiento, cuando se incorpore, verifica también los equipos y las reglas del plan y la sesión aplicables. |
| 6 | En una sola operación registra la aceptación, mueve el mismo turno y, cuando corresponda, la misma sesión. Resuelve la reserva y libera el horario anterior. Conserva identificador, kinesióloga, tipo, motivo, duración, estado vigente y vínculos aplicables. |
| 7 | Muestra el nuevo horario y estado, que también se reflejan en Mis turnos, y envía el correo de confirmación del cambio. |

| Flujo o excepción | Acción |
|---|---|
| 3.a · Rechazo | Registra el rechazo vigente, cierra la oferta e informa que el turno conserva horario y estado. Continúa la evaluación del mismo cupo según **Generar**. Si hay disponibilidad, candidato elegible y al menos 4 horas de margen, emite la siguiente oferta por prioridad. Si se agotan candidatos o plazo y el cupo sigue libre, vuelve a la disponibilidad habitual. |
| 1.a · Enlace inválido | Informa que el enlace es inválido y bloquea el detalle y la respuesta. |
| 1.b / 4.a · Vencimiento | Desde el instante exacto de vencimiento se impide responder. El sistema cierra la oferta aunque el paciente nunca abra el enlace. Si intenta consultarla o responder, informa que venció y que no reprogramó su turno. Continúa la evaluación del mismo cupo con las condiciones de 3.a. |
| 4.b · Cambio del turno original | Si se canceló o cambió de horario, cierra la oferta e informa que el cambio ofrecido no se aplicó. Puede continuar con otro candidato si el cupo sigue utilizable y cumple las condiciones de generación. |
| 4.c / 5.a · Cupo no disponible o incompatibilidad | Cierra la oferta, informa el resultado y conserva el turno. Si la incompatibilidad afecta al candidato y el cupo sigue utilizable, continúa la evaluación del mismo cupo con las condiciones de 3.a. |
| 6.a · Respuestas repetidas o simultáneas | Una aceptación repetida devuelve el resultado ya registrado. Si compiten aceptación y rechazo, se aplica la primera respuesta válida registrada. Si compiten aceptación y bloqueo invalidante, se aplica la regla de cambios de agenda aprobada. |
| 6.b · Fallo durante el traslado | Conserva la programación previa, deja la aceptación sin registrar e informa que el cambio no se completó. |
| 7.a · Fallo del correo de confirmación | Registra el fallo. El traslado sigue vigente y el nuevo horario está disponible en Mis turnos. |

**Postcondiciones.** Un adelanto completado libera el intervalo anterior sin generar otra evaluación ni oferta automática. Un rechazo, vencimiento o traslado fallido conserva la programación previa. La aceptación del adelanto y la confirmación de asistencia son acciones separadas.

**Rendimiento.** Menos de 2 segundos para cada operación de base de datos, tanto la consulta de la oferta como la reprogramación.

**Frecuencia.** Según las consultas y respuestas a ofertas emitidas. **Estabilidad.** Alta.
