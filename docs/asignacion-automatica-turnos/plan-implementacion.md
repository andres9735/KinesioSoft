# Plan de implementación por subpasos

**Alcance.** Primer incremento operativo para candidatos de Primera consulta con la misma kinesióloga. El cupo puede provenir de una cancelación elegible de Primera consulta o Seguimiento.

| Subpaso | Resultado esperado |
|---|---|
| 1. Base e integración | Confirmar el commit base, los datos de la intención y los puntos de lectura y escritura de agenda afectados. Usar las reglas aprobadas de admisión del destino y cambios de agenda con oferta activa. |
| 2. Validación del destino | Implementar el validador compartido de inicio exacto, duración registrada, encaje, franja vigente, bloqueos y cruces de kinesióloga y paciente. |
| 3. Selección de candidatos | Aplicar tipo, estado, profesional, ventana de 7 días, orden de prioridad, desempates, correo válido y exclusión por oferta previa para la misma cancelación. |
| 4. Oferta y reserva temporal | Registrar oferta y vencimiento. Integrar la reserva con disponibilidad, alta de turnos y cambios de agenda mediante un protocolo transaccional común. |
| 5. Consumo y primera emisión | Procesar la intención en segundo plano una vez por cancelación. Verificar el umbral de 12 horas al cancelar, el margen de 4 horas al activar y la vigencia de 2 horas. Enviar el correo con enlace seguro. |
| 6. Consulta y salidas sin traslado | Mostrar los datos de la oferta. Resolver rechazo, vencimiento sin interacción, fallos de correo y continuación de la evaluación del mismo cupo cuando corresponda. |
| 7. Aceptación y traslado atómico | Revalidar y mover el mismo turno. Resolver la reserva, liberar el intervalo anterior, auditar el cambio e invalidar las autorizaciones de asistencia anteriores. Asegurar idempotencia y ausencia de otra intención por adelanto. |
| 8. Comunicación del resultado | Reflejar el nuevo horario y estado en pantalla, Mis turnos y agenda. Enviar el correo de confirmación y registrar un eventual fallo sin revertir el traslado. |
| 9. Verificación y cierre | Ejecutar la regresión de cancelaciones, agenda y recordatorios. Verificar concurrencia, rollback y duplicados con MariaDB aislada, incluidos ambos órdenes de aceptación y bloqueo. Comprobar las cotas de rendimiento bajo una carga definida. |
