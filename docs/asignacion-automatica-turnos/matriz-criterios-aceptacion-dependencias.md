# Matriz de criterios de aceptación y dependencias · V1.0

El primer incremento cubre **candidatos de Primera consulta con la misma kinesióloga**, tanto para cupos liberados por Primera consulta como por Seguimiento. Las comprobaciones de equipos, plan y sesión se incorporan con los candidatos de Seguimiento.

| ID | Referencia | Resultado verificable |
|---|---|---|
| CA-G01 | Generar, precondición y 1.a | La cancelación efectiva de un turno ocupante Pendiente o Confirmado inicia la evaluación desde 12 horas corridas de anticipación, inclusive. Con un segundo menos, no. |
| CA-G02 | Generar, 1 y 1.a | El procesamiento repetido conserva una evaluación por cancelación. Los reintentos y las ofertas sucesivas pertenecen a esa evaluación. |
| CA-G03 | Generar, 2, 7 y 2.a | Cada activación exige cupo disponible, ausencia de otra oferta activa y al menos 4 horas para el inicio. El límite exacto es válido. Con un segundo menos, no. |
| CA-G04 | Generar, 3 | Se buscan automáticamente turnos agendados Pendientes o Confirmados de la misma kinesióloga. |
| CA-G05 | Generar, 3 | Solo se seleccionan turnos cuyo inicio sea estrictamente posterior al del cupo y hasta 7 días corridos después, inclusive, aunque cambie la semana calendario. |
| CA-G06 | Generar, 4 | El candidato comienza al inicio exacto del cupo y conserva su duración registrada. El intervalo cabe en el cupo y en una franja habilitada vigente, sin bloqueos ni superposiciones de profesional o paciente. Puede provenir de otra configuración. Una Primera consulta de 30 minutos que cabe en un cupo de 45 minutos conserva sus 30 minutos. |
| CA-G07 | Generar, 4 | El primer incremento selecciona Primera consulta a partir de cupos liberados por cualquiera de los dos tipos. Con la configuración actual, CA-G06 determina la admisión del destino. Al incorporar Seguimiento se verifica también que el destino admita el tipo correspondiente. |
| CA-G08 | Generar, 5 | Para Seguimiento, todos los equipos requeridos por el candidato deben estar disponibles durante todo el intervalo que ocupará, aunque difieran de los del turno cancelado. Si algún requisito aplicable falla o no puede verificarse, se omite el candidato. |
| CA-G09 | Generar, 5 | Para Seguimiento vinculado, el traslado de la misma sesión conserva su vínculo con el plan y respeta las reglas aplicables de orden y separación. |
| CA-G10 | Generar, 6 y 6.a | Se elige el inicio más lejano, luego el registro más temprano y luego el identificador menor. Se excluye a quienes carecen de correo de formato válido o ya recibieron una oferta para esa cancelación. |
| CA-G11 | Generar, 7 y 9 | Hay como máximo una oferta activa por cupo. La reserva excluye nuevas reservas y ofertas mientras esté activa. La vigencia es de 2 horas desde la activación y deja al menos 2 horas antes del inicio. El turno original mantiene horario y estado. |
| CA-G12 | Generar, 8 | El correo contiene ambos horarios, la kinesióloga, el vencimiento exacto y el enlace seguro. El contenido se limita a los datos de agenda. |
| CA-G13 | Generar, 8.a | Un reintento temporal durante la vigencia conserva oferta, enlace y vencimiento. |
| CA-G14 | Generar, 8.b | Un fallo definitivo queda en Auditoría, cierra la oferta y libera su reserva, con el turno original intacto. Puede continuar con el siguiente candidato si corresponde. Si no se emite otra oferta y el cupo sigue libre, vuelve a la disponibilidad habitual. |
| CA-G15 | Generar, 6.a y 9.a | Tras rechazo o vencimiento, se emite una nueva oferta para el mismo cupo si sigue disponible, hay candidato elegible y faltan al menos 4 horas. Si se agotan candidatos o plazo, el cupo libre vuelve a la agenda. Esto también aplica si la búsqueda inicial no encuentra candidatos. |
| CA-G16 | Generar, 7 y 2.a | Un bloqueo o un cambio de configuración invalidante cierra la oferta activa y libera su reserva en la misma operación que registra el cambio. El turno original permanece intacto y el cupo queda inhabilitado mientras subsista la restricción. Un cambio compatible conserva la oferta y el vencimiento original. |
| CA-R01 | Responder, 1, 2 y 1.a | Un enlace válido muestra la oferta asociada al destinatario con ambos horarios, la profesional y el vencimiento. Abrirlo no registra una respuesta. Un enlace inválido impide acceder y responder. |
| CA-R02 | Responder, 3.a | Un rechazo vigente cierra la oferta, informa el resultado y conserva el turno original. El mismo cupo continúa según CA-G15. |
| CA-R03 | Responder, 1.b / 4.a | Desde el vencimiento exacto no se puede responder. El sistema cierra la oferta aunque no se abra el enlace. Ante una consulta o respuesta posterior, informa que venció y no reprogramó el turno. Se aplica CA-G15. |
| CA-R04 | Responder, 3, 4 y 4.b | La aceptación explícita exige vigencia, reserva para esa oferta, programación original, misma kinesióloga y estado Pendiente o Confirmado. Si el turno original se canceló o cambió de horario, cierra e informa. Puede continuar con otro candidato si corresponde. |
| CA-R05 | Responder, 4.c / 5.a | Si el cupo dejó de estar disponible, se cierra la oferta, se informa al paciente y se conserva su turno. No se emiten ofertas mientras el cupo siga inutilizable. |
| CA-R06 | Responder, 5 y 4.c / 5.a | Al aceptar se revalidan CA-G06 y CA-G07 y, para Seguimiento cuando corresponda, CA-G08 y CA-G09. Si falla una condición del candidato o no puede verificarse, se cierra la oferta, se informa y se conserva el turno. Si el cupo sigue utilizable, se aplica CA-G15. |
| CA-R07 | Responder, 6 | En una operación se acepta, se mueve el mismo turno, se resuelve la reserva y se libera el intervalo original. Se conservan identificador, kinesióloga, tipo, motivo, duración y estado vigente. Cuando corresponda, se mueve la misma sesión y se conserva su vínculo. |
| CA-R08 | Responder, 6.a | Una aceptación repetida devuelve el resultado anterior y produce un solo traslado. Ante aceptación y rechazo simultáneos, vale la primera respuesta válida registrada. |
| CA-R09 | Responder, 6.b | Un fallo parcial conserva la programación anterior, deja la aceptación sin registrar e informa que el cambio no se completó. |
| CA-R10 | Responder, 6, 7 y 7.a | Al concretar el adelanto, vuelve a quedar disponible el intervalo anterior sin una nueva intención ni oferta automática. Se muestran el nuevo horario y estado, se reflejan en Mis turnos y se envía el correo de confirmación. Un fallo del correo se registra y el traslado sigue vigente. |
| CA-R11 | Responder, 6 y 6.a | Si se registra primero el bloqueo invalidante, se cierra la oferta, se libera la reserva y el turno conserva el horario original. Si se completa primero la aceptación, se aplica al turno trasladado el tratamiento habitual de un turno existente afectado por un bloqueo. Se comprueban ambos órdenes. |
| CA-P01 | Generar, 1 y 8 | La cancelación responde sin esperar la búsqueda ni el envío. |
| CA-P02 | Generar, 3–5 | La búsqueda y verificación de candidatos se completa en menos de 2 segundos. |
| CA-P03 | Responder, 2 y 6 | La consulta de la oferta y la reprogramación toman menos de 2 segundos cada una en sus operaciones de base de datos. |

Para verificar CA-P02 y CA-P03 se debe definir el volumen de datos, la carga concurrente y el entorno de prueba.

| ID | Dependencia | Trabajo de integración |
|---|---|---|
| DEP-01 | Cancelación e intención | Consumir la intención durable existente. Confirmar sus datos y vías de origen y garantizar una evaluación por cancelación. CA-G01 y CA-G02. |
| DEP-02 | Agenda, disponibilidad y ocupación | Compartir reglas de validación de destino entre emisión y aceptación. Integrar la reserva con consultas de disponibilidad, alta de turnos, bloqueos y cambios de configuración. CA-G03, CA-G06, CA-G07, CA-G11, CA-G16 y CA-R06. |
| DEP-03 | Equipos | Al incorporar Seguimiento, identificar los equipos del candidato y verificar su disponibilidad durante todo el intervalo de destino. CA-G08 y CA-R06. |
| DEP-04 | Planes y sesiones | Al incorporar Seguimiento, identificar plan y sesión, validar orden y separación y conservar la vinculación al trasladar. CA-G09, CA-R06 y CA-R07. |
| DEP-05 | Reprogramación y concurrencia | Coordinar el traslado atómico con respuestas, vencimiento, nuevas reservas y cambios de agenda. Verificar en MariaDB aislada ambos órdenes de aceptación y bloqueo. CA-G16 y CA-R04 a CA-R11. |
| DEP-06 | Enlaces y vigencia | Reutilizar lo aplicable de los enlaces seguros de recordatorios y verificar vinculación al destinatario, consulta sin efectos, vigencia e idempotencia. CA-G12, CA-R01, CA-R03, CA-R04 y CA-R08. |
| DEP-07 | Correo y Auditoría | Integrar oferta, reintentos, fallos y confirmación. Precisar la aplicación de RF12.5 sin extender el vencimiento original. CA-G12 a CA-G14, CA-R10 y CA-P01. |
| DEP-08 | Mis turnos, agenda y recordatorios | Reflejar el horario actualizado e invalidar los enlaces de asistencia emitidos para la programación anterior al completar el traslado. CA-R07 y CA-R10. |
