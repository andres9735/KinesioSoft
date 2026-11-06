# Aclaración técnica transversal UC–28 / UC–27 — Sobre técnico durable de entrega con cifrado autenticado.

**Fecha:** 06/11/2026.

**Estado:** APROBADA Y CONGELADA.

**Naturaleza:** aclaración complementaria de AT-10 y contratos GA pertinentes; no sustituye las decisiones funcionales congeladas.

## Reglas aprobadas

### R1 — Persistencia exclusiva del hash

Los registros de `EnlaceAsistenciaTurno` conservarán exclusivamente el hash criptográfico de la credencial original. No se permitirá persistir el token original en texto plano dentro del registro del enlace.

### R2 — Sobre técnico cifrado y separado

Se autoriza conservar temporalmente el material indispensable para reconstruir la URL de entrega mediante un sobre técnico durable, separado de los registros de enlaces, protegido mediante cifrado autenticado. Se prohíbe almacenar ese material en texto plano persistente.

### R3 — Atomicidad

La emisión del enlace y la preparación de su sobre de entrega deberán ser atómicas. No podrá consolidarse una nueva emisión destinada a notificación si falla la preparación durable del material necesario para su entrega.

### R4 — Jobs sin credenciales

Los Jobs y mensajes persistentes de procesamiento transportarán únicamente identificadores o referencias técnicas. No contendrán tokens originales, URLs con credenciales ni copias del material descifrado.

### R5 — Acceso restringido y claves

La recuperación y el descifrado del sobre estarán restringidos a los componentes autorizados del proceso de despacho. Las claves deberán protegerse adecuadamente y contar con un mecanismo de rotación que preserve el tratamiento seguro de los sobres pendientes.

### R6 — Revalidación previa al envío

Antes de efectuar cada intento de entrega se verificará que el enlace continúe vigente, no esté revocado y corresponda a la programación autorizada. La disponibilidad del sobre no otorgará por sí misma autorización para enviar un enlace inválido.

### R7 — Vigencia y eliminación

El sobre tendrá una vigencia limitada y un ciclo de vida explícito. Su material recuperable se eliminará cuando finalice la necesidad de entrega o reintentos autorizados, incluidos los casos de agotamiento del período permitido. No se conservará indefinidamente.

### R8 — Idempotencia y auditoría

Se mantendrán la idempotencia de emisión y envío, la auditoría sin exposición de credenciales y la distinción entre resultados conocidos, fallidos e inciertos. Una interrupción del proceso no autorizará a crear automáticamente un segundo enlace para sustituir el original.

### R9 — Preservación de UC–06

No se modificarán las reglas, transiciones, plazos ni postcondiciones de `ConfirmarAsistenciaTurnoAction` y `CancelarTurnoAction`. El sobre técnico pertenece al mecanismo de entrega, no a la lógica de confirmación o cancelación.

### R10 — Frontera UC–27

La integración definitiva del correo, los Jobs, las colas, los reintentos técnicos y Mailpit se realizará en los subpasos aprobados de UC–27 (6.30–6.36). En UC–28 solamente se incorporará la infraestructura mínima indispensable para resolver la dependencia de entrega durable.

## Consecuencias

- Un único enlace lógico por turno/ciclo.
- Reintentos sin invalidar enlaces previamente enviados.
- El sobre no constituye autorización adicional del paciente.
- Los resultados inciertos no garantizan entrega exactamente una vez.
- MariaDB, concurrencia y correo real requieren certificación posterior.

## Alcance documental

Esta decisión fue aprobada por el usuario el 06/11/2026 y se incorpora íntegramente en este checkpoint. Complementa la aclaración de seguridad de 6.04 y los contratos AT/GA aplicables, sin editar los ocho documentos originales de fuentes congeladas.

La certificación local de la infraestructura mínima de UC–28 consta en [el certificado de 6.26–6.29](turnos-agenda-diaria-626-629-uc28.md). La aprobación de estas reglas no certifica componentes futuros de UC–27 ni autoriza automáticamente su ejecución.
