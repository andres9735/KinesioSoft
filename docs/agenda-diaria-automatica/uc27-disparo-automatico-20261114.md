# UC-27 — Ensayo del disparo automático local

14/11/2026, rama `feature/turnos-agenda-diaria-automatica`, HEAD `cd9295f`. Ensayo acotado a UC-27, reutilizando la SQLite y Mailpit del ensayo E2E. Sin cambios en código de aplicación, `.env`, bases habituales, permisos MariaDB, staging o commits. La evidencia previa se conserva.

## Entorno y alcance

Base efectiva: `storage/framework/testing/agenda-e2e-20261114/ensayo.sqlite`. Único driver PDO SQLite, PDO MySQL deshabilitado. SMTP 127.0.0.1:11025, Mailpit http://127.0.0.1:18025, sin relay externo, sólo destinatarios example.test. Cola database/sqlite `agenda-e2e-20261114`, caché file del ensayo, zona America/Argentina/Buenos_Aires y origen HTTPS localhost:8443. Sin reloj simulado ni cambios en el cron `*/5 * * * *`.

Se utiliza el `schedule:work` real de Laravel. El auxiliar temporal `uc27-scheduler/consola.php` conserva el evento registrado en routes/console.php, `uc27:seleccion-recordatorios`, e impide ejecutar los demás eventos mediante filtros sólo de proceso. La constante ARTISAN_BINARY dirige sus procesos hijos al mismo auxiliar con preflight. PHPRC apunta al php.ini exclusivo ya utilizado por el ensayo y PHP_INI_SCAN_DIR evita ini adicionales; los hijos tampoco cargan PDO MySQL. No se ejecutó UC-29 ni se instaló una tarea permanente del sistema operativo.

El worker real usa exclusivamente la cola del ensayo. El supervisor acotado detiene ambos daemons después de observar las dos ejecuciones; no altera Apache, Mailpit ni otros procesos.

## Estado inicial

Mailpit inicial: **2 mensajes**, con sus IDs conservados para comparación; cero destinados a `e2e-20261114-scheduler@example.test`. Se conservaron las dos decisiones anteriores y sus horarios.

Nuevo turno ficticio id 3: Seguimiento, PENDIENTE, **15/11/2026 17:00–17:30**, misma profesional habilitada y nuevo paciente sintético. Verificados futuro estricto, ventana de 24 horas, margen superior a 12 horas, correo utilizable, ausencia de ocupación superpuesta y ausencia de recordatorio previo. Preparación a las 19:10:14 locales, salida 0. No se generó otra jornada.

Preflight salida 0: SQLite efectivo, drivers, SMTP, cola, cron, zona y php.ini exclusivos confirmados. Comando de observación: `& storage/framework/testing/agenda-e2e-20261114/uc27-scheduler/observar.ps1`. Inició scheduler y worker a las 19:10:52; próximos disparos naturales previstos a las **19:15 y 19:20**, sin llamar manualmente a la preparación.

Auxiliares y registros permanecen ignorados en `storage/framework/testing/agenda-e2e-20261114/uc27-scheduler/`: preflight.json, procesos.json, mailpit-inicial.json, mailpit/base por fase, eventos.jsonl, seleccion.log, scheduler.log y worker.log. Las consultas de resultado usan SQLite OPEN_READONLY y query_only=ON; no imprimen secretos ni URLs de asistencia.

## Resultado

Primer disparo real: `ScheduledTaskStarting` a las **19:15:00**, `ScheduledTaskFinished` a las **19:15:02**, **código de salida 0**, PDO MySQL false/driver SQLite. La tarea informó **Recordatorios seleccionados: 1**. El worker inició el Job a las **19:15:03** y terminó DONE, 349.11 ms. Mailpit capturó el mensaje nuevo a las **19:15:03.341** locales.

Consulta de las 19:15:49: **3 mensajes totales**, uno nuevo para el destinatario del ensayo, dos mensajes anteriores conservados. Un recordatorio nuevo PROCESADO, un intento ACEPTADO, cero Jobs pendientes. El nuevo turno sigue PENDIENTE y con todos sus atributos intactos; los dos turnos anteriores también están intactos y sigue existiendo una sola ejecución de jornada. Verificador final de esta fase: **PASS, salida 0**.

Incidencia del auxiliar de evidencia, sin fallo de UC-27: la primera comprobación de IDs salió 1 porque `registrar.ps1 inicial` utilizó el mismo nombre de archivo que la captura inicial y reemplazó su lista de IDs. El conteo inicial de dos mensajes quedó conservado. Se separó la línea base en `mailpit-linea-base.json`, utilizando los IDs de los dos correos en la evidencia intacta previa `mailpit-primero.log`; ambos coinciden con Mailpit. El cotejo corregido verifica exactamente un ID nuevo y los dos anteriores conservados, sin reenviar correos ni ejecutar manualmente la selección. Esta corrección sólo afecta auxiliares ignorados.

Segundo disparo real: `ScheduledTaskStarting` a las **19:20:00**, `ScheduledTaskFinished` a las **19:20:01**, **código de salida 0**, PDO MySQL false/driver SQLite. La tarea informó **Recordatorios seleccionados: 0**. No se creó otro Job ni otro intento.

Consulta final a las **19:20:18**: Mailpit conserva **3 mensajes totales** (los 2 anteriores más exactamente 1 nuevo), con el mismo ID nuevo y la misma fecha de captura que tras el primer disparo. Sigue existiendo un solo recordatorio PROCESADO y un solo intento ACEPTADO para el turno nuevo. Cola vacía; el turno nuevo permanece PENDIENTE con sus atributos originales, y ambos turnos previos siguen intactos. La única ejecución de UC-29 tampoco cambió. Verificador de segunda fase **PASS, salida 0**.

| Momento local, 14/11/2026 | Seleccionados | Total Mailpit | Resultado |
| --- | --- | --- | --- |
| Antes del primer disparo | Sin recordatorio previo para el turno nuevo | 2 | Estado inicial registrado |
| 19:15:00–19:15:03 | 1 | 3 | Tarea exit 0, Job DONE, una captura nueva |
| 19:20:00–19:20:01 | 0 | 3 | Tarea exit 0, sin duplicado ni segundo Job |

El supervisor emitió **OBSERVACION_DOS_EJECUCIONES=PASS**, salida **0**, y detuvo sólo los dos daemons que creó a las **19:20:20**. Su parada es deliberada al terminar la observación, no una certificación del código de salida individual de esos daemons continuos. Registros de error de scheduler y worker vacíos. No se mantuvo un scheduler permanente ni se modificó la instancia habitual.

**Disparo automático de UC-27 verificado en el ensayo local**, con cron real y dos ejecuciones consecutivas, aceptación SMTP y captura Mailpit; no se acredita entrega externa ni lectura del paciente. Este resultado amplía la evidencia histórica que tenía el disparo pendiente, sin reescribirla. No certifica concurrencia MariaDB ni activación permanente/productiva. El disparo de UC-29 a las 07:00 sigue fuera de este ensayo. **6.39 pendiente**, **6.40 provisional** y evaluación externa del cupo pendiente.
