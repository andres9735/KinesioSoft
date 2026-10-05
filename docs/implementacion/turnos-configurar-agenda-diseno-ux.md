# UC–XX Configurar agenda de atención — Diseño UX navegable

## 1. Propósito y alcance

Este documento congela la experiencia navegable del caso de uso **UC–XX Configurar agenda de atención** antes de diseñar su implementación técnica. Define las pantallas, estados, formularios, acciones, retornos, validaciones visibles y comportamiento responsive necesarios para que una kinesióloga administre sus reglas habituales de disponibilidad y sus bloqueos excepcionales.

El alcance cubre exclusivamente:

- Sección A: crear una primera configuración, crear una nueva configuración con efecto hacia adelante y editar una configuración futura todavía no iniciada.
- Sección B: cambiar la duración estándar mediante una nueva vigencia y ajustar la matriz semanal cuando sea incompatible.
- Sección C: registrar un bloqueo excepcional y revisar advertencias por turnos u otros bloqueos superpuestos antes de confirmarlo.
- Sección D: consultar configuraciones finalizadas y configuraciones futuras anuladas.
- Sección E: anular lógicamente un bloqueo conservando su información original.
- Sección F: anular lógicamente una configuración futura no iniciada y comunicar el reajuste prospectivo de continuidad.

No se diseñan clases Laravel, rutas finales, Requests, Actions, Policies, consultas, transacciones, migraciones, índices ni pruebas. Tampoco se incorporan flujos de solicitud, programación, reprogramación o cancelación de turnos. Los turnos existentes sólo se consultan para advertir impactos; este UC no los modifica.

La única actriz funcional es la kinesióloga autenticada con perfil completo. No se diseña gestión administrativa de agendas.

## 2. Fuentes y jerarquía aplicada

La especificación aplica la siguiente jerarquía, de mayor a menor autoridad:

1. `Diccionario_de_Datos_vigente.txt` y su documento equivalente: estructura persistida, relaciones, claves y reglas estructurales.
2. `UC-XX_Configurar_agenda_atencion.txt` y su documento equivalente: flujo principal, secciones A–F, excepciones y postcondiciones.
3. `Modelo_Relacional_KinesioSoft_vigente.jpg`: confirmación visual del vínculo de configuraciones y bloqueos con la kinesióloga, y de las franjas con su configuración.
4. Decisiones explícitas del subpaso y decisiones complementarias congeladas en `README_Codex_sources.md` y `AGENTS.md`.
5. `Casos_de_Usos_relacionados_con_Modulo_de_Turnos.txt`: contexto para comprender el consumo futuro de la disponibilidad, sin ampliar el alcance.
6. Prototipo DeepSeek: referencia de jerarquía visual, cards, matriz, filtros y densidad de información; nunca fuente de persistencia o reglas.
7. Convenciones actuales de KinesioSoft: layout Blade, encabezados, cards, formularios, tablas desktop, cards mobile, paginación, componentes de campos y confirmación global con SweetAlert2.

No se detectó una contradicción entre el Diccionario, el UC y el Modelo Relacional para este diseño. Cuando el prototipo difiere, esta especificación lo adapta a las fuentes superiores.

## 3. Principios UX congelados

1. **Una fecha objetivo, una configuración aplicable.** La interfaz nunca sustituye la configuración de hoy por la última histórica ni por la próxima futura. Si hoy cae en un hueco, muestra “Sin configuración vigente”.
2. **Cambios con efecto hacia adelante.** Crear horarios sucesores o cambiar duración no sobrescribe condiciones ya iniciadas ni modifica turnos otorgados.
3. **Estado derivado, no editable.** “Vigente”, “Futura”, “Histórica”, “Anulada”, “Próxima”, “En curso” y “Pasado” se calculan a partir de fechas y anulaciones; nunca aparecen como campos editables.
4. **Formularios complejos en página completa.** Matrices semanales, revisión de advertencias y motivos obligatorios necesitan URL navegable, errores persistentes y espacio móvil. No se alojan en modales altos.
5. **Confirmación separada de captura.** El formulario reúne y valida los datos; SweetAlert2 confirma la acción final y muestra feedback global. No captura el motivo obligatorio.
6. **Trazabilidad visible.** Los registros anulados se consultan con sus datos originales, fecha de anulación y motivo. No se presentan como eliminados.
7. **Bloqueos independientes.** La UI no obliga a elegir una configuración para registrar un bloqueo y admite solapamientos entre bloqueos.
8. **Advertir sin bloquear lo permitido.** La presencia de turnos afectados u otros bloqueos superpuestos se explica antes de confirmar un bloqueo, pero no impide registrarlo.
9. **No prometer disponibilidad.** Anular un bloqueo o una configuración provoca recálculo, pero la interfaz no afirma que un intervalo quedó libre sin considerar otras restricciones.
10. **Misma aplicación, adaptación responsive.** Desktop usa tablas y composición multicolumna; mobile usa orden vertical y cards sin scroll horizontal.
11. **Lenguaje de dominio.** Se usan “Consultar”, “Editar”, “Anular”, “Vigente”, “Futura” e “Histórica”. No se trasladan “Activo/Inactivo”, “Inactivar/Reactivar” ni otros patrones propios de Catálogos.
12. **Zona horaria del proyecto.** “Hoy” y “ahora” se presentan y comparan según la configuración temporal de KinesioSoft, sin una zona horaria fijada en la interfaz.

### Decisión página, modal y SweetAlert2

| Operación | Contenedor canónico | Motivo |
|---|---|---|
| Crear primera/nueva configuración | Página dedicada AGENDA-02 | Incluye vigencia, duración, siete días, múltiples franjas y errores cruzados. |
| Editar configuración futura | Página dedicada AGENDA-03 | Debe conservar datos tras errores y permitir corregir una matriz completa. |
| Cambiar duración | Página dedicada AGENDA-04 | Debe mostrar la matriz heredada y hacer corregibles las incompatibilidades. |
| Consultar configuración | Página dedicada AGENDA-05 | La matriz completa y los datos de anulación necesitan lectura estable y URL navegable. |
| Historial de configuraciones | Página dedicada AGENDA-06 | Es una colección creciente, paginada y con acceso a detalle. |
| Registrar bloqueo | Página dedicada AGENDA-07 | La secuencia captura → revisión → confirmación puede mostrar listas de conflictos. |
| Consultar bloqueo | Página dedicada AGENDA-08 | Es el contexto seguro previo a una eventual anulación y admite datos de trazabilidad. |
| Anular bloqueo | Página/formulario AGENDA-09 + SweetAlert2 final | El motivo se captura y valida en el formulario; el diálogo sólo confirma. |
| Anular configuración futura | Página/formulario AGENDA-10 + SweetAlert2 final | Requiere detalle, motivo y explicación del impacto temporal antes de confirmar. |

Los modales quedan disponibles sólo para ayudas breves o información contextual futura que no exija edición, paginación ni scroll extenso. Ninguna operación canónica AGENDA-01 a AGENDA-10 depende de un modal.

## 4. Arquitectura general de navegación

**AGENDA-01** es el dashboard y punto de retorno del UC. Resume la situación actual y ofrece tres acciones primarias: crear configuración, cambiar duración y registrar bloqueo. También presenta configuraciones futuras, acceso al historial y el listado paginado de bloqueos.

Las operaciones se organizan en cuatro ramas:

- **Configuración habitual:** AGENDA-01 → AGENDA-02 o AGENDA-03 → AGENDA-01.
- **Duración:** AGENDA-01 → AGENDA-04 → AGENDA-01.
- **Consulta de configuraciones:** AGENDA-01 → AGENDA-05, o AGENDA-01 → AGENDA-06 → AGENDA-05.
- **Bloqueos:** AGENDA-01 → AGENDA-07 → AGENDA-01; AGENDA-01 → AGENDA-08 → AGENDA-09 → AGENDA-01.

La anulación de una configuración sigue AGENDA-01/AGENDA-05 → AGENDA-10 → AGENDA-01. En todos los retornos desde listados o detalles se conserva, cuando exista, el contexto de origen: filtro de bloqueos, página de bloqueos o página del historial. Este “contexto de retorno” es navegación, no un nuevo atributo persistido.

El dashboard puede mostrar un resumen compacto de futuras y remitir al detalle individual. El historial de configuraciones y el listado de bloqueos se paginan de a 10 inicialmente para evitar crecimiento indefinido.

## 5. Inventario de pantallas y estados

### Inventario de pantallas

| ID | Pantalla | Sección UC | Tipo | Entrada principal | Salida principal |
|---|---|---|---|---|---|
| AGENDA-01 | Configurar agenda de atención | Principal / A–F | Dashboard | Navegación del módulo Turnos | Acción elegida o salida del UC |
| AGENDA-02 | Crear primera / nueva configuración futura | A | Página con formulario | CTA principal o estado vacío | AGENDA-01 tras guardar o cancelar |
| AGENDA-03 | Editar configuración futura | A | Página con formulario | Card futura o AGENDA-05 | Origen conservado tras guardar o cancelar |
| AGENDA-04 | Cambiar duración estándar | B | Página con formulario y matriz | Acción principal del dashboard | AGENDA-01 tras guardar o cancelar |
| AGENDA-05 | Consultar configuración | D / apoyo A y F | Página de sólo lectura | Configuración vigente, futura o historial | Origen; AGENDA-03/10 si es legal |
| AGENDA-06 | Historial de configuraciones | D | Listado paginado | Acceso “Ver historial” | AGENDA-05 o AGENDA-01 |
| AGENDA-07 | Registrar bloqueo | C | Página con captura y revisión | Acción principal “Registrar bloqueo” | AGENDA-01 tras confirmar o cancelar |
| AGENDA-08 | Consultar bloqueo | E / consulta | Página de sólo lectura | Fila/card de bloqueo | Origen o AGENDA-09 |
| AGENDA-09 | Anular bloqueo | E | Página con formulario + confirmación | AGENDA-08 o acción contextual | AGENDA-01/listado conservado |
| AGENDA-10 | Anular configuración futura | F | Página con formulario + confirmación | Card futura o AGENDA-05 | AGENDA-01; registro pasa al historial |

### Acciones de configuración según estado

| Estado derivado | Consultar | Editar | Anular |
|---|---:|---:|---:|
| Vigente (`vigencia_desde <= hoy` y `vigencia_hasta` nula o `>= hoy`, no anulada) | Sí | No | No |
| Futura no iniciada (`vigencia_desde > hoy`, no anulada) | Sí | Sí | Sí |
| Histórica (`vigencia_hasta < hoy`, no anulada) | Sí | No | No |
| Anulada (`anulado_at` informado) | Sí | No | No |

“Próxima” puede acompañar a la futura no anulada con menor `vigencia_desde`, pero no cambia sus acciones. Una configuración que comienza hoy ya es iniciada: sólo ofrece Consultar.

### Estados de bloqueos

| Estado derivado | Criterio conceptual | Consultar | Anular |
|---|---|---:|---:|
| Próximo | No anulado y `inicio_at > ahora` | Sí | Sí |
| En curso | No anulado y `inicio_at <= ahora < fin_at` | Sí | Sí |
| Pasado | No anulado y `fin_at <= ahora` | Sí | Sí |
| Anulado | `anulado_at` informado, con independencia del intervalo original | Sí | No |

Se permite anular un bloqueo pasado no anulado para corregir su trazabilidad; la UI aclara que esa operación no produce por sí sola disponibilidad futura. Nunca se ofrecen Editar ni Eliminar.

## 6. AGENDA-01 — Pantalla principal

- **ID conceptual y nombre visible:** AGENDA-01, “Configurar agenda de atención”.
- **Objetivo:** concentrar el estado actual, próximos cambios, bloqueos y accesos a las secciones A–F.
- **Secciones relacionadas:** entrada común; enlaces a A, B, C, D, E y F.
- **Punto de entrada:** opción “Configurar agenda” dentro del módulo Turnos, visible sólo para la kinesióloga con perfil completo.
- **Información mostrada:** encabezado y explicación breve; advertencia persistente de que los cambios no alteran automáticamente turnos otorgados; card de configuración vigente; matriz semanal vigente; resumen de futuras no anuladas ordenadas por inicio; acceso al historial; listado de bloqueos con filtros, cantidad total y paginación.
- **Acciones disponibles:** “Crear primera configuración” cuando nunca existió ninguna; “Nueva configuración” cuando ya hay antecedentes; “Cambiar duración” sólo cuando existe una configuración no anulada desde la cual conservar una matriz aplicable; “Registrar bloqueo” siempre que se cumpla la precondición del UC; “Consultar” en configuraciones y bloqueos; “Editar” y “Anular” sólo para futuras no iniciadas; “Ver historial completo”; filtros de bloqueos.
- **Condiciones:** si no hay configuración aplicable hoy, no se muestra ninguna otra como vigente. Si una futura empieza hoy, desaparecen sus acciones Editar/Anular al recargar o revalidar la pantalla. La acción “Cambiar duración” explica por qué no está disponible si no existe matriz de origen; la primera agenda se crea desde AGENDA-02.
- **Estado vacío:** el primer uso muestra una card destacada “Todavía no configuraste tu agenda” con el CTA “Crear primera configuración” y los valores sugeridos sólo se cargan al abrir el formulario. Un hueco muestra “Sin configuración vigente” y conserva por separado el historial y las futuras. Las secciones sin futuras, historial o bloqueos usan mensajes específicos, no una card falsa.
- **Validaciones visibles:** no contiene validación de escritura. Un cambio temporal ocurrido desde la carga se refleja al actualizar y ajusta las acciones legales.
- **Advertencias:** banner superior y nota junto a las acciones: horarios, duración y bloqueos no modifican turnos ya otorgados. Un hueco temporal advierte que no se ofrecerá disponibilidad basada en horarios habituales para hoy.
- **Cancelar/Volver:** no aplica dentro del dashboard; abandonar la pantalla finaliza el UC.
- **Destino tras éxito:** todas las escrituras vuelven aquí, actualizan los resúmenes y muestran feedback global.
- **Desktop:** ancho máximo coherente con el layout; encabezado y acciones principales en una fila de hasta tres cards; configuración vigente a ancho completo; matriz en dos columnas; futuras e historial en dos columnas; bloqueos en tabla con acciones textuales.
- **Mobile:** encabezado y acciones apilados; card vigente antes de futuras; matriz como siete bloques verticales; cada futura y bloqueo como card; filtros desplazables sólo dentro de su grupo o, preferentemente, en un `select` etiquetado para evitar scroll horizontal; botones principales a ancho completo.
- **Accesibilidad:** `h1` único, secciones con `h2`, acciones de navegación como enlaces, filtros con label, texto junto a badges, foco visible y anuncios de feedback global. El orden DOM coincide con el orden visual mobile.
- **Datos persistidos:** vigencias, duración y anulación de `configuraciones_agenda`; días y horas de `franjas_horarias_agenda`; intervalo, motivo y anulación de `bloqueos_agenda`.
- **Datos derivados:** existencia de vigente, hueco actual, estados y badges, próxima futura, cantidad de días/franjas, resumen horario, estado temporal de bloqueos, presentación “Todo el día” y conteos de cada sección.

### Composición de la card vigente

Debe mostrar badge “Vigente”, período `desde → hasta` o “Sin fecha de fin”, duración en minutos, cantidad derivada de días con atención, cantidad total de franjas y matriz de lunes a domingo. Los días sin franjas dicen “Sin atención”. Su única acción sobre el registro es “Consultar”; “Nueva configuración” y “Cambiar duración” son acciones del UC, no edición de la vigente.

### Composición de cada card futura

Muestra período, duración, cantidad de días con atención, cantidad total de franjas, resumen compacto por día y badge “Futura”; la más cercana puede añadir “Próxima”. Ofrece “Consultar”, “Editar” y “Anular”. Si dejó de ser futura o fue anulada antes de ejecutar una acción, la UI informa el cambio, no escribe y vuelve a presentar el estado actual.

## 7. AGENDA-02 — Crear primera / nueva configuración futura

- **ID conceptual y nombre visible:** AGENDA-02, “Crear primera configuración” o “Nueva configuración de agenda”, según el origen.
- **Objetivo:** registrar una configuración completa sin sobrescribir períodos anteriores.
- **Sección relacionada:** A.
- **Punto de entrada:** CTA del estado inicial, CTA “Nueva configuración” o acción equivalente desde el hueco temporal.
- **Información mostrada:** explicación del efecto hacia adelante; período de vigencia; duración estándar; matriz editable de lunes a domingo; resumen del período antes de guardar.
- **Campos y acciones:** `vigencia_desde` obligatoria; `vigencia_hasta` opcional; `duracion_turno_minutos` obligatoria; cero o más filas de franja por día durante la edición; “Agregar franja”, “Quitar franja”, “Cancelar” y “Guardar configuración”.
- **Condiciones:** no se admite `vigencia_desde` en una fecha pasada. Para una primera configuración, o cuando hoy existe realmente un hueco sin configuración aplicable, puede utilizarse `vigencia_desde = hoy` siempre que no exista solapamiento con otra configuración no anulada. Cuando se crea una configuración sucesora de una configuración vigente, `vigencia_desde` debe ser posterior a hoy para no cerrar retrospectivamente la configuración que representa el día actual. Una configuración con `vigencia_desde = hoy` se considera inmediatamente iniciada y, después de guardarse, ya no puede editarse ni anularse como futura. `vigencia_hasta` es inclusiva, opcional y puede quedar vacía. Al crear la primera configuración se sugieren 45 minutos y lunes a viernes 08:00–17:00, pero nada es efectivo hasta guardar.
- **Estado vacío:** la matriz puede comenzar con la sugerencia anterior en la primera configuración; para una nueva configuración se propone copiar visualmente la matriz de la configuración inmediatamente anterior sólo como punto de partida editable. Si no existe origen aplicable, inicia sin franjas y explica que debe agregarse al menos una.
- **Validaciones visibles:** inicio obligatorio y no pasado; si es una sucesora de la configuración vigente, inicio posterior a hoy; si es la primera configuración o existe un hueco real hoy, inicio igual a hoy permitido sólo sin solapamiento; fin igual o posterior al inicio; duración entera de 15 a 180 y múltiplo de 5; al menos una franja global; horas completas; fin posterior a inicio; no solapamiento dentro del mismo día; franja de duración igual o mayor a la duración estándar; período sin solapamiento con configuraciones no anuladas. Los errores se muestran junto a fechas, duración o filas concretas y, si son múltiples, también en un resumen superior con enlaces.
- **Advertencias:** crear una sucesora puede ajustar prospectivamente el fin de la inmediatamente anterior y, por ello, cuando existe una configuración vigente su inicio debe ser posterior a hoy; no cambia sus franjas/duración históricas ni los turnos existentes. La vista previa debe describir el período que resultará afectado sin exponer lógica técnica.
- **Cancelar/Volver:** vuelve a AGENDA-01. Si hubo cambios, solicita confirmación para descartarlos; si no, vuelve directamente.
- **Destino tras éxito:** AGENDA-01, con mensaje “Configuración registrada” y su fecha de inicio; se muestra como vigente si inicia hoy o futura si inicia después.
- **Desktop:** formulario en bloques: vigencia y duración en grilla, matriz a ancho completo y barra de acciones al final; sin scroll interno independiente.
- **Mobile:** una columna; fechas y duración apiladas; cada día es un `fieldset` vertical y cada franja muestra inicio, fin y botón “Quitar”; acciones grandes, sin tabla horizontal.
- **Accesibilidad:** labels explícitos; matriz agrupada por `fieldset`/`legend`; botones con texto o nombre accesible que incluya día/franja; errores mediante `aria-describedby` y `aria-invalid`; foco en resumen/primer error tras fallo.
- **Datos persistidos:** `vigencia_desde`, `vigencia_hasta`, `duracion_turno_minutos`; por franja, `dia_semana`, `hora_inicio`, `hora_fin`. La kinesióloga se obtiene del contexto autenticado.
- **Datos derivados:** condición primera/nueva, advertencia “inicia hoy”, duración de cada franja, solapamientos calculados, cantidades y resumen previo.

La confirmación final usa SweetAlert2 sólo después de que el formulario sea válido. Resume vigencia, duración y cantidad de franjas y permite “Confirmar guardado” o “Volver a revisar”.

## 8. AGENDA-03 — Editar configuración futura

- **ID conceptual y nombre visible:** AGENDA-03, “Editar configuración futura”.
- **Objetivo:** corregir una configuración no anulada cuya vigencia aún no comenzó.
- **Sección relacionada:** A.
- **Punto de entrada:** acción “Editar” de una card futura o AGENDA-05.
- **Información mostrada:** badge “Futura”, período actual, duración y matriz completa precargada; nota de que los cambios siguen sujetos a no solapamiento.
- **Campos y acciones:** permite editar `vigencia_desde`, `vigencia_hasta`, `duracion_turno_minutos` y toda la matriz semanal; ofrece “Agregar franja”, “Quitar franja”, “Cancelar” y “Guardar cambios”. Esto refleja la regla del Diccionario que permite revalidar período, duración y franjas de una futura no iniciada.
- **Condiciones:** sólo aparece si no está anulada y `vigencia_desde > hoy`. La condición se revalida al guardar. Si comenzó hoy, fue anulada o cambió concurrentemente, no se aplica ningún cambio y se ofrece consultar el estado actual.
- **Estado vacío:** no corresponde para el registro, pero un acceso obsoleto muestra mensaje de indisponibilidad y enlace al dashboard; no muestra un formulario editable.
- **Validaciones visibles:** las mismas de AGENDA-02, excluyendo el propio período al evaluar solapamientos. Si cambia la duración, todas las franjas se revalidan y las incompatibles se identifican por día y horario.
- **Advertencias:** modificar la fecha puede alterar el encadenamiento prospectivo con configuraciones vecinas; no cambia períodos históricos ni turnos otorgados.
- **Cancelar/Volver:** vuelve exactamente al origen (dashboard o detalle); con cambios sin guardar, confirma descarte.
- **Destino tras éxito:** origen conservado, preferentemente AGENDA-01, con feedback “Configuración futura actualizada”.
- **Desktop:** mismo patrón de formulario dedicado y matriz a ancho completo de AGENDA-02.
- **Mobile:** edición por día en bloques verticales, sin modal ni scroll horizontal.
- **Accesibilidad:** idéntica semántica de formulario de AGENDA-02; el título y el texto dejan claro que se edita un registro futuro.
- **Datos persistidos:** campos de configuración y franjas existentes identificados por el registro cargado; no se agrega estado.
- **Datos derivados:** elegibilidad para edición, duración de franjas, solapamientos y resumen de cambios.

SweetAlert2 confirma el guardado con el nuevo período. No ofrece “Editar” ni por interfaz ni por acceso directo cuando la configuración ya comenzó.

## 9. AGENDA-04 — Cambiar duración estándar

- **ID conceptual y nombre visible:** AGENDA-04, “Cambiar duración estándar”.
- **Objetivo:** crear una nueva configuración desde una fecha de efecto, conservando inicialmente una matriz existente y permitiendo corregirla si resulta incompatible.
- **Sección relacionada:** B.
- **Punto de entrada:** acción “Cambiar duración” de AGENDA-01.
- **Información mostrada:** duración de origen; nueva duración; fecha desde la que rige; configuración de la que se copia la matriz; matriz completa que se pretende conservar; comparación y explicación de que el resultado será una nueva vigencia.
- **Campos y acciones:** `duracion_turno_minutos`, `vigencia_desde` y matriz semanal editable. Ofrece “Corregir” directamente en cada franja, “Agregar/Quitar franja”, “Cancelar” y “Guardar nueva duración”. No es un editor de la configuración iniciada.
- **Condiciones:** `vigencia_desde > hoy`. La nueva duración debe comenzar en una fecha posterior a hoy porque crea una configuración sucesora a partir de una configuración de origen. Una configuración que comienza hoy ya se considera iniciada y, como `vigencia_desde` tiene granularidad `DATE`, no se modela un cambio de duración intradía. Permitir el inicio hoy obligaría a alterar retrospectivamente qué configuración representa el día actual. Debe existir una matriz de origen coherente para esa transición y la nueva fecha no puede superponerse con otra configuración no anulada; ante un conflicto, la interfaz orienta a editar la futura correspondiente o elegir otra fecha, sin sustituir silenciosamente registros.
- **Estado vacío:** si no existe configuración de origen, se explica que primero debe crearse una configuración completa en AGENDA-02 y no se presenta un formulario incompleto.
- **Validaciones visibles:** nueva duración entera entre 15 y 180, múltiplo de 5; fecha posterior a hoy y sin superposición con otra configuración no anulada; matriz con al menos una franja; reglas horarias completas. Cada franja menor a la nueva duración se resalta con día, intervalo, duración real y mensaje “Esta franja dura X min y debe durar al menos Y min”. El botón final permanece no operativo hasta corregir todas las incompatibilidades, sin ocultarlas detrás de un error genérico.
- **Advertencias:** se crea una nueva vigencia, se preserva el histórico y no se modifican turnos. La matriz mostrada es una copia editable para el nuevo período, no una modificación retrospectiva.
- **Cancelar/Volver:** AGENDA-01; confirma descarte si hubo cambios.
- **Destino tras éxito:** AGENDA-01 con mensaje que indica duración y fecha de inicio de la nueva configuración.
- **Desktop:** resumen comparativo arriba; duración y fecha en dos columnas; matriz debajo con panel lateral o franja superior de incompatibilidades.
- **Mobile:** resumen, campos, lista de incompatibilidades y días en ese orden; el error aparece también junto a cada franja; sin modal.
- **Accesibilidad:** incompatibilidades identificadas por texto e icono, no sólo color; resumen enlazado a cada fila; cambios dinámicos anunciados en una región `aria-live` no intrusiva.
- **Datos persistidos:** nueva configuración con vigencia, nueva duración y matriz resultante; posible ajuste prospectivo de `vigencia_hasta` anterior conforme al UC.
- **Datos derivados:** configuración/matriz de origen, duración calculada por franja, lista de incompatibilidades y resumen antes/después.

SweetAlert2 confirma la creación de la nueva vigencia sólo después de resolver las incompatibilidades.

## 10. AGENDA-05 — Consultar configuración

- **ID conceptual y nombre visible:** AGENDA-05, “Consultar configuración de agenda”.
- **Objetivo:** mostrar una configuración vigente, futura, histórica o anulada en sólo lectura.
- **Secciones relacionadas:** D; apoyo contextual de A y F.
- **Punto de entrada:** card vigente/futura de AGENDA-01, fila de AGENDA-06 o retorno posterior a una acción.
- **Información mostrada:** badge derivado; vigencia; duración; matriz completa de lunes a domingo; cantidades de días y franjas; fecha y motivo de anulación cuando corresponda; leyenda “Sin atención” en días sin franjas.
- **Acciones disponibles:** “Volver”; “Editar” y “Anular” sólo si sigue siendo futura no iniciada y no anulada. Una vigente puede ofrecer enlaces separados “Nueva configuración” y “Cambiar duración”, nunca “Editar”.
- **Condiciones:** las acciones se deciden con el estado actual, no con el estado que tenía al abrir el enlace.
- **Estado vacío:** si el registro no está disponible para consulta, se informa sin exponer datos ajenos y se vuelve al origen.
- **Validaciones visibles:** no hay campos editables. Un cambio concurrente muestra información actualizada y ajusta acciones.
- **Advertencias:** en anuladas, mensaje de trazabilidad: no participa en disponibilidad ni solapamientos efectivos. En históricas, “Sólo lectura”.
- **Cancelar/Volver:** retorna al dashboard o a la página del historial y conserva su paginación.
- **Destino tras éxito:** no aplica; desde Editar/Anular se navega a AGENDA-03/10.
- **Desktop:** resumen en grilla y matriz en dos columnas; acciones al final y opcionalmente en encabezado.
- **Mobile:** resumen y siete días apilados; acciones con texto completo.
- **Accesibilidad:** estructura `dl` para metadatos, encabezados por día y badges con texto; ningún dato depende sólo de color.
- **Datos persistidos:** vigencias, duración, anulación y franjas.
- **Datos derivados:** estado, próxima, cantidades y resumen por día.

## 11. AGENDA-06 — Historial de configuraciones

- **ID conceptual y nombre visible:** AGENDA-06, “Historial de configuraciones”.
- **Objetivo:** consultar una colección creciente de configuraciones finalizadas y futuras anuladas.
- **Sección relacionada:** D.
- **Punto de entrada:** “Ver historial completo” en AGENDA-01.
- **Información mostrada:** total de resultados; filas/cards ordenadas por criterio cronológico explícito —más recientes primero—; vigencia, duración, resumen de días/franjas, badge “Histórica” o “Anulada”; fecha de anulación en el segundo caso.
- **Acciones disponibles:** únicamente “Consultar” y “Volver”. Paginación inicial de 10 elementos.
- **Condiciones:** una futura anulada aparece aquí y no en el resumen de futuras no anuladas. Una configuración vigente nunca se incluye para rellenar un historial vacío.
- **Estado vacío:** “No existen configuraciones históricas ni futuras anuladas para consultar”, con enlace a AGENDA-01.
- **Validaciones visibles:** no aplica a escritura. Una página fuera de rango vuelve a la última página válida o muestra el estado vacío de forma comprensible.
- **Advertencias:** listado de sólo lectura; los registros se conservan para trazabilidad.
- **Cancelar/Volver:** vuelve a AGENDA-01; desde un detalle retorna a la misma página del historial.
- **Destino tras éxito:** no aplica.
- **Desktop:** tabla sin scroll horizontal innecesario, con columnas Período, Duración, Resumen, Estado y Acciones.
- **Mobile:** cards con período como título, badge textual, duración, conteos y botón Consultar.
- **Accesibilidad:** caption o encabezado descriptivo, encabezados de columna semánticos, navegación de paginación etiquetada y estado actual de página anunciado.
- **Datos persistidos:** configuraciones y franjas; `anulado_at`, `motivo_anulacion` para el detalle.
- **Datos derivados:** Histórica/Anulada, conteos y resumen de matriz.

## 12. AGENDA-07 — Registrar bloqueo

- **ID conceptual y nombre visible:** AGENDA-07, “Registrar bloqueo excepcional”.
- **Objetivo:** capturar un intervalo continuo, revisar impactos reales y confirmarlo sin inventar atributos persistentes.
- **Sección relacionada:** C.
- **Punto de entrada:** acción principal “Registrar bloqueo” de AGENDA-01.
- **Información mostrada:** selector de modo temporal; fechas/horas; motivo libre; presets opcionales; explicación del intervalo resultante; en revisión, turnos afectados y bloqueos superpuestos.
- **Campos y acciones:** modo visual “Todo el día / rango de días completos” o “Intervalo parcial”; fecha inicial y final; horas inicial/final sólo para parcial; `motivo` obligatorio y libre. Presets “Feriado”, “Vacaciones”, “Licencia” y “Congreso” rellenan `motivo` pero pueden editarse. Acciones “Revisar bloqueo”, “Volver a editar”, “Confirmar registro” y “Cancelar”.
- **Condiciones:** todo el día de una fecha se representa conceptualmente `[00:00 del día, 00:00 del siguiente)`; un rango completo usa fin exclusivo a las 00:00 del día posterior a la última fecha incluida. El intervalo parcial multidiario es continuo, no una repetición diaria. La UI muestra un resumen humano, por ejemplo “Desde 10/10/2026 09:00 hasta 12/10/2026 12:00, de forma continua”.
- **Estado vacío:** no aplica al formulario. En revisión, “No hay turnos afectados” y “No se superpone con otros bloqueos” son resultados positivos independientes.
- **Validaciones visibles:** motivo obligatorio; fechas/horas obligatorias según modo; `fin_at > inicio_at`; errores junto al campo y resumen superior. La validación crítica se realiza antes de la revisión y se repite al confirmar.
- **Advertencias:** si hay turnos, card de advertencia con cantidad y lista de fecha/hora de cada turno, paginada o expandible si fuese extensa; explica que deberán gestionarse en otro UC. Si hay solapamientos, muestra cantidad y cada intervalo/motivo relevante; explica que se conservarán como registros independientes. Ambas advertencias pueden coexistir y no deshabilitan la confirmación.
- **Cancelar/Volver:** vuelve a AGENDA-01; si hay captura, confirma descarte. Desde revisión, “Volver a editar” conserva todo.
- **Destino tras éxito:** AGENDA-01, conservando el filtro de bloqueos más útil para localizar el nuevo registro y mostrando “Bloqueo registrado”. Si hubo advertencias, el feedback las recuerda sin iniciar reprogramaciones.
- **Desktop:** captura en card de ancho medio/amplio; fechas y horas en grilla; revisión en paneles con tablas compactas de turnos y bloqueos.
- **Mobile:** campos apilados; presets como botones con texto; revisión en cards; fechas y horas sin scroll horizontal; confirmación al final del contenido.
- **Accesibilidad:** modos agrupados con `fieldset`/`legend`; el cambio de modo anuncia qué campos se muestran; presets son botones, no select obligatorio; listas de advertencia con encabezados; foco en el primer error o en el título de revisión.
- **Datos persistidos:** `inicio_at`, `fin_at`, `motivo` y kinesióloga contextual. No se persisten modo, preset, `tipo` ni `todo_el_dia`.
- **Datos derivados:** presentación “Todo el día”, normalización del rango, cantidad/lista de turnos afectados, cantidad/lista de bloqueos superpuestos y resumen del intervalo.

### Secuencia UX de registro

1. **Captura:** la kinesióloga elige modo, intervalo y motivo.
2. **Validación:** al pulsar “Revisar bloqueo” se validan campos e intervalo; los errores mantienen los datos.
3. **Advertencias:** la página pasa a revisión y presenta los turnos afectados y solapamientos reales obtenidos para ese intervalo.
4. **Confirmación:** “Confirmar registro” abre SweetAlert2 con el resumen y una frase explícita si hay advertencias. Cancelar el diálogo vuelve a revisión.
5. **Resultado:** tras confirmar y completar el registro, vuelve a AGENDA-01. Si el estado cambió antes de confirmar, se actualizan advertencias y se exige una nueva confirmación.

## 13. AGENDA-08 — Consultar bloqueo

- **ID conceptual y nombre visible:** AGENDA-08, “Consultar bloqueo”.
- **Objetivo:** mostrar el registro original, su estado temporal y su trazabilidad.
- **Sección relacionada:** apoyo de E.
- **Punto de entrada:** “Consultar” desde una fila o card de bloqueos.
- **Información mostrada:** intervalo exacto, representación humana, motivo, estado temporal derivado, fecha de registro; si está anulado, fecha y motivo de anulación; otros bloqueos no anulados que se superponen, como información derivada actual.
- **Acciones disponibles:** “Volver” y “Anular bloqueo” sólo si `anulado_at` es nulo. Nunca Editar ni Eliminar.
- **Condiciones:** un bloqueo pasado sigue siendo consultable y anulable mientras no esté anulado. Si ya fue anulado, el CTA desaparece.
- **Estado vacío:** acceso inválido o no disponible informa la situación y retorna al listado sin mostrar acciones.
- **Validaciones visibles:** no aplica a campos. El estado se recalcula al cargar.
- **Advertencias:** la presencia de otros bloqueos se explica sin afirmar disponibilidad. Un bloqueo anulado no restringe por sí mismo, pero el intervalo puede seguir afectado por otras causas.
- **Cancelar/Volver:** retorna con el mismo filtro y página del listado.
- **Destino tras éxito:** no aplica; AGENDA-09 realiza la escritura.
- **Desktop:** `dl` en dos columnas y panel separado para trazabilidad/solapamientos.
- **Mobile:** bloques apilados, fechas legibles y acción al final.
- **Accesibilidad:** badge textual, fechas expresadas sin depender de abreviaturas ambiguas y encabezados para información original y anulación.
- **Datos persistidos:** intervalo, motivo y campos de anulación.
- **Datos derivados:** estado, “Todo el día”, duración del intervalo y solapamientos actuales.

## 14. AGENDA-09 — Anular bloqueo

- **ID conceptual y nombre visible:** AGENDA-09, “Anular bloqueo”.
- **Objetivo:** dejar sin efecto un bloqueo sin eliminarlo ni alterar sus datos originales.
- **Sección relacionada:** E.
- **Punto de entrada:** AGENDA-08 o acción “Anular” del listado, que siempre desemboca en esta página.
- **Información mostrada:** intervalo, motivo original, estado temporal, resumen de otros bloqueos superpuestos y explicación de que la disponibilidad será recalculada.
- **Campos y acciones:** textarea `motivo_anulacion` obligatorio; “Cancelar” y “Continuar con la anulación”. Al continuar con datos válidos, SweetAlert2 ofrece “Confirmar anulación” o “Volver”.
- **Condiciones:** sólo si no estaba anulado; la elegibilidad se vuelve a comprobar al confirmar. No se modifica `inicio_at`, `fin_at` ni `motivo`.
- **Estado vacío:** si ya fue anulado, muestra la fecha/motivo existente, no permite repetir la acción y ofrece volver al detalle/listado.
- **Validaciones visibles:** motivo no vacío y mensaje asociado al textarea; se preserva el valor ante un fallo.
- **Advertencias:** si existen otros bloqueos, indica que partes del intervalo continuarán bloqueadas. Incluso si no existen, el texto final dice que la disponibilidad fue recalculada y puede depender de turnos, configuración aplicable u otras restricciones; nunca promete “intervalo disponible”.
- **Cancelar/Volver:** retorna a AGENDA-08 o al listado conservando filtro/página, sin cambios.
- **Destino tras éxito:** listado de bloqueos dentro de AGENDA-01 con contexto conservado y mensaje “Bloqueo anulado”; el registro pasa a filtro Anulados, pero no se fuerza el cambio de filtro si rompería el contexto.
- **Desktop:** resumen y formulario corto en card central, sin modal.
- **Mobile:** card a ancho completo, textarea y botones apilados o de ancho completo.
- **Accesibilidad:** textarea con label, ayuda y error vinculados; confirmación con título específico e intervalo; foco vuelve al disparador si se cancela SweetAlert2.
- **Datos persistidos:** `anulado_at` y `motivo_anulacion`; datos originales en sólo lectura.
- **Datos derivados:** elegibilidad, solapamientos restantes y mensaje contextual del recálculo.

## 15. AGENDA-10 — Anular configuración futura

- **ID conceptual y nombre visible:** AGENDA-10, “Anular configuración futura”.
- **Objetivo:** dejar sin efecto una configuración todavía no iniciada, preservar su matriz y comunicar el impacto prospectivo.
- **Sección relacionada:** F.
- **Punto de entrada:** acción “Anular” de la card futura o AGENDA-05.
- **Información mostrada:** período, duración, matriz semanal completa, badge “Futura”, explicación del efecto sobre disponibilidad y vista previa comprensible de continuidad: qué configuración anterior podría extenderse, hasta qué fecha o sin fecha de fin, y cuál futura posterior conservaría su inicio.
- **Campos y acciones:** textarea `motivo_anulacion` obligatorio; “Cancelar” y “Continuar con la anulación”; SweetAlert2 final con “Confirmar anulación” y “Volver”.
- **Condiciones:** `anulado_at` nulo y `vigencia_desde > hoy`. Se revalida al confirmar; si la fecha llegó a hoy, la operación se impide y se vuelve al detalle actual.
- **Estado vacío:** si ya comenzó o ya fue anulada, muestra el motivo de indisponibilidad, sin formulario, y ofrece consultar el registro o volver.
- **Validaciones visibles:** motivo obligatorio; el valor se conserva ante error. Los cambios concurrentes se explican sin aplicar una segunda anulación.
- **Advertencias:** la configuración se conservará en historial; dejará de participar en disponibilidad y solapamientos efectivos; la continuidad puede reajustarse sólo hacia adelante; los turnos existentes no se alteran.
- **Cancelar/Volver:** retorna al detalle o dashboard con su contexto, sin cambios.
- **Destino tras éxito:** AGENDA-01 con mensaje “Configuración futura anulada y continuidad recalculada”; desaparece de futuras y queda consultable en AGENDA-06.
- **Desktop:** detalle y matriz en bloque de sólo lectura; impacto y motivo en un segundo bloque; acciones al final.
- **Mobile:** resumen, matriz por día, impacto, textarea y acciones en orden vertical; no se usa modal alto.
- **Accesibilidad:** explicación de impacto como texto y no sólo diagrama; textarea correctamente etiquetado; confirmación nombra el período exacto; foco en mensaje de resultado o primer error.
- **Datos persistidos:** configuración y franjas originales; `anulado_at`, `motivo_anulacion`; posible ajuste prospectivo de `vigencia_hasta` de otra configuración según el UC.
- **Datos derivados:** elegibilidad, estado, resumen de matriz y vista previa de continuidad.

## 16. Matriz semanal editable

La matriz es un control de formulario local a Agenda, no una grilla de slots persistidos. Representa el conjunto de `franjas_horarias_agenda` que pertenecerá a la configuración.

### Estructura y comportamiento

- Presenta siempre lunes a domingo en orden natural.
- Cada día puede tener cero o más franjas durante la edición.
- Un día sin filas se muestra como “Sin atención”. La configuración completa debe conservar al menos una franja global.
- Cada fila contiene “Hora de inicio”, “Hora de fin” y “Quitar franja”.
- “Agregar franja” crea una fila no persistida para ese día. No existe límite artificial de dos franjas.
- “Quitar franja” elimina sólo esa fila del formulario. En edición de una configuración futura, el conjunto final enviado expresa la matriz completa que reemplazará a la futura; no es eliminación física de un histórico iniciado.
- Las franjas se ordenan visualmente por `hora_inicio`. Si la usuaria carga fuera de orden, pueden reordenarse al salir del campo o al validar, sin cambiar sus valores.
- Un toggle visual “Con atención / Sin atención” es opcional. Si se usa, sólo agrega una primera fila o vacía las franjas del día en el estado del formulario. No representa ni envía un atributo persistido “día habilitado”. Al apagarlo con filas cargadas se confirma el descarte de esas filas.
- Las franjas sugeridas de la primera configuración son editables y no se consideran guardadas hasta la confirmación.

### Reglas visibles por fila y por día

1. Inicio y fin son obligatorios para cada fila existente.
2. `hora_fin` debe ser posterior a `hora_inicio`.
3. La duración calculada de la fila debe ser al menos `duracion_turno_minutos`.
4. En un mismo día, la siguiente franja puede comenzar exactamente cuando termina la anterior.
5. En un mismo día, la siguiente franja no puede comenzar antes de que termine la anterior.
6. Las comparaciones y mensajes se actualizan cuando cambia la duración estándar.

Ejemplos de feedback:

- `08:00–12:00` y `12:00–16:00`: válidas y contiguas.
- `08:00–13:00` y `12:00–16:00`: “Estas franjas se superponen entre las 12:00 y las 13:00”.
- `14:00–14:00`: “La hora de fin debe ser posterior a la hora de inicio”.
- `14:00–14:30` con turnos de 45 minutos: “La franja dura 30 min y debe durar al menos 45 min”.

### Presentación responsive

En desktop cada día ocupa una fila o card horizontal con las franjas en una zona flexible; no se representa la semana como calendario de siete columnas estrechas. En mobile cada día es un bloque vertical y cada franja usa dos campos apilados o una grilla de dos columnas que quepa sin desplazamiento horizontal. Los botones “Agregar franja” y “Quitar franja” incluyen texto visible en mobile; un icono puede acompañar, no reemplazar, el nombre accesible.

### Errores y foco

El error se asocia simultáneamente a la fila concreta y al resumen general cuando impide guardar. Los mensajes nombran día e intervalo. Tras un rechazo del servidor se reconstruye toda la matriz con los valores ingresados y se enfoca el resumen de errores; no se pierden las demás franjas.

## 17. Estados vacíos y casos límite

| Caso | Representación y conducta UX |
|---:|---|
| 1. Nunca existió configuración | AGENDA-01 muestra “Todavía no configuraste tu agenda” y CTA “Crear primera configuración”. No muestra vigente, futura ni histórica ficticia. |
| 2. Hay historial pero hoy existe un hueco | Card “Sin configuración vigente”, explicación del hueco, futuras separadas y acceso al historial. Nunca usa la última histórica como fallback. |
| 3. Existe vigente sin futuras | Card vigente y matriz; bloque de futuras muestra “No hay configuraciones futuras programadas” y CTA Nueva configuración. |
| 4. Existe vigente y una o más futuras | Vigente separada; futuras ordenadas ascendentemente por inicio, con la primera marcada además como “Próxima”. |
| 5. Existen futuras pero ninguna vigente | Card “Sin configuración vigente”; futuras visibles con su fecha de inicio. No se llama vigente a la más próxima. |
| 6. No existe historial | AGENDA-06 muestra estado vacío y acceso a AGENDA-01; la vigente continúa accesible desde el dashboard. |
| 7. No existen bloqueos | Listado muestra estado vacío y CTA “Registrar bloqueo”. |
| 8. Un filtro de bloqueos no obtiene resultados | Mensaje “No hay bloqueos [próximos/en curso/pasados/anulados]” y acción “Ver todos” o limpiar filtro; no muestra CTA de primera carga si sí existen otros resultados. |
| 9. No existen configuraciones futuras | Mensaje específico y CTA “Nueva configuración”, sin contador engañoso. |
| 10. Existe una futura anulada | Desaparece de futuras no anuladas y aparece sólo en AGENDA-06 con badge Anulada, fecha de anulación y acceso a detalle. |
| 11. Cambio de duración con todas las franjas compatibles | AGENDA-04 muestra confirmación visual “Todas las franjas pueden conservarse” y permite continuar. |
| 12. Cambio de duración con franjas incompatibles | Resumen con cantidad y lista; cada franja incompatible está resaltada y es editable. No se permite confirmar hasta corregirla o quitarla. |
| 13. Bloqueo sin turnos afectados | Revisión muestra “No se encontraron turnos otorgados dentro del intervalo” y permite confirmar. |
| 14. Bloqueo con turnos afectados | Advertencia con cantidad, fecha y horario de cada turno; permite confirmar y aclara que no los modifica. |
| 15. Bloqueo sin solapamiento con otros | Revisión lo comunica de forma separada a los turnos y permite confirmar. |
| 16. Bloqueo con doble o múltiple solapamiento | Informa cantidad y lista de bloqueos; explica independencia de registros y permite confirmar. |
| 17. Anulación que deja el intervalo libre según bloqueos | Mensaje prudente: “No quedan otros bloqueos superpuestos; la disponibilidad fue recalculada y aún depende de la agenda, turnos y otras restricciones”. |
| 18. Anulación donde otro bloqueo sigue afectando | Informa cuántos bloqueos no anulados permanecen y qué partes del intervalo siguen afectadas; no promete disponibilidad. |
| 19. Configuración futura anulable | Card y detalle ofrecen Consultar, Editar y Anular; AGENDA-10 presenta impacto temporal y motivo obligatorio. |
| 20. Configuración que comienza hoy | Se deriva como Vigente/iniciada; ya no muestra Editar ni Anular. Un acceso abierto previamente se revalida y no ejecuta la acción. |

También se contemplan estos estados transitorios:

- **Error al guardar:** la misma página conserva los datos, muestra mensaje global no destructivo y permite reintentar.
- **Cambio concurrente:** se explica qué condición cambió y se presenta el estado actual; nunca se fuerza una escritura con información obsoleta.
- **Página paginada sin elementos tras una anulación:** se vuelve a una página válida manteniendo el filtro.
- **Configuración sin fin:** se presenta “Sin fecha de fin”, no una fecha artificial.
- **Rango de día completo:** la UI muestra fechas inclusivas para captura y un resumen entendible; no muestra `23:59:59`.

## 18. Estados visuales derivados

### Configuraciones

El orden de precedencia visual es:

1. **Anulada:** `anulado_at` informado.
2. **Futura:** no anulada y `vigencia_desde > hoy`.
3. **Vigente:** no anulada, `vigencia_desde <= hoy` y `vigencia_hasta` nula o `vigencia_hasta >= hoy`.
4. **Histórica:** no anulada y `vigencia_hasta < hoy`.

“Próxima” es una marca complementaria sobre la futura no anulada con inicio más cercano. No reemplaza “Futura” ni altera permisos. Si no se cumple ninguna condición de vigente, AGENDA-01 muestra el estado de página “Sin configuración vigente”.

### Bloqueos

El orden de precedencia es:

1. **Anulado:** `anulado_at` informado.
2. **Próximo:** no anulado e inicio posterior a ahora.
3. **En curso:** no anulado e intervalo semiabierto que contiene ahora.
4. **Pasado:** no anulado y fin igual o anterior a ahora.

Los filtros del listado son **Próximos**, **En curso**, **Pasados**, **Anulados** y **Todos**. No existe “Activos”, porque mezclaría estados temporales diferentes. El filtro inicial es “Próximos”; AGENDA-01 mantiene visible junto al selector un contador/enlace de “En curso” cuando exista alguno para que no quede oculto.

### Presentación de badges

Todos los badges incluyen texto, contraste suficiente y, opcionalmente, un icono redundante. El color no es la única señal. “Anulada” prevalece sobre el estado temporal original, que puede describirse en el detalle como “El intervalo original ya transcurrió” sin crear un segundo estado persistido.

## 19. Persistido vs derivado

| Elemento UI | Origen | Persistido/Derivado | Observación |
|---|---|---|---|
| Inicio de vigencia | `configuraciones_agenda.vigencia_desde` | Persistido | `DATE`; una fecha igual a hoy ya inició. |
| Fin de vigencia | `configuraciones_agenda.vigencia_hasta` | Persistido | Opcional e inclusivo; NULL se presenta “Sin fecha de fin”. |
| Duración estándar | `configuraciones_agenda.duracion_turno_minutos` | Persistido | Entero 15–180, múltiplo de 5. |
| Fecha/hora de anulación de configuración | `configuraciones_agenda.anulado_at` | Persistido | Sólo para futura aún no iniciada. |
| Motivo de anulación de configuración | `configuraciones_agenda.motivo_anulacion` | Persistido | Obligatorio al anular. |
| Día de una franja | `franjas_horarias_agenda.dia_semana` | Persistido | La existencia de franjas expresa atención. |
| Inicio/fin de franja | `hora_inicio`, `hora_fin` | Persistido | No se materializan slots. |
| Inicio/fin de bloqueo | `bloqueos_agenda.inicio_at`, `fin_at` | Persistido | Intervalo conceptual `[inicio_at, fin_at)`. |
| Motivo del bloqueo | `bloqueos_agenda.motivo` | Persistido | Texto libre; los presets sólo lo completan. |
| Fecha/hora de anulación de bloqueo | `bloqueos_agenda.anulado_at` | Persistido | El registro original se conserva. |
| Motivo de anulación de bloqueo | `bloqueos_agenda.motivo_anulacion` | Persistido | Obligatorio al anular. |
| Fecha/hora de turnos afectados | `turnos.inicio_at`, `turnos.fin_at` | Persistido existente | Sólo se consulta para advertir; no se modifica. |
| Vigente/Futura/Histórica/Anulada | Fechas de configuración y `anulado_at` | Derivado | No existe columna `estado` de configuración. |
| Próxima | Futuras ordenadas por `vigencia_desde` | Derivado | Marca complementaria de presentación. |
| Sin configuración vigente | Ausencia de configuración aplicable a hoy | Derivado | No es un registro ni un estado almacenado. |
| Próximo/En curso/Pasado/Anulado | Intervalo de bloqueo, ahora y `anulado_at` | Derivado | Filtros y badges temporales. |
| Todo el día / rango completo | Límites normalizados del bloqueo + modo de captura | Derivado/presentación | El modo no se persiste. |
| Preset Feriado/Vacaciones/Licencia/Congreso | Interacción que rellena `motivo` | Presentación | No crea `tipo` ni catálogo. |
| Día con atención | Existencia de al menos una franja para el día | Derivado | Un toggle visual sólo manipula filas. |
| Cantidad de franjas | Conteo de franjas de la configuración | Derivado | Resumen de card. |
| Cantidad de días de atención | Conteo de días con al menos una franja | Derivado | Resumen de card. |
| Duración de una franja | Diferencia entre `hora_fin` y `hora_inicio` | Derivado | Se usa para validar compatibilidad. |
| Turnos afectados | Solapamiento entre intervalo propuesto y turnos existentes | Derivado | Incluye cantidad y fecha/hora. |
| Bloqueos superpuestos | Solapamiento con bloqueos no anulados | Derivado | Puede haber dos o más; no se fusionan. |
| Vista previa de continuidad | Configuraciones no anuladas vecinas | Derivado | Explica el posible reajuste prospectivo. |
| Contexto de retorno | URL/origen de navegación | Presentación | Conserva filtro/página; no es dominio. |

No se diseñan columnas `estado`, `activo`, `tipo`, `todo_el_dia`, `dia_habilitado`, cupos, slots ni disponibilidad materializada. Tampoco una FK entre `turnos` y `configuraciones_agenda`.

## 20. Desktop vs mobile

| Aspecto | Desktop | Mobile |
|---|---|---|
| Encabezado | Título, explicación y CTA alineables en filas. | Título, explicación, advertencia y CTA apilados. |
| Dashboard | Acciones en hasta tres columnas; cards de resumen en dos columnas cuando corresponda. | Orden único: advertencia, acciones, vigente/hueco, futuras, historial, bloqueos. |
| Matriz de sólo lectura | Dos columnas de días con chips de intervalos. | Siete cards verticales; intervalos envuelven línea. |
| Matriz editable | Una fila/card por día con varias franjas flexibles. | `fieldset` por día y controles apilados; sin tabla. |
| Listados | Tabla semántica para historial y bloqueos, sin ocultar acciones legales. | Cards con etiquetas campo–valor y acciones textuales. |
| Filtros | Grupo horizontal visible sobre tabla. | `select` etiquetado o botones que envuelven; nunca una barra que obligue scroll de toda la página. |
| Formularios | Grillas de dos columnas para fechas/horas y secciones a ancho controlado. | Una columna; botones de acción amplios; teclado adecuado por tipo de input. |
| Detalles | `dl` en dos columnas y matriz separada. | `dl` y matriz apilados. |
| Advertencias extensas | Tablas compactas para turnos y bloqueos afectados. | Cards cronológicas; resumen primero y detalle expandible accesible si la lista es larga. |
| Acciones | Grupo al final y, si ayuda, acciones primarias repetidas en encabezado. | Una sola barra final en flujo; evitar barras sticky que tapen errores o teclado. |
| Modales | Sólo contenido breve y acotado, si aparece en un refinamiento futuro. | Se evitan modales altos; las operaciones definidas usan páginas completas. |

La densidad se reduce en mobile, pero no se ocultan vigencia, duración, motivo, estado, advertencias ni acciones legales. La tabla desktop y las cards mobile representan los mismos registros y filtros.

## 21. Accesibilidad

- Cada página tiene un `h1`; las secciones respetan jerarquía de encabezados.
- Todos los campos tienen `label` explícito. Los grupos de modo temporal y cada día de la matriz usan `fieldset` y `legend`.
- Los requeridos se indican con texto además del asterisco; la instrucción general explica su significado.
- Los errores se vinculan con `aria-describedby` y los campos inválidos usan `aria-invalid="true"`. En formularios largos existe resumen con enlaces al campo.
- Tras un error, el foco va al resumen o primer campo inválido; tras navegar a revisión, va al título de revisión; tras feedback, al encabezado o mensaje correspondiente.
- Enlaces realizan navegación; botones ejecutan cambios locales, envío o confirmación. No se usan `div` clicables.
- Todas las acciones sólo con icono tienen nombre accesible específico, por ejemplo “Quitar franja de Martes 2”. Cuando hay espacio, se prefiere texto visible.
- El foco visible del stack actual se conserva y mejora donde sea necesario; no se elimina el outline.
- La matriz, filtros, diálogos y paginación son operables por teclado.
- Los badges y advertencias combinan texto, icono y color; ningún estado depende sólo de color.
- SweetAlert2 debe tener título específico, botones con verbos claros, foco inicial no destructivo y retorno de foco al disparador al cancelar.
- Si se usa el componente modal existente para una ayuda breve, conserva trampa de foco, cierre con Escape, semántica de diálogo, título asociado y retorno de foco. No se utiliza para los formularios AGENDA-02/03/04/07/09/10.
- Los cambios dinámicos de compatibilidad de franjas y resultados de revisión se anuncian con regiones `aria-live` apropiadas sin repetir cada pulsación.
- Las áreas táctiles de botones, toggles y acciones de fila son razonables (objetivo aproximado mínimo de 44 × 44 CSS px cuando el contexto lo permita).
- Fechas y rangos no se comunican sólo con flechas; el texto accesible expresa “desde” y “hasta”.
- Las tablas desktop incluyen encabezados y caption/título asociado; las cards mobile conservan etiquetas, no sólo valores.

## 22. Componentes visuales candidatos

Este inventario es conceptual. No autoriza crear componentes en este subpaso ni obliga a abstraerlos todos después.

| Componente candidato | Reutilización probable | Alcance recomendado | Relación con componentes actuales |
|---|---|---|---|
| Resumen de configuración | AGENDA-01/05/10 | Local a Agenda | Puede reutilizar estilos de cards, no un componente de Catálogos. |
| Matriz semanal de sólo lectura | AGENDA-01/05/10 | Reutilizable dentro de Agenda | Nuevo candidato sólo si las tres vistas comparten contrato real. |
| Matriz semanal editable | AGENDA-02/03/04 | Reutilizable dentro de Agenda | Necesita lógica propia; no generalizar fuera del dominio. |
| Día/fila de agenda | Parte de la matriz | Local interno | No abstraer hasta comprobar repetición real. |
| Card de configuración futura | AGENDA-01 | Local a Agenda | Card actual como patrón visual, sin heredar estado Activo/Inactivo. |
| Badge temporal de configuración | AGENDA-01/05/06/10 | Reutilizable dentro de Agenda | No reutilizar `x-catalogos.estado-badge`, cuyo contrato es booleano. |
| Listado de bloqueos desktop | AGENDA-01 | Local a Agenda | Reutiliza patrón de tabla responsive existente. |
| Card mobile de bloqueo | AGENDA-01 | Local a Agenda | Reutiliza patrón tabla desktop/cards mobile, no lógica de Catálogos. |
| Badge temporal de bloqueo | AGENDA-01/08/09 | Reutilizable dentro de Agenda | Requiere cuatro estados derivados; no usar badge booleano. |
| Empty state | Varias pantallas | Reutilizable visualmente | Evaluar el `x-catalogos.empty-state`; no renombrarlo/globalizarlo sin uso demostrado. Puede mantenerse markup local inicialmente. |
| Filtros de bloqueos | AGENDA-01 | Local a Agenda | No usar `x-catalogos.filtros`, que presupone búsqueda y activo/inactivo. |
| Resumen de errores | Formularios largos | Potencialmente transversal | Los errores por campo reutilizan `x-input-error`; el resumen requeriría contrato probado. |
| Advertencia de turnos afectados | AGENDA-07 | Local a Agenda | No abstraer todavía; contenido propio del UC. |
| Advertencia de bloqueos superpuestos | AGENDA-07/09 | Posible reutilización local | Extraer sólo si ambas vistas comparten datos y semántica. |
| Vista previa de continuidad | AGENDA-10 | Local a Agenda | No generalizar; semántica específica de configuraciones temporales. |
| Campos y botones básicos | Formularios | Reutilizable existente | Priorizar `x-input-label`, `x-input-error`, `x-text-input`, `x-primary-button`, `x-secondary-button` y `x-danger-button` cuando su contrato encaje. |
| Confirmación final | Escrituras sensibles | Reutilizable existente | Usar SweetAlert2 global y `.js-confirm-action` cuando el formulario ya contiene todos los datos y el texto simple alcanza. |

El aspecto compartido no basta para crear una abstracción. Especialmente, no se generalizan componentes de Catálogos que incorporen semántica Activo/Inactivo.

## 23. Cobertura de las secciones A–F

| Sección | Pantallas | Acción principal | Resultado UX |
|---|---|---|---|
| A | AGENDA-01, AGENDA-02, AGENDA-03, AGENDA-05 | Crear o editar horarios habituales y vigencia | Configuración completa registrada/actualizada, matriz validada y retorno al dashboard. |
| B | AGENDA-01, AGENDA-04, AGENDA-05 | Cambiar duración con fecha de efecto | Nueva vigencia con matriz compatible; histórico preservado. |
| C | AGENDA-01, AGENDA-07 | Registrar bloqueo tras revisar impactos | Bloqueo independiente registrado; turnos y superposiciones sólo advertidos. |
| D | AGENDA-01, AGENDA-06, AGENDA-05 | Consultar historial y detalle | Listado paginado y detalle completo de sólo lectura. |
| E | AGENDA-01, AGENDA-08, AGENDA-09 | Anular bloqueo con motivo | Anulación trazable, recálculo comunicado sin promesa de disponibilidad. |
| F | AGENDA-01, AGENDA-05, AGENDA-10 | Anular futura no iniciada con motivo | Registro conservado en historial y continuidad prospectiva comunicada. |

El paso 4 de la secuencia principal se materializa como retorno a AGENDA-01 con datos refrescados y feedback global después de cada escritura exitosa.

## 24. Mapa navegable completo

### Mapa textual

```text
MÓDULO TURNOS
└── AGENDA-01 Configurar agenda de atención
    ├── Crear primera / Nueva configuración ──> AGENDA-02 ──éxito──> AGENDA-01
    ├── Editar futura ─────────────────────────> AGENDA-03 ──éxito──> AGENDA-01/origen
    ├── Cambiar duración ──────────────────────> AGENDA-04 ──éxito──> AGENDA-01
    ├── Consultar vigente/futura ──────────────> AGENDA-05 ──volver─> AGENDA-01
    │   ├── Editar futura ─────────────────────> AGENDA-03
    │   └── Anular futura ─────────────────────> AGENDA-10 ──éxito──> AGENDA-01
    ├── Ver historial ─────────────────────────> AGENDA-06
    │   └── Consultar histórica/anulada ───────> AGENDA-05 ──volver─> AGENDA-06
    ├── Registrar bloqueo ─────────────────────> AGENDA-07 ──éxito──> AGENDA-01
    └── Consultar bloqueo ─────────────────────> AGENDA-08
        └── Anular bloqueo ────────────────────> AGENDA-09 ──éxito──> AGENDA-01
```

### Transiciones y retornos

| Origen y acción | Destino | Al cancelar/volver | Al guardar/confirmar | Tras error | Contexto conservado |
|---|---|---|---|---|---|
| Módulo Turnos → Configurar agenda | AGENDA-01 | Abandonar finaliza UC | No aplica | Mensaje de carga con reintento | No aplica |
| AGENDA-01 → Crear primera/Nueva | AGENDA-02 | AGENDA-01; confirma descarte si hubo cambios | AGENDA-01 refrescada | Permanece en AGENDA-02 con datos y errores | Posición/origen conceptual |
| AGENDA-01/05 → Editar futura | AGENDA-03 | Origen exacto | Origen o AGENDA-01 refrescada | Permanece con matriz completa | Origen y, si aplica, página |
| AGENDA-01 → Cambiar duración | AGENDA-04 | AGENDA-01 | AGENDA-01 refrescada | Permanece; incompatibilidades visibles | No aplica |
| AGENDA-01/06 → Consultar configuración | AGENDA-05 | Origen exacto | No aplica | Mensaje y opción de volver | Página de historial o dashboard |
| AGENDA-01 → Ver historial | AGENDA-06 | AGENDA-01 | No aplica | Mantiene filtro/página válida | Página de historial |
| AGENDA-01 → Registrar bloqueo | AGENDA-07 captura | AGENDA-01 | Pasa primero a revisión | Permanece en captura | Datos ingresados |
| AGENDA-07 “Revisar” | AGENDA-07 revisión | “Volver a editar” conserva captura | SweetAlert2 y luego AGENDA-01 | Actualiza advertencias y exige reconfirmar | Captura y resultados de revisión |
| AGENDA-01 → Consultar bloqueo | AGENDA-08 | Listado | No aplica | Mensaje y retorno | Filtro + página de bloqueos |
| AGENDA-08/listado → Anular bloqueo | AGENDA-09 | Detalle/listado | SweetAlert2; luego listado actualizado | Permanece con motivo | Filtro + página + origen |
| AGENDA-05/card futura → Anular configuración | AGENDA-10 | Detalle/dashboard | SweetAlert2; luego AGENDA-01 | Permanece con motivo o informa cambio temporal | Origen |

En todos los formularios, cancelar un SweetAlert2 vuelve al formulario/revisión sin borrar valores. Un error de negocio o concurrencia nunca redirige silenciosamente a éxito.

## 25. Decisiones descartadas del prototipo DeepSeek

| Propuesta observada | Decisión adoptada | Razón |
|---|---|---|
| Formularios de configuración en modal alto con scroll interno | Páginas AGENDA-02/03 | Matriz extensa, múltiples errores, URL navegable y mejor mobile. |
| Cambio de duración en modal breve sin matriz | Página AGENDA-04 con matriz heredada editable | El UC exige identificar y corregir franjas incompatibles. |
| Registro de bloqueo en modal | Página AGENDA-07 con etapa de revisión | Debe listar turnos afectados y múltiples solapamientos antes de confirmar. |
| Historial completo en modal | AGENDA-06 paginada + AGENDA-05 | La colección crece indefinidamente y requiere detalle navegable. |
| Anulación con motivo capturado dentro de SweetAlert2 | AGENDA-09/10 capturan y validan el motivo; SweetAlert2 sólo confirma | El motivo es dato de formulario obligatorio y debe conservarse ante errores. |
| Estados persistidos de configuración (`estado`) | Badges derivados | Las fuentes no definen esa columna. |
| `tipo` y `todo_el_dia` persistidos en bloqueos | Presets y modo de captura que producen `motivo`, `inicio_at`, `fin_at` | El Diccionario sólo persiste esos datos de negocio. |
| Filtros de bloqueo “Activos/Anulados/Todos” | Próximos/En curso/Pasados/Anulados/Todos | “Activo” oculta diferencias temporales relevantes. |
| Acción única “Anular” sin “Consultar” en bloqueos | Consultar siempre; Anular sólo si no anulado | El detalle preserva contexto y trazabilidad; no hay Editar/Eliminar. |
| Futuras sin acciones completas | Consultar, Editar y Anular bajo `vigencia_desde > hoy` | Lo permiten Diccionario y UC; hoy ya cuenta como iniciada. |
| Historial sólo de finalizadas | Incluye finalizadas y futuras anuladas | El UC exige conservar y consultar ambas. |
| Advertencia genérica de turnos sin comprobación/listado | Revisión real con cantidad, fechas y horarios | Es un mínimo funcional congelado. |
| Mensaje “el intervalo vuelve a estar disponible” al anular | Mensaje de recálculo condicionado | Otros bloqueos, turnos o ausencia de configuración pueden mantener indisponibilidad. |
| Hasta dos franjas visuales implícitas | Cantidad ilimitada con “Agregar franja” | No existe máximo funcional de dos. |
| Día “activo” como dato | Día con atención derivado de sus franjas | No existe booleano persistido. |
| Librerías/CDN del mock | Stack Blade/Tailwind/Alpine/Vite/SweetAlert2 existente | No se incorpora infraestructura paralela. |

Sí se conservan, adaptados, la jerarquía tipo dashboard, las cards de acciones, el resumen visible de la matriz, los chips horarios, la separación entre futuras e historial y el patrón tabla desktop/cards mobile.

## 26. Preguntas abiertas reales

No se identifican preguntas abiertas que impidan congelar esta especificación UX.

## 27. Criterios para el siguiente subpaso

Antes de pasar al diseño técnico o a la implementación, esta especificación debe aprobarse como contrato UX. El siguiente subpaso deberá:

1. Mantener AGENDA-01 a AGENDA-10 y sus retornos, o documentar explícitamente cualquier ajuste aprobado.
2. Traducir los datos persistidos y derivados sin agregar columnas de presentación.
3. Definir autorización exclusiva de la kinesióloga y precondición de perfil completo.
4. Diseñar cómo se obtienen el estado temporal, la configuración aplicable por fecha, los turnos afectados, los bloqueos superpuestos y la vista previa de continuidad, sin materializar slots.
5. Preservar formularios complejos como páginas y reservar SweetAlert2 para confirmaciones/feedback.
6. Resolver en backend todas las validaciones críticas descritas; el feedback cliente no puede ser la única barrera.
7. Definir cómo se conserva el formulario completo y el contexto de retorno tras errores.
8. Mantener paginación de históricos y bloqueos, inicialmente de 10 elementos cuando corresponda.
9. Reutilizar componentes e infraestructura existentes sólo cuando el contrato encaje; evitar heredar semántica de Catálogos.
10. Diseñar verificaciones específicas para cada estado vacío, caso límite, breakpoint y requisito de accesibilidad aquí enumerado.

La aprobación de este documento no autoriza aún migrations, Models, Requests, Actions, Policies, rutas, vistas, JavaScript ni tests.
