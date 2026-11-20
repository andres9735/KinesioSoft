# Asignación Automática de Turnos — Segunda instancia

## Estado actual

En la demostración de la instalación habitual de KinesioSoft se
observó el registro de una cancelación, una oferta de adelanto para
una Primera consulta, su aceptación, la actualización del mismo
turno y de la agenda, y el correo de confirmación del cambio.
El turno conservó su estado al cambiar de horario.

Queda pendiente corroborar qué comando o proceso generó esa oferta
y qué mecanismo de selección utilizó. El operador local
`ComandosDemoTurnos::cupo()` admite restringir el selector al turno B
mediante `turnoPermitido`; con ese ID solo considera ese turno,
mantiene las validaciones de elegibilidad y no busca un reemplazo
si resulta incompatible. Con el valor predeterminado `null`, el
selector considera el conjunto general y aplica su prioridad.
No se encontró un registro de ejecución que permita atribuir el uso
de esa restricción a la oferta demostrada.

La [guía del operador local](../../storage/framework/testing/demo-real-20261117/GUIA-COMANDOS.md)
describe el comando restringido, pero su registro solo consigna una
ejecución anterior de `identificar` sobre la base habitual; no
acredita qué comando generó la oferta posterior. La demostración
por sí sola no acredita la selección automática general ni la
operación sostenida del Scheduler y los workers. La evidencia
automatizada de selección y del ciclo general conserva el alcance
y los entornos documentados en el
[cierre del primer incremento](asignacion-automatica-turnos-09-cierre.md).

## Pendientes funcionales

1. Tomar como referencia las
   [decisiones funcionales aprobadas, puntos 1, 3 y 5](../asignacion-automatica-turnos/decisiones-funcionales.md),
   los [casos de uso](../asignacion-automatica-turnos/casos-de-uso.md)
   y la [matriz de criterios y dependencias](../asignacion-automatica-turnos/matriz-criterios-aceptacion-dependencias.md)
   para incorporar turnos de Seguimiento como candidatos en un
   incremento posterior al alcance actual de Primera consulta.

2. Aplicar las reglas ya aprobadas: misma kinesióloga, estado
   Pendiente o Confirmado e inicio posterior al del cupo y hasta
   siete días corridos después, inclusive. Seguimiento puede ocupar
   un cupo liberado por cualquiera de los dos tipos si el destino
   admite al candidato; empieza al inicio exacto del cupo y conserva
   su duración registrada. Entre candidatos elegibles de ambos
   tipos rige la misma prioridad: inicio más lejano, registro más
   temprano e identificador menor (CA-G04 a CA-G10).

   Quedan pendientes las dependencias para aplicar esas reglas a
   Seguimiento: identificar todos los equipos del candidato y
   comprobar su disponibilidad durante el intervalo completo
   (DEP-03 / CA-G08); identificar el plan y la sesión vinculados,
   verificar orden y separación y conservar la misma sesión y su
   vínculo al trasladar (DEP-04 / CA-G09). Integrar la admisión del
   tipo y estas comprobaciones en la emisión y la revalidación al
   aceptar (DEP-02 / CA-G07 / CA-R06 y CA-R07). Si una restricción
   aplicable falla o no puede verificarse, excluir al candidato o
   impedir el traslado, según la etapa, conservando su turno original.

3. Ampliar la selección de candidatos y reutilizar el flujo
   existente de oferta, aceptación, rechazo y vencimiento.
   Al aceptar, el sistema debe trasladar el mismo turno y
   conservar su estado.

4. Probar la selección con candidatos de Seguimiento, la
   prioridad cuando haya ambos tipos de consulta, la exclusión
   de turnos incompatibles y la continuidad tras un rechazo
   o vencimiento. Verificar que el flujo de Primera consulta
   siga funcionando y que no se duplique el despacho lógico de cada
   oferta, incluso ante referencias repetidas en cola.

   Según el [contrato de salidas y reintentos](asignacion-automatica-turnos-06-salidas.md)
   y el [contrato de comunicación](asignacion-automatica-turnos-08-comunicacion.md),
   verificar que los reintentos temporales conserven la misma oferta,
   enlace y vencimiento original, respetando RF12.5: hasta tres
   reintentos con esperas de 5/10/20 minutos, dentro de una hora desde
   el primer fallo observado y antes del vencimiento. Ante un
   resultado de transporte incierto, comprobar que quede registrado
   y no provoque un reenvío automático. Para la confirmación del
   cambio, verificar un despacho lógico por aceptación y que un fallo
   del correo no revierta el traslado. No se garantiza entrega SMTP
   exactamente una vez ni recepción o lectura en el buzón.

## Comprobaciones operativas pendientes

- Después de encender Windows, verificar que el worker arranque
  automáticamente y que el Scheduler registre ejecuciones
  recientes con VS Code cerrado.
- Revisar en Mailpit el próximo correo generado por el flujo
  habitual para confirmar que use el diseño nuevo, ya verificado
  con los correos de prueba.
