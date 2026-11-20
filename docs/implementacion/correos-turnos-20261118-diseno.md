# Rediseño de correos de turnos — 18/11/2026

1. **Subpaso ejecutado.** Presentación HTML de Recordatorio, Oferta de adelanto y Cambio de horario confirmado conforme a los tres PNG aprobados. No se modifica elegibilidad, autorización, prioridad, vencimientos efectivos, estados ni política de transporte.

2. **Archivos inspeccionados.** Tres PNG de referencia, `AGENTS.md`, las tres vistas y Mailables, los preparadores/despachadores correspondientes, el modelo de paciente/usuario, la ruta `turnos.mis-turnos.index`, las pruebas relacionadas y el arnés de aislamiento existente. `docs/archivo/` no fue inspeccionado.

3. **Archivos creados.** `app/Mail/PresentacionTurno.php`; vistas compartidas `resources/views/emails/turnos/{layout,_fecha,_estado}.blade.php`; `tests/Feature/Mail/PlantillasTurnosTest.php`; este informe. Evidencia y herramientas locales ignoradas en `storage/framework/testing/correos-diseno-20261118/`: preflight, perfil PHP, ejecutor, renderer PHP, capturador de Chrome, HTML sintéticos, PNG y XML de PHPUnit.

4. **Archivos modificados.** Tres Mailables y tres vistas existentes; `PrepararContenidoRecordatorioTurnoService`, `DespacharOfertaAdelantoService`, `DespacharConfirmacionAdelantoService`; pruebas `PrepararContenidoRecordatorioTurnoServiceTest`, `PrimeraEmisionAdelantoTest`, `ConfirmacionAdelantoTest`. No se modifican `.env`, dependencias, esquema, tareas de Windows ni la preparación operativa.

5. **Cambios realizados.** Tablas de presentación, estilos en línea, colores y jerarquía de las referencias; tipografía Arial y aviso de oferta en Georgia; adaptación a móvil por columnas apiladas y botones legibles. Nombre real desde `users.name`, escapado por Blade y agregado sólo al contenido transitorio. Fechas con día en español y hora inicial `HH:mm hs.`. Vencimiento mostrado en `America/Argentina/Buenos_Aires` sin segundos/microsegundos; se conserva su instante real. El estado aceptado conserva su etiqueta, incluido Pendiente. Mis turnos utiliza la ruta existente. Los enlaces seguros y las condiciones confirmar/cancelar siguen proviniendo del flujo previo. No cambia el contrato cifrado de confirmaciones históricas.

6. **Pruebas/comandos.** Regresión pertinente de siete archivos: **144 pruebas / 1006 aserciones PASS**, cero errores/fallos/omisiones, salida 0, PHP 8.2.12 / PHPUnit 11.5.55, 229,316 s. Después del ajuste exclusivamente visual: **7 pruebas / 73 aserciones PASS**, salida 0, 0,486 s; son el mismo subconjunto de presentación, no se suman a la regresión. Sintaxis PHP y Pint `--test` de los once PHP afectados: PASS. `git diff --check`: PASS. No se requiere compilar Vite para estas vistas de correo.

   Preflight: testing, SQLite `:memory:` efectivo por PDO/PRAGMA sin archivo, único driver sqlite y PDO MySQL deshabilitado también en hijos; Mail/Queue fake, transporte array, sesión/cache array, NullHandler, storage/vistas/cachés/documentos exclusivos y clave efímera. Las migraciones de los tests sólo alcanzan SQLite en memoria. El renderer construye Mailables ficticios sin despachar ni encolar.

7. **Resultado y comparación visual.** Renderizados los tres correos y una variante informativa sin botones. Chrome independiente sin interfaz carga únicamente archivos HTML ficticios; las URLs HTTP/HTTPS están bloqueadas durante la captura. No se manipula el Chrome del usuario ni VS Code. Se inspeccionaron visualmente las tres capturas de escritorio al mismo ancho de **1360 px** que las referencias (680 píxeles CSS a escala 2), y la oferta móvil. Las doce variantes de escritorio/375/320 px pasan el control de ausencia de desbordamiento horizontal. El manifiesto geométrico está en `comparacion-layout.json`.

   | Correo | Referencia | Captura final |
   |---|---|---|
   | Recordatorio | 1360 × 1334 | 1360 × 1338 |
   | Oferta | 1360 × 1246 | 1360 × 1260 |
   | Confirmación | 1360 × 1010 | 1360 × 1018 |

   El contenedor mantiene el mismo ancho y posición: 1224 px de ancho, x=68, y=157. Se conserva el color de fondo `#edf0f7`, el violeta `#473ac5` y el panel `#f2efff`, muestreados de las referencias. Diferencias introducidas: cruz de marca reconstruida con tablas HTML sin imagen externa, con extremos cuadrados; variaciones pequeñas de métricas tipográficas/espaciado que producen 4/14/8 px de altura adicional; en móvil se apilan columnas y botones y el aviso permite salto natural. El estado Confirmado usa una insignia verde en lugar del ámbar de Pendiente mostrado en la maqueta. No se certifican Gmail/Outlook/Apple Mail reales ni sus modos oscuros; los clientes que omitan bordes redondeados o sombras conservan el contenido y el orden.

8. **Discrepancias y límites.** No hubo envíos SMTP, procesamiento de turnos reales, consultas a MariaDB ni modificación de la confirmación histórica. La prueba compara HTML real renderizado por Blade y capturado localmente; no acredita recepción de correo. El formato anterior con hora final/segundos se conserva sólo en los datos internos existentes cuando forma parte de su contrato, y se transforma exclusivamente para la vista.

9. **Git.** Rama `feature/turnos-asignacion-automatica`, HEAD `5aeb94b407f03e934d51209dd9e9cd1a395d54df`. Se preservan los cambios previos del selector y su prueba, y `docs/archivo/` sin seguimiento. Sin staging, commit ni push.

10. **Límite del subpaso.** Se termina el rediseño y su verificación local. No se habilitan Scheduler/worker ni se avanza a pruebas operativas con pacientes o correos reales.
