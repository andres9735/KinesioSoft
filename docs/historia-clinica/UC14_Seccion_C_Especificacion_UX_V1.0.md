# KinesioSoft — Especificación UX V1.0

## UC-14, Sección C — Revisión e incorporación de declaraciones de salud

| Atributo              | Valor                                                                                              |
| --------------------- | -------------------------------------------------------------------------------------------------- |
| Versión               | UX V1.0                                                                                            |
| Estado                | **Aprobada, consolidada y congelada** como especificación de diseño                                |
| Decisiones fuente     | DUX-01, DUX-02, DUX-03, DUX-04, DUX-05, DUX-06 y DUX-07                                            |
| Alcance               | Rediseño visual e interactivo de revisión, vista previa, confirmación y comunicación del resultado |
| Implementación        | Pendiente de inspección técnica, programación y pruebas del rediseño                               |
| Restricción principal | Preservar los contratos y reglas clínicas existentes de UC-14 A/C                                  |

> **Uso del documento.** Esta es la especificación UX consolidada para implementar el rediseño de UC-14 Sección C. Constituye la referencia visual y de interacción; no reemplaza las especificaciones funcionales, los contratos técnicos ni las reglas clínicas existentes. Ante una incompatibilidad, no modificar silenciosamente la lógica clínica: identificarla durante la inspección y comunicarla antes de implementar.

---

## 1. Objetivo, alcance y principios

### 1.1 Objetivo

Reducir la longitud visual y la repetición de información de la revisión profesional de declaraciones de salud. Facilitar la comparación entre los datos originales del paciente y los dictámenes profesionales, la carga de relevamientos complementarios, la identificación de pendientes y la lectura de la vista previa antes de confirmar una incorporación.

### 1.2 Alcance de la intervención

El rediseño comprende:

1. Encabezado de contexto clínico y navegación existente.
2. Formulario dividido en cuatro secciones plegables.
3. Presentación de datos originales, dictámenes y controles condicionales.
4. Relevamientos complementarios y núcleo familiar con presentación compacta.
5. Panel de progreso y navegación a pendientes.
6. Vista previa resumida con comparaciones y trazabilidad bajo demanda.
7. Conformidad profesional, resultado de incorporación mediante **SweetAlert2** y errores contextuales o generales.
8. Adaptación responsive y accesibilidad.

### 1.3 Principios obligatorios

- **Preservación clínica:** la declaración original, los datos previos, las justificaciones y la trazabilidad no se alterarán por un cambio visual.
- **No inferencia:** «Sin información declarada» no equivale a «No presenta».
- **Separación de fuentes:** declaración original, versión clínica anterior y versión propuesta son conceptos distintos.
- **Sin persistencia anticipada:** navegar, expandir, calcular progreso o mostrar una vista previa no incorpora información.
- **Servidor como autoridad:** validaciones, permisos, revalidaciones, idempotencia e integridad siguen siendo responsabilidad de los mecanismos existentes.
- **Mínimo impacto:** reutilizar Blade, JavaScript, estilos, rutas y SweetAlert2 ya empleados por KinesioSoft siempre que sea viable.

---

## 2. Flujo UX de extremo a extremo

1. **Revisión profesional:** la kinesióloga examina la declaración original, emite sus dictámenes y completa los relevamientos aplicables.
2. **Vista previa y confirmación:** consulta el resultado propuesto y su trazabilidad; expresa la conformidad profesional existente y pulsa **Confirmar revisión e incorporar datos**.
3. **Comunicación del resultado:** tras confirmar el resultado efectivo de la operación, se presenta SweetAlert2 de éxito; ante validaciones, conflictos o errores se utiliza el tratamiento correspondiente.

No habrá una pantalla de confirmación adicional, un segundo modal previo de aceptación ni una pantalla independiente de éxito. La visualización de la propuesta no crea una versión clínica.

---

## 3. Pantalla de revisión: estructura y contexto (DUX-01)

### 3.1 Áreas principales

**A. Encabezado de contexto clínico.** Mostrará de forma compacta:

- Paciente.
- Turno de origen, fecha y horario.
- Historia clínica.
- Identificador y estado de la declaración.
- Profesional responsable y especialidad.
- Procedencia general del relevamiento complementario/familiar.
- Acciones autorizadas para volver al turno o expediente, según el contexto y rutas actuales.

Los controles editables de especialidad y procedencia general se ubicarán en el encabezado del formulario, fuera de los acordeones; conservarán sus validaciones.

**B. Formulario de revisión.** Se organizará en los cuatro grupos temáticos definidos en la sección 4.

**C. Panel de resumen y progreso.** Expondrá completitud, decisiones y navegación a pendientes, según la sección 8.

### 3.2 Integración visual

Se conservará la **navegación superior existente** de KinesioSoft. No se incorporarán menús laterales globales, buscadores, campanas de notificaciones u otros componentes de navegación ajenos a este incremento.

En escritorio, el formulario ocupará el área principal y el progreso podrá aparecer lateralmente. En móvil, las áreas se apilarán verticalmente con un resumen compacto, sin desplazamiento horizontal innecesario.

---

## 4. Organización y comportamiento de secciones plegables (DUX-02)

### 4.1 Cuatro secciones principales

| Sección                                            | Contenidos                                                                                                                                               |
| -------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **1. Datos de salud y características personales** | Objetivo de salud, peso, altura, ocupación, actividad física y detalle.                                                                                  |
| **2. Antecedentes declarados**                     | Antecedentes originales sin clasificación y bloques declarativos de cirugías, alergias y medicación habitual; dictámenes y justificaciones.              |
| **3. Relevamientos complementarios**               | Antecedentes personales, familiares, cirugías, alergias y medicación habitual; resultados, aportes individuales y procedencia.                           |
| **4. Núcleo familiar**                             | Resultado del relevamiento, integrantes, vínculos, situaciones de información no disponible y confirmación de vinculación con el contacto de emergencia. |

La separación entre **antecedentes originalmente declarados** y **relevamientos profesionales complementarios** es obligatoria, incluso cuando se refieran a la misma categoría clínica.

### 4.2 Contrato de acordeones

1. Cada sección podrá abrirse y cerrarse **independientemente**, manteniendo varias abiertas a la vez.
2. Cerrar una sección no borrará valores, dictámenes, correcciones, justificaciones o relevamientos ingresados.
3. Cada encabezado mostrará un título reconocible, un control de expansión y el estado `Completa`, `Pendiente` o `Requiere atención`.
4. La completitud dependerá de las reglas y validaciones aplicables; no basta con que haya texto en los campos.
5. Ante validaciones fallidas, la sección afectada se expandirá y se facilitará el foco en el primer control inválido.
6. Los campos condicionales aparecerán cuando el dictamen o relevamiento los requiera; su obligatoriedad no se modificará.
7. Abrir, cerrar o recorrer secciones no producirá persistencia clínica.
8. Los acordeones serán operables mediante teclado y comunicarán el estado expandido/contraído a tecnologías de asistencia.

---

## 5. Datos declarados y dictámenes profesionales (DUX-03)

### 5.1 Patrón de comparación

Cada dato sujeto a dictamen tendrá dos zonas claramente diferenciadas:

- **Declaración original del paciente:** valor inalterado, solo lectura y claramente atribuido al paciente; los datos no informados se identificarán sin convertirlos en respuestas negativas.
- **Decisión profesional:** selección del dictamen y controles condicionales pertinentes, con validaciones y avisos de consecuencia clínica cuando corresponda.

En escritorio, **dato original a la izquierda y decisión profesional a la derecha**. En móvil, ambos se apilarán, primero el dato original.

### 5.2 Dictámenes admitidos

| Dictamen      | Controles adicionales visibles                | Efecto existente a representar                                                  |
| ------------- | --------------------------------------------- | ------------------------------------------------------------------------------- |
| **Validar**   | Ninguno por el solo hecho de seleccionarlo.   | Admitir el valor original conforme a las reglas clínicas.                       |
| **Corregir**  | Campo de valor corregido, del tipo apropiado. | Proponer la corrección sin sobrescribir la declaración original.                |
| **Observar**  | Justificación clínica obligatoria.            | No admitir el dato declarado mediante ese dictamen; conservar la observación.   |
| **Descartar** | Justificación clínica obligatoria.            | Excluir el dato declarado según la decisión justificada; conservar el registro. |

No se introducirán nuevos requisitos de justificación para `Validar` o `Corregir`. Toda validación preexistente permanece aplicable.

### 5.3 Valores originales vacíos

La ausencia de un dato se comunicará como **«Sin información declarada»**; cuando aporte claridad, se explicará que no acredita ausencia de antecedentes.

La declaración vacía no se convertirá automáticamente en **«No presenta»**. El relevamiento complementario es independiente de la declaración original.

### 5.4 Reducción de longitud sin pérdida de información

- Los controles adicionales solo se mostrarán cuando sean pertinentes.
- Las correcciones aparecerán al elegir `Corregir`; las justificaciones exigidas, al elegir los dictámenes correspondientes.
- Los avisos de exclusión serán breves, con posibilidad de lectura del detalle cuando corresponda.
- Se evitarán encabezados extensos repetidos para cada dato.
- Contraer/ocultar un control no debe causar pérdida involuntaria de valores; el tratamiento de un cambio de dictamen seguirá las reglas funcionales actuales.
- Los errores se mostrarán junto al control y se reflejarán en el estado de su sección.

---

## 6. Relevamientos complementarios (DUX-04)

### 6.1 Cinco categorías en tarjetas compactas

La tercera sección principal tendrá cinco tarjetas:

1. Antecedentes personales.
2. Antecedentes familiares.
3. Cirugías.
4. Alergias.
5. Medicación habitual.

Cada tarjeta identificará la categoría y el **resultado seleccionado**, y mostrará solo los controles aplicables a ese resultado. Las categorías conservarán sus validaciones funcionales.

### 6.2 Interpretación de resultados

La interfaz distinguirá, **solo cuando estén admitidos por el dominio actual**, los resultados que expresen presencia de antecedentes, ausencia relevada, información desconocida, falta de relevamiento o ausencia de aporte complementario.

- Si corresponde **presenta antecedentes**, se mostrarán los registros y la acción de agregar.
- Si el resultado no permite agregar antecedentes, se ocultarán los controles de incorporación incompatibles.
- `No presenta`, `Se desconoce` y `No se relevó` —cuando existan en el sistema— no deberán confundirse entre sí.
- No se crearán enumeraciones, opciones, valores persistentes ni equivalencias nuevas.

### 6.3 Aportes individuales

Cada antecedente complementario se presentará en una tarjeta individual, con los atributos existentes que correspondan: **descripción, procedencia individual, estado de codificación y demás campos actualmente implementados**.

Se mantendrá **Agregar antecedente complementario** y la posibilidad de varios registros cuando lo permitan las reglas funcionales.

Quitar un registro en preparación solo afectará la propuesta actual, conforme al comportamiento existente, y no eliminará antecedentes clínicos previamente incorporados.

La procedencia individual (p. ej., información referida por el paciente frente a relevamiento profesional) no será sustituida automáticamente por la procedencia general del encabezado.

### 6.4 Cambios de resultado y validaciones

Cambiar un resultado aplicará las reglas existentes para los valores que dejen de ser pertinentes, evitando su pérdida involuntaria y evitando incorporar valores incompatibles. Los errores estarán asociados al aporte o control afectado.

---

## 7. Núcleo familiar y contacto de emergencia (DUX-04)

### 7.1 Resultado e integrantes

La cuarta sección principal permitirá registrar o actualizar el relevamiento familiar mediante los controles existentes. Mostrará claramente el resultado seleccionado y los campos compatibles con dicho resultado.

Cuando el resultado permita informar integrantes, especialmente `Informado` cuando corresponda al dominio actual, cada integrante se mostrará en una tarjeta con su identificación, vínculo y demás atributos existentes. Se mantendrán las acciones para agregar, modificar y quitar integrantes de la propuesta.

Las situaciones de ausencia, desconocimiento o falta de relevamiento se mostrarán con precisión, **sin deducir ausencia de integrantes a partir de campos vacíos**.

### 7.2 Contacto de emergencia

- La persona designada como contacto de emergencia **no se considerará automáticamente integrante del núcleo familiar**.
- Su vinculación requerirá la confirmación expresa prevista por el sistema.
- El control de vinculación será contextual: solo se mostrará cuando el resultado familiar y las reglas actuales permitan registrar integrantes.
- No se alterará automáticamente la identidad ni los datos del contacto original.

### 7.3 Conservación y accesibilidad

Expandir, contraer y recorrer los registros no borrará información. Los cambios de resultado se resolverán conforme a las validaciones existentes. Las tarjetas serán utilizables con teclado y adaptables a pantallas pequeñas.

---

## 8. Panel de progreso, completitud y navegación (DUX-05)

### 8.1 Indicadores obligatorios

El panel calculará dinámicamente desde el estado **real** del formulario:

- Secciones completas respecto de **4** (incluida una representación visual proporcional).
- Número de datos con dictamen `Corregir`.
- Número de datos con dictamen `Observar`.
- Número de datos con dictamen `Descartar`.
- Número de requisitos obligatorios pendientes, sin doble conteo de un mismo problema.
- Estado de cada una de las cuatro secciones.
- Acceso directo a secciones y pendientes.

Los contadores son informativos. Un dictamen `Corregir`, `Observar` o `Descartar` correctamente satisfecho **no constituye por sí mismo un error**. No se persistirá el progreso.

### 8.2 Estados por sección

| Estado                | Definición                                                                                         |
| --------------------- | -------------------------------------------------------------------------------------------------- |
| **Completa**          | Se satisfacen todos los requisitos aplicables a la sección.                                        |
| **Pendiente**         | Faltan decisiones o información requerida, sin un error de validación necesariamente identificado. |
| **Requiere atención** | Hay al menos una inconsistencia o validación incumplida que ya fue detectada.                      |

Cuando concurran pendientes y errores, tendrá prioridad visual **Requiere atención**. Los estados no alterarán los estados clínicos persistidos.

### 8.3 Navegación al problema

- Seleccionar una sección en el panel la identificará, expandirá si está cerrada y desplazará la vista a su contenido.
- Seleccionar un error o pendiente concreto enfocará el control pertinente cuando haya uno disponible.
- **Ir al primer pendiente** seguirá el orden visual del formulario, priorizando errores de validación ya identificados.
- Ninguna navegación alterará dictámenes, valores ni persistencia.

### 8.4 Acceso a vista previa

El botón **Ver vista previa de incorporación** permanecerá disponible durante la revisión. Al pulsarlo:

1. Se ejecutarán las validaciones funcionales existentes.
2. Si se cumplen, se accederá a la vista previa.
3. Si no se cumplen, el formulario permanecerá disponible; se expandirá la sección del problema y se facilitará el foco en el campo correspondiente.

El progreso del navegador no autorizará por sí mismo la incorporación ni sustituirá validaciones del servidor.

### 8.5 Responsive

En escritorio podrá mostrarse como panel lateral visible durante el desplazamiento si el tamaño lo permite. En móvil será compacto y desplegable, sin bloquear el formulario ni impedir el acceso a pendientes.

---

## 9. Vista previa de incorporación (DUX-06)

### 9.1 Objetivo y estructura

La vista previa presentará **primero el resultado clínico propuesto**, en lugar de repetir todo el formulario y todas las comparaciones expandidas. Contendrá cinco áreas:

1. **Encabezado y estado** de propuesta.
2. **Resumen cuantitativo** de dictámenes.
3. **Resultado clínico propuesto**, por categorías.
4. **Comparación y trazabilidad** consultables bajo demanda.
5. **Retorno y confirmación** mediante las operaciones existentes.

El encabezado identificará paciente, historia clínica, declaración, turno vinculado, profesional, especialidad y versión propuesta cuando esté disponible. Una versión propuesta **no se presentará como registrada**.

### 9.2 Resumen de decisiones

Se mostrarán contadores para `Validar`, `Corregir`, `Observar` y `Descartar`, obtenidos de las decisiones efectivas. Los relevamientos complementarios se presentarán diferenciados de estos dictámenes.

Los valores no se simularán ni se usarán para reemplazar validaciones.

### 9.3 Resultado propuesto por categorías

Se mostrarán los valores finales que se registrarían si la operación se confirma, agrupados en:

- Datos de salud y características personales.
- Antecedentes y relevamientos complementarios.
- Núcleo familiar, cuando corresponda.
- Datos no admitidos (observados/descartados) y su tipo de dictamen.
- Información clínica anterior conservada, **solo si la lógica existente efectivamente la mantiene**.

Se identificarán visualmente las correcciones. Agrupar o resumir información no modificará el contenido de la versión resultante.

### 9.4 Tres fuentes distintas

| Fuente                        | Qué representa                                                                          |
| ----------------------------- | --------------------------------------------------------------------------------------- |
| **Declaración original**      | Información que informó el paciente, conservada sin sobrescritura.                      |
| **Versión clínica anterior**  | Datos incorporados profesionalmente con anterioridad, cuando exista una versión previa. |
| **Versión clínica propuesta** | Información que quedaría registrada tras la confirmación.                               |

La preservación de la declaración original es independiente de conservar datos clínicos previos. No se mostrarán comparaciones ficticias cuando no exista versión anterior. Un dato observado o descartado no se presentará como admitido solo porque permanezca en la declaración original.

### 9.5 Comparación y trazabilidad bajo demanda

Los detalles de cada dato podrán expandirse y contraerse. Según corresponda, permitirán consultar:

- Valor declarado originalmente.
- Valor clínico previo.
- Valor propuesto.
- Dictamen.
- Justificación **íntegra**.
- Procedencia.
- Profesional responsable y trazabilidad disponible.

No se inventarán comparaciones para datos sin fuente original o anterior. Ocultar el detalle inicialmente **no debe impedir acceder** a ninguna modificación, exclusión o decisión clínicamente significativa.

### 9.6 Apartados sin información

Se evitarán repeticiones como «versión previa sin datos / se conservaría sin datos / se sustituiría sin datos» mediante mensajes semánticamente precisos:

- **Sin información declarada**.
- **Sin información clínica previa**.
- **Sin datos propuestos para incorporar**.

No se sustituirá la ausencia de información por `No presenta`. Las situaciones de conservación, sustitución o exclusión significativas deberán seguir siendo consultables.

### 9.7 Retorno y límites

**Volver a la revisión** conservará los valores y decisiones conforme al mecanismo existente. La continuación utilizará la confirmación funcional ya implementada.

Consultar la vista previa no realizará incorporación, no modificará declaraciones ni creará versiones. Si la propuesta se vuelve inválida, se aplicará la recuperación funcional existente, sin persistencia parcial.

Los detalles serán operables mediante teclado, anunciarán su estado y se presentarán en disposición vertical en móvil cuando sea necesario.

---

## 10. Conformidad profesional y confirmación (DUX-07)

### 10.1 Ubicación y contenido

La confirmación ocurrirá **dentro de la vista previa**, sin paso intermedio adicional. Se mantendrá el control de conformidad profesional existente, con su obligatoriedad y validación.

- La casilla no estará premarcada.
- El texto describirá claramente la conformidad y la incorporación.
- Acción principal: **Confirmar revisión e incorporar datos**.
- Acción secundaria: **Volver a la revisión**.
- No se añadirá una nueva aceptación o un segundo modal de confirmación.

### 10.2 Procesamiento seguro

Al enviarse la solicitud, la interfaz comunicará que se encuentra procesando y evitará dobles envíos involuntarios. Estas medidas visuales no reemplazarán la autorización, revalidaciones, idempotencia, auditoría e integridad transaccional del servidor.

Solo podrá comunicarse éxito después de contar con evidencia efectiva del resultado de la operación.

---

## 11. Comunicación del resultado y errores con SweetAlert2 (DUX-07)

### 11.1 Éxito

La incorporación confirmada se mostrará mediante un **modal SweetAlert2 de éxito**, reutilizando la integración y convenciones de KinesioSoft. **No habrá pantalla independiente de resultado exitoso.**

El modal incluirá:

- Título: **Revisión confirmada e incorporada**.
- Breve descripción de los datos admitidos y del estado revisado de la declaración.
- Identificador de historia clínica.
- Versión clínica **efectivamente registrada**.
- Identificador y estado final de la declaración.
- Profesional responsable, cuando corresponda.
- Nota: la incorporación no inicia automáticamente la atención ni modifica por sí misma el estado del turno.

**Acción principal:** Consultar expediente actualizado.

**Acción secundaria:** Volver al turno, cuando ese destino exista y sea autorizado según el contexto.

Los destinos serán los ya disponibles; no se crearán rutas nuevas para mostrar el resultado. El aviso no se cerrará automáticamente por tiempo. Cerrar el modal, cuando se permita, no repetirá la operación ni hará aparecer la declaración como pendiente otra vez.

### 11.2 Validación de formulario

Los errores de campos específicos aparecerán **junto a esos controles**, con expansión de la sección afectada y acceso al primer campo inválido. SweetAlert2 no sustituirá esta validación contextual.

### 11.3 Propuesta desactualizada o conflicto

Los conflictos, cambios de estado y propuestas obsoletas tendrán mensajes comprensibles, preferentemente mediante SweetAlert2 de advertencia cuando corresponda. La acción de recuperación se ajustará a las operaciones ya previstas. No se podrá confirmar una propuesta que haya quedado inválida eludiendo el servidor.

### 11.4 Error técnico o resultado incierto

Los errores generales podrán mostrarse en SweetAlert2 de error sin exponer datos sensibles ni detalles técnicos. Cuando no esté claro si se registró una operación, la interfaz **no afirmará** éxito o fracaso definitivo sin comprobarlo. No se introducirán reintentos automáticos que puedan duplicar incorporaciones.

### 11.5 Acceso y privacidad

Los errores de autorización respetarán las restricciones actuales y no expondrán información clínica a usuarios no autorizados.

### 11.6 Accesibilidad de modales

Los mensajes y acciones serán operables mediante teclado, gestionarán el foco de manera coherente, no dependerán solo del color y se adaptarán a pantallas pequeñas sin recortar información identificatoria o acciones.

---

## 12. Compatibilidad responsive, accesibilidad y tratamiento de errores

1. Mantener jerarquía comprensible de encabezados y asociaciones etiqueta/control.
2. Identificar el estado expandido/contraído y mantener acceso por teclado.
3. Asociar los errores a controles concretos, y anunciar los mensajes comprensiblemente.
4. Al navegar al error, abrir su sección, desplazar la página y enfocar cuando sea posible.
5. Presentar datos originales y decisiones en dos columnas en escritorio y apilados en móviles.
6. Conservar la lectura íntegra de valores, justificativos y trazabilidad, incluidos textos extensos.
7. Mantener botones y modales utilizables en tamaños pequeños.
8. Evitar depender exclusivamente del color para comunicar completitud, correcciones, exclusiones o resultados.
9. Garantizar que cerrar secciones, consultar detalles o navegar dentro de una propuesta no genere escrituras clínicas.

---

## 13. Límites estrictos de implementación

Esta especificación **no autoriza**:

- Modificar declaraciones originales.
- Modificar los cuatro dictámenes y sus efectos, las justificaciones exigidas o los requisitos clínicos mínimos.
- Cambiar los resultados admitidos de relevamientos ni inferir ausencia clínica a partir de datos vacíos.
- Modificar procedencia individual/general, antecedentes, núcleo familiar, contacto de emergencia o sus reglas.
- Cambiar autorización, permisos, políticas, auditoría o trazabilidad.
- Cambiar versionado, conservación de versiones anteriores, idempotencia, transacciones o concurrencia.
- Alterar la independencia entre incorporación clínica y estado del turno.
- Modificar el esquema de la base de datos ni ejecutar migraciones.
- Crear nuevas entidades, estados clínicos persistidos, guardado automático o borradores persistentes.
- Introducir rutas nuevas o contratos HTTP sin inspección y autorización específica.
- Añadir una pantalla de éxito independiente o una segunda confirmación.
- Añadir reintentos automáticos o comunicar resultados no confirmados.
- Sustituir validaciones o controles del servidor por lógica solo del navegador.

**Regla de inspección:** si el código existente exige modificar un contrato funcional para cumplir alguna decisión visual, detener ese cambio, documentar el obstáculo y proponer primero una alternativa compatible.

---

## 14. Criterios de aceptación del rediseño UX

| ID       | Criterio verificable                                                                           |
| -------- | ---------------------------------------------------------------------------------------------- |
| UX-CA-01 | Se conserva la navegación superior existente.                                                  |
| UX-CA-02 | El encabezado identifica paciente, turno, historia, declaración y contexto profesional reales. |
| UX-CA-03 | El formulario tiene cuatro secciones principales conforme a esta especificación.               |
| UX-CA-04 | Las secciones pueden expandirse independientemente y permanecer varias abiertas.               |
| UX-CA-05 | Contraer y expandir no hace perder información ingresada.                                      |
| UX-CA-06 | La declaración original se presenta en solo lectura y con su procedencia.                      |
| UX-CA-07 | Se presentan correctamente Validar, Corregir, Observar y Descartar.                            |
| UX-CA-08 | Los campos condicionales respetan el dictamen y sus validaciones existentes.                   |
| UX-CA-09 | Los datos originalmente vacíos no se interpretan como ausencia clínica.                        |
| UX-CA-10 | Se muestran cinco categorías compactas de relevamientos complementarios.                       |
| UX-CA-11 | Los aportes individuales, su procedencia y su codificación aplicable se conservan.             |
| UX-CA-12 | Se presentan y gestionan integrantes familiares según las reglas existentes.                   |
| UX-CA-13 | La vinculación del contacto de emergencia sigue siendo explícita y no automática.              |
| UX-CA-14 | El progreso refleja datos actuales y cuatro secciones.                                         |
| UX-CA-15 | Completa, Pendiente y Requiere atención se calculan según requisitos reales.                   |
| UX-CA-16 | Los contadores reflejan dictámenes efectivos sin considerarlos errores automáticamente.        |
| UX-CA-17 | La navegación hacia pendientes abre secciones y enfoca los campos pertinentes.                 |
| UX-CA-18 | Las validaciones existentes impiden pasar a vista previa cuando corresponde.                   |
| UX-CA-19 | La vista previa agrupa los valores clínicos propuestos por categorías.                         |
| UX-CA-20 | Correcciones, observaciones y descartes son reconocibles.                                      |
| UX-CA-21 | La comparación y justificación completas pueden consultarse bajo demanda.                      |
| UX-CA-22 | Se distingue declaración original, versión anterior (si existe) y propuesta.                   |
| UX-CA-23 | Los apartados sin información se presentan sin repeticiones ni inferencias clínicas.           |
| UX-CA-24 | Volver a revisión conserva la propuesta conforme al mecanismo actual.                          |
| UX-CA-25 | La conformidad profesional existente sigue siendo expresa y obligatoria.                       |
| UX-CA-26 | Consultar la vista previa no persiste datos ni genera versiones.                               |
| UX-CA-27 | El éxito real se comunica mediante SweetAlert2, sin pantalla independiente.                    |
| UX-CA-28 | SweetAlert2 informa datos confirmados y ofrece destinos autorizados.                           |
| UX-CA-29 | Las validaciones de campo se muestran contextualmente.                                         |
| UX-CA-30 | Los conflictos y errores no comunican resultados falsos ni generan reintentos inseguros.       |
| UX-CA-31 | La interfaz es adaptable a escritorio y móvil sin pérdida de información.                      |
| UX-CA-32 | Acordeones, campos y modales son operables mediante teclado.                                   |
| UX-CA-33 | Se preservan declaración original, reglas clínicas, autorización, trazabilidad y turnos.       |
| UX-CA-34 | Las pruebas funcionales existentes relevantes continúan superándose.                           |

---

## 15. Preparación, implementación y verificación

### 15.1 Inspección técnica previa (solo lectura)

Antes de editar código, verificar de manera acotada:

- Vistas Blade, componentes y scripts que implementan revisión y vista previa.
- Contratos reales de los campos y dictámenes que consume el backend.
- Mecanismo actual para conservar la propuesta al volver del resumen.
- Gestión actual de errores y relación entre validaciones servidor/navegador.
- Estructura de datos ya preparada por el servidor para la vista previa.
- Contrato HTTP de la confirmación, redirecciones, mensajes y compatibilidad con la integración SweetAlert2 existente.
- Tests actuales de UC-14 C y archivos mínimos de impacto.

No modificar nada en esta inspección. Si una decisión UX parece requerir un cambio funcional, informar primero.

### 15.2 Secuencia sugerida de implementación

1. **Formulario de revisión:** encabezado, cuatro secciones, dictámenes/relevamientos en tarjetas y panel de progreso.
2. **Vista previa:** resumen clínico y comparación/trazabilidad bajo demanda.
3. **Confirmación y resultados:** integración SweetAlert2 y casos de error, reutilizando los contratos existentes.

Cada incremento deberá acompañarse de pruebas focalizadas relevantes y una breve verificación visual/manual. No ejecutar migraciones ni alterar bases preexistentes por este rediseño.

### 15.3 Evidencia y límites de la certificación

La implementación funcional de UC-14 A/C y su verificación técnica anterior son una **línea base**, no evidencia de que el nuevo diseño ya esté implementado.

El conjunto focalizado de pruebas SQLite de UC-14 A/C fue informado como **255 casos, 1.552 aserciones, PASS** antes del rediseño. Después de programar deberán repetirse las pruebas pertinentes y verificarse la interacción nueva.

La **concurrencia real de MariaDB** se mantiene como certificación técnica separada pendiente; una prueba SQLite no la sustituye.

### 15.4 Cierre de aceptación

El rediseño estará listo para su aceptación cuando cumpla los criterios UX aplicables, supere las pruebas focalizadas de regresión y se comprueben manualmente revisión, vista previa, conformidad, SweetAlert2 y recuperación de errores. La aprobación del **diseño UX V1.0** no equivale todavía a la aprobación de su implementación.

---

## 16. Trazabilidad de las decisiones aprobadas

| Decisión | Materia consolidada                                     | Secciones de esta especificación |
| -------- | ------------------------------------------------------- | -------------------------------- |
| DUX-01   | Estructura general, contexto, navegación y adaptación   | 1, 3, 12, 13                     |
| DUX-02   | Cuatro secciones plegables y comportamiento             | 4, 12                            |
| DUX-03   | Declaración original, dictámenes y campos condicionales | 5                                |
| DUX-04   | Relevamientos complementarios y núcleo familiar         | 6, 7                             |
| DUX-05   | Progreso, completitud y navegación a pendientes         | 8                                |
| DUX-06   | Vista previa clínica, comparaciones y trazabilidad      | 9                                |
| DUX-07   | Conformidad, SweetAlert2, errores y recuperación        | 10, 11                           |

**Referencia de origen:** documento «Decisiones DUX para rediseño de pantalla Historia Clínica.docx», con DUX-01 a DUX-07 aprobadas y congeladas. Este archivo consolida esas decisiones sin reabrirlas. Las convenciones o valores exactos del dominio y los destinos de navegación deben contrastarse con el código y las especificaciones funcionales vigentes.

**ESTADO FINAL: ESPECIFICACIÓN UX V1.0 APROBADA, CONSOLIDADA Y CONGELADA COMO BASE PARA LA INSPECCIÓN TÉCNICA E IMPLEMENTACIÓN DEL REDISEÑO DE UC-14 SECCIÓN C.**
