# UC–06 — Gestionar Mis turnos: cierre definitivo del incremento (6.18)

**UC–06: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora este informe**, hasta su alcance implementado y certificado. Las dependencias externas y los límites de evidencia indicados no se declaran resueltos. **No hay un nuevo incremento iniciado.**

## Identificación y alcance

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD inicial de 6.18: `ee3d2577c2899c791e842e53e1f45b542ae2ab98`, cierre de 6.17; staging vacío y sin cambios trackeados al iniciar.
- Base de UC-06: `6c9dcd63160ca60603547feab7eb6afe525ee653`, cierre de UC-07 V2.
- Alcance literal de 6.18, en `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md`:

> Elaborar informe de cierre, matriz de cumplimiento de los 40 criterios de aceptación, evidencias y estado final de Git.

El incremento ofrece consulta de Primeras consultas y Seguimientos propios, Próximos/Historial, filtros y paginación, detalle autorizado, originales privados de UC-07, confirmación de asistencia y cancelación con límite inclusivo de 12 horas. Preserva autorización, programación, ocupación de Agenda, auditoría, idempotencia y atomicidad; registra una intención durable por cancelación elegible. La interfaz conserva contexto y recuperación sin reenvíos automáticos.

El cierre distingue **UC-06 completo según su alcance implementado y certificado** de los contratos, interfaces y dependencias de otros incrementos todavía pendientes. No declara operativos planes clínicos, UC-08, enlaces externos, recordatorios o procesos de ofertas inexistentes.

## Fuentes y trazabilidad normativa

Las seis fuentes locales de `docs/_codex_sources/turnos/uc06/` siguen aprobadas, congeladas y excluidas de Git: especificación funcional V1.0, D-01–D-18, matriz de armonización/dependencias, DT-01–DT-12, UX-01–UX-05 y planificación incremental V1.0. Su exclusión y disponibilidad local son deliberadas, conforme a la incorporación documental autorizada en 6.01; no se incorporan ni modifican por este cierre.

La especificación, sección 20, conserva **34 criterios directos/de salida y seis de integración**. La matriz siguiente mantiene CA-01–CA-40 sin renumerar. Los contratos C-01–C-14 y decisiones externas DX-01–DX-07 se contrastaron en 6.16; ese documento detalla por contrato qué está implementado, qué tiene sólo interfaz y qué depende de otro componente.

Trazabilidad de diseño: DT-01–DT-02 gobiernan ocupación/clasificación; DT-03, Seguimientos y UC-08; DT-04, autorización/privacidad; DT-05–DT-08, Actions, locking, replay y auditoría; DT-09, intención durable; DT-10, canales externos; DT-11–DT-12, consultas y pruebas. UX-01–UX-05 corresponden a listado, filtros, detalle, operaciones y recuperación/accesibilidad, implementados en 6.05–6.15 con sus límites de validación.

## Resumen de los subpasos y commits

| Subpaso | Resultado y referencia histórica |
| --- | --- |
| 6.01 | Fuentes y trazabilidad local certificadas según `AGENTS.md`; fuentes excluidas de Git, sin commit propio de sus contenidos. |
| 6.02 | Rama e instrucciones de UC-06: `f59ca2b`. |
| 6.03 | Baseline inicial PHP/JS/build/MariaDB: `1502843`. |
| 6.04 | Estados y ocupación compartida con Agenda: `97b9ac3`. |
| 6.05 | Próximos/Historial y partición temporal: `f1c103a`. |
| 6.06 | Filtros, búsqueda y navegación: `458a308`. |
| 6.07 | Autorización individual mediante Policy: `ecef8f9`. |
| 6.08 | Detalle y originales privados; corrección D-09/R4, C-02 pendiente: `10a2b55`. |
| 6.09 | Transacción compartida y orden de locks: `a1c662d`. |
| 6.10 | Confirmar asistencia, auditoría y replay: `cb607b2`. |
| 6.11 | Intención durable, contexto inmutable y UNIQUE: `eea2d15`. |
| 6.12 | Cancelar, liberar ocupación y registrar intención atómicamente: `9bd7586`. |
| 6.13 | Concurrencia real y correcciones D1/D2 certificadas: `616085b`. |
| 6.14 | Acciones HTTP desde el detalle, referencia cifrada y diálogos: `5a9c6ed`; regularización documental en `aae4c2f`. |
| 6.15 | UX final y validación manual: `cdf196b`. |
| 6.16 | Verificación de integraciones y límites: `d73af11`. |
| 6.17 | Regresión ordinaria integral: `ee3d257`. |
| 6.18 | Este informe y seguimiento definitivo; formalización mediante el commit que los incorpore, sin anticipar su hash. |

Los commits se comprobaron en el historial de la rama. Los baselines y cierres 6.03–6.12 constan en `AGENTS.md`; las certificaciones 6.13–6.17 están en documentos específicos versionados. Las menciones a futuros subpasos pendientes en esos documentos son fotografías históricas de cada cierre; no invalidan la secuencia posterior ni se reescriben para aparentar evidencia nueva.

## Certificaciones principales reutilizadas

| Evidencia | Resultado y alcance |
| --- | --- |
| [6.13](turnos-gestionar-mis-turnos-613-certificacion.md), MariaDB 10.4.32 / InnoDB / REPEATABLE-READ | V1 **2 tests / 49 assertions PASS**; V2-A **1/21 PASS**; V2-B **27/609 PASS**, 25 esperas reales, 8 carreras y 26 límites entre casos. UNIQUE, timeout 1205, deadlock 1213 y rollback verificados; auditoría sin residuos ni conexiones ajenas y esquema intacto. |
| [6.14](turnos-gestionar-mis-turnos-614-certificacion.md) | PHP **1207/8555 PASS**, integración UC-07 **62/586 PASS**, JS **26 + 155 PASS**, build 95 módulos; R1 y validación manual principal satisfactorias, SQLite temporal aislado con 48 migraciones y 44 tablas. |
| [6.15](turnos-gestionar-mis-turnos-615-certificacion.md) | PHP **290/2402 PASS**, JS **41 + 155 PASS**, build 95 módulos y cuatro comprobaciones UX satisfactorias; auditoría final PASS. Fuentes remotas omitidas en Mis turnos sin relajar CSP, progreso de transporte separado del diálogo y mensajes claros para rechazos conocidos. |
| [6.16](turnos-gestionar-mis-turnos-616-certificacion.md) | **45 tests / 617 assertions PASS** de contratos entre componentes; recorrido UC-07 → confirmar → cancelar/replay → nueva reserva del cupo. Integraciones externas clasificadas sin crear consumidores. |
| [6.17](turnos-gestionar-mis-turnos-617-certificacion.md) | PHP ordinario completo **2760 tests / 18474 assertions PASS**, sin fallos, errores, omitidos, risky o warnings; JS **196 PASS**; build **PASS, 95 módulos**; lint/Pint acotado **54 archivos PASS**. SQLite `:memory:` efectivo, `testing`, configuración sin caché e integridad de 683 archivos PASS. |

Los resultados corresponden a ejecuciones independientes y no se suman. El resultado global vigente es el de 6.17; su XML incluye Unit/Feature, no las suites reales `tests/Integration/MySql` o `tests/Integration/Uc06MariaDb`. Estas últimas y las pruebas visuales no se repiten en 6.18.

Incidencias resueltas conservadas en la evidencia: D1 cerró conexiones propias del harness aun ante excepciones; D2 recuperó la colisión UNIQUE 1062 mediante lectura actual `lockForUpdate()` en la misma conexión/transacción, conservando `firstOrCreate()` y su savepoint. En 6.16 se corrigió sólo la invocación que compartía bootstrap con PHPUnit; en 6.17 se resolvió el bloqueo de subprocesos Node `spawn EPERM` mediante autorización, sin modificar tests. El aviso de Browserslist fue informativo y no se actualizaron dependencias.

## Matriz de los 40 criterios de aceptación

**V:** evidencia de pruebas existente localizada y incluida en las certificaciones citadas. **R:** revisión estática o del límite disponible, con limitación de evidencia explícita. **P:** dependencia externa, aunque el comportamiento administrativo o contrato de salida disponible pueda estar verificado. Estos estados no equivalen a 40 ejecuciones independientes ni a un 40/40 PASS.

Referencias de pruebas (rutas relativas al repositorio):

- **MT:** `tests/Feature/Turnos/MisTurnosTest.php`.
- **TP:** `tests/Feature/Policies/TurnoPolicyTest.php`.
- **HTTP:** `tests/Feature/Turnos/MisTurnosAutogestionTest.php`.
- **CONF / CANC:** `tests/Feature/Actions/Turnos/ConfirmarAsistenciaTurnoActionTest.php` y `CancelarTurnoActionTest.php`.
- **INT:** `tests/Feature/Services/Turnos/RegistrarIntencionEvaluacionCupoServiceTest.php`.
- **UC07:** `tests/Feature/Turnos/PrimeraConsultaIntegracionTest.php`.
- **MDB:** `tests/Integration/Uc06MariaDb/AutogestionConcurrenciaTest.php`, certificado en 6.13.
- **JS / UX:** `tests/JavaScript/mis-turnos.test.mjs` y `tests/Feature/Turnos/MisTurnosUxTest.php`; evidencia manual de 6.14–6.15 con alcance acotado.

| ID | Criterio resumido | Estado | Evidencia concreta y límite |
| --- | --- | --- | --- |
| CA-01 | Turnos propios de ambos tipos, actuales e históricos | V | MT: `test_owner_reads_detail_of_both_types_in_every_state_and_temporal_section`; partición de ambos tipos. D-01/D-09. |
| CA-02 | Rechazo de consulta, operaciones y documentos ajenos | V | TP: `test_each_resource_contract_requires_the_persisted_owner_and_rejects_guests`; HTTP: `test_policy_runs_before_reference_validation_and_client_authority_is_rejected`; UC07: descarga privada en el recorrido real. D-09. |
| CA-03 | Pendiente/Confirmado futuro en Próximos | V | MT: `test_sections_partition_both_types_by_state_and_full_start_instant_without_mutation`. D-14. |
| CA-04 | Cancelado futuro en Historial | V | MT: partición de estados y `test_localization_of_future_cancelled_appointment_uses_history_order_and_pagination`. D-14. |
| CA-05 | Al inicio pasa a Historial sin mutar estado | V | MT: `test_appointment_moves_to_history_at_its_start_without_changing_state`. D-02/D-14. |
| CA-06 | Órdenes cronológicos estables | V | MT: `test_sections_order_by_scheduled_start_and_id_including_future_cancellations_in_history`. D-14. |
| CA-07 | Filtros combinados sin alterar clasificación | V | MT: `test_combined_filters_and_independent_pagination_preserve_only_validated_criteria` y filtros de cada estado. D-15. |
| CA-08 | Búsqueda parcial por profesional, sin distinguir mayúsculas | V | MT: `test_professional_search_is_partial_case_insensitive_and_scoped_to_own_appointments`. D-15. |
| CA-09 | Fechas inclusivas y rechazo de rango invertido | V | MT: `test_date_filters_are_inclusive_open_and_based_on_local_scheduled_start` y `test_invalid_range_returns_to_the_filter_form_with_accessible_errors_and_preserved_input`. D-15. |
| CA-10 | Paginaciones independientes | V | MT: `test_ten_per_page_and_independent_paginators`. D-15. |
| CA-11 | Limpiar restablece filtros y primeras páginas | V | MT: `test_apply_and_clear_reset_both_pages_and_discard_the_highlight_hint`. D-15/D-16. |
| CA-12 | Vacío general y acceso a UC-07 | V | UX: `test_unregistered_patient_keeps_the_uc07_entry_and_honest_empty_message`. D-01/D-16. |
| CA-13 | Vacío filtrado distinto de ausencia total | V | MT: `test_empty_filtered_results_are_not_presented_as_an_unregistered_patient`; UX: salida real para limpiar búsqueda. D-16. |
| CA-14 | Error de carga distinto de vacío real | R | `MisTurnosController::index()` y `MisTurnosQueryService::listar()` no convierten excepciones de consulta en colecciones vacías; JS distingue fallas técnicas de resultados conocidos. No se localizó un test específico que inyecte una falla de la carga inicial del listado: no se atribuye esa cobertura dinámica. D-16. |
| CA-15 | Motivo y adjuntos originales autorizados de UC-07 | V | MT: `test_first_consultation_detail_shows_only_original_documents_associated_with_that_turno`; UC07: `test_uc07_to_uc06_confirmation_cancellation_and_rebooking_preserve_shared_contracts`. D-08. |
| CA-16 | Identidad/número/total de sesión de Seguimiento cuando exista | P | MT: `test_followup_does_not_inherit_request_data_or_fabricate_plan_or_session_information` verifica ausencia honesta y no herencia. No hay relación clínica real de plan/sesión para certificar su presentación: C-02/DT-03 pendiente. D-05/D-08. |
| CA-17 | No exponer registros clínicos profesionales de UC-08 | R | Proyección administrativa explícita de `MisTurnosQueryService::detalle()` y MT: `test_visible_card_fields_exclude_clinical_and_internal_data`. Sin UC-08 operativo no se certifica acceso contra registros clínicos reales; no se añaden ni inventan. D-08/D-09. |
| CA-18 | Confirmación desde el registro hasta antes del inicio | V | CONF: `test_allows_any_strictly_future_start_without_minimum_notice`. D-06. |
| CA-19 | Rechazo exactamente al inicio | V | CONF: `test_rejects_first_confirmation_at_or_after_start`; HTTP: reloj revalidado tras abrir el diálogo. D-06. |
| CA-20 | Confirmar conserva intervalo y ocupación | V | CONF: `test_confirms_both_types_preserving_all_fields_and_occupation`; recorrido UC07/Agenda de 6.16. D-06/D-13. |
| CA-21 | Cancelar con exactamente doce horas | V | CANC: `test_allows_exactly_twelve_hours_or_more_using_complete_instants`. D-04. |
| CA-22 | Rechazo con menos de doce horas tras esperar | V | HTTP: `test_time_is_revalidated_after_the_dialog_was_opened`; CANC: `test_deadline_is_rechecked_after_locks_instead_of_using_request_start_time`; MDB: reloj tras espera real. D-04/D-10. |
| CA-23 | Cancelar un Confirmado autorizado | V | CANC: `test_confirmation_then_cancellation_are_distinct_valid_operations`; recorrido HTTP de 6.16. D-04/D-06. |
| CA-24 | Sin nuevas transiciones desde estados incompatibles | V | HTTP: `test_buttons_and_post_restrictions_cover_every_type_and_state`; CONF/CANC: rechazo de estado persistido incompatible. Cancelado admite sólo el replay informativo de D-11, sin nueva cancelación o escritura. D-03. |
| CA-25 | Pendiente → En atención por UC-08 | P | Contrato C-04/DT-03; UC-06 respeta estados profesionales. El inicio clínico y la restauración del estado previo al abandonar corresponden a UC-08, pendiente. |
| CA-26 | Cancelar Seguimiento no consume sesión ni modifica plan/otros turnos | P | CANC: `test_followup_cancellation_does_not_touch_other_scheduled_turnos_or_create_replacements` verifica otros turnos y ausencia de reemplazo. Consumo de sesión y estado de un plan real no son certificables sin C-02/DT-03; DX-02 pendiente. D-05. |
| CA-27 | Liberación, historial e intención única de evaluación | V | CANC: `test_cancels_each_eligible_combination_with_one_audit_and_intention_and_releases_occupation`; UC07: reserva posterior del cupo. Evaluación efectiva sigue externa. D-04/D-13/D-17. |
| CA-28 | Sin candidatos no se revierte una cancelación | P | C-10/DT-09: cancelación e intención se consolidan sin invocar candidatos/ofertas; CANC verifica ausencia de efectos externos. No existe consumidor para ensayar end-to-end la ausencia de candidatos. |
| CA-29 | Repetir Confirmar no duplica transición | V | CONF: `test_retries_use_current_state_without_duplicate_transition_audit_or_timestamp`; MDB: workers serializados. D-11. |
| CA-30 | Repetir Cancelar no duplica liberación/evaluación lógica | V | CANC: `test_replay_after_deadline_and_start_does_not_write_or_replace_original_reason`; INT: `test_repeated_registration_returns_the_same_intention_without_writes`; MDB: UNIQUE concurrente. D-11. |
| CA-31 | Concurrencia sin cambios incompatibles | V | MDB: `test_two_independent_workers_serialize_real_transitions` y `test_different_turnos_contend_for_the_expected_shared_mutex`, con esperas InnoDB observadas en 6.13. D-10. |
| CA-32 | Programación obsoleta rechazada | V | HTTP: `test_change_after_reference_check_is_still_detected_by_original_transactional_action`; MDB: `test_revalidates_committed_changes_after_an_observed_lock_wait`. D-10. |
| CA-33 | Auditoría de actor, canal, instante y transición | V | CONF/CANC: contexto auditado de operaciones efectivas; HTTP: `test_original_actions_are_reached_and_replay_has_no_duplicate_effects`. D-12. |
| CA-34 | Sin éxito parcial si falla consistencia/auditoría | V | CANC: `test_failure_in_turno_audit_or_intention_rolls_back_all_effects_without_retry`; MDB: `test_persistence_failure_rolls_back_state_audit_intention_and_occupation`. D-12/D-17. |
| CA-35 | Enlace válido permite confirmar y después cancelar | P | Actions compartidas y contexto prevalidado verificados en 6.16; no hay resolvedor de enlaces que certifique integridad, vigencia y reutilización real del mismo enlace. C-08/DT-10. |
| CA-36 | Enlace sin acceso general o documental | P | Portal y documentos tienen autorización separada; no existe canal externo para certificar el alcance de un enlace real. No confundir la referencia cifrada del portal con ese canal. C-08/DT-10. |
| CA-37 | Recordatorio informativo de Confirmado sin reconfirmar | P | Contrato C-09/DT-10: Agenda Diaria Automática y emisor no operativos; el campo `recordatorio_enviado_at` no acredita implementación. |
| CA-38 | Falla de entrega sin alterar turno ni impedir portal | P | Autogestión independiente de correo, verificada sin envíos en 6.16; no se ensaya una entrega fallida real porque falta el emisor/consumidor externo. C-09/DT-10. |
| CA-39 | Desistir sin modificar turno | V | JS: confirmar/cancelar con diálogo desistido, cerrado o sin consentimiento no envían POST; cobertura funcional/visual de 6.14–6.15. D-17. |
| CA-40 | Respuesta perdida/retry sin duplicados y con consulta del resultado | V | JS: timeout, respuesta perdida, consulta GET y ausencia de reenvío automático; HTTP: replay; MDB: `test_real_timeout_has_no_partial_writes_and_explicit_retry_is_safe`. D-11/D-17. |

Resultado de trazabilidad: **40 IDs conservados**; **30 V**, **2 R** (CA-14/CA-17), **2 criterios directos condicionados** (CA-16/CA-26) y **6 de integración pendientes de sus responsables** (CA-25/CA-28/CA-35–CA-38). Esta clasificación documenta los límites sin convertir ausencia de evidencia o de infraestructura en un PASS total. No se identificó una contradicción material demostrada entre el comportamiento disponible y las fuentes congeladas que impida cerrar ese alcance.

## Seguridad, integridad e integraciones

- Identidad derivada del usuario, propiedad persistida y Policy por operación; autenticación, verificación de correo, perfil y CSRF intactos. Referencia cifrada/autenticada vinculada a actor, turno y programación; no es un token de acceso externo.
- Originales de Primera consulta privados e inmutables; autorización documental independiente. No se exponen anotaciones clínicas inventadas ni información de otros pacientes.
- Transacción compartida con locks kinesióloga → paciente → turno y relectura autoritativa de estado, programación, permisos y reloj; sin holds ni reintentos automáticos del callback.
- Auditoría obligatoria atómica; motivo administrativo opcional sin sobrescribir el motivo de solicitud. Replay no duplica transiciones, auditorías o intenciones ni reactiva estados profesionales posteriores.
- C-01/C-03/C-05/C-06/C-14 verificados en el alcance disponible. C-07 y C-13 tienen garantías administrativas comprobadas, con reprogramación/plan externos pendientes. C-08 tiene interfaz de canal; C-10 tiene registro durable, no procesamiento. C-02/C-04 y C-09/C-11/C-12 requieren sus flujos externos para integración completa.
- Las huellas de los **683 archivos** de código, tests, configuración y manifiestos coinciden con 6.17: `4598E754B82808973C6B01E2AD54E696EE09B612C4440328B4F25D989D4FCAFE`, con el algoritmo descrito en esa certificación. Se comprobó por lectura, sin ejecutar pruebas. No hubo cambios productivos pendientes al iniciar 6.18.

## Limitaciones y dependencias pendientes

1. C-02/DT-03: relaciones reales de planes, sesiones, versión, número y total; contabilidad y recuperación clínica de Seguimientos.
2. UC-08: inicio desde Pendiente/Confirmado, atención y restauración exacta del estado previo al abandonar; coordinación futura con los mutex compartidos.
3. Reprogramación profesional completa, trazabilidad y decisiones DX-01/DX-02/DX-07, sin extender automáticamente reglas de autogestión a cancelaciones profesionales.
4. Enlaces seguros operativos: integridad, alcance, vigencia, revocación y reutilización controlada; recordatorios y Agenda Diaria Automática. DX-03 no queda resuelta por un enum o una referencia del portal.
5. Consumidor de intenciones, evaluación efectiva de cupos, candidatos, priorización, ofertas, vencimientos, reservas temporales y aceptación; DX-04–DX-06 siguen externas. `procesado_at` y la intención persistida no implementan estos procesos.
6. Límites de evidencia CA-14 y CA-17 descritos en la matriz; la validación manual tampoco equivale a una auditoría exhaustiva de navegadores, dispositivos o accesibilidad. SQLite no certifica locking real; MariaDB 6.13 acredita sus escenarios concretos, no ausencia universal de deadlocks.

Son límites explícitos del cierre y dependencias futuras; no se autorizan ampliaciones funcionales para eliminarlos en esta fase ni se alteran los criterios congelados para ocultarlos.

## Resumen técnico apto para revisión o futura descripción de PR

UC-06 completa la autogestión administrativa de turnos propios con Próximos/Historial, filtros, detalle y originales privados, confirmación de asistencia y cancelación. Reutiliza UC-07 y Agenda, preserva autorización y concurrencia, y consolida transición/auditoría/intención durable de forma atómica e idempotente. Incluye UX recuperable y accesible, recuperación segura de colisiones UNIQUE y cierre de conexiones del harness.

La validación combina la regresión final **2760/18474 PHP**, **196 JavaScript**, build **95 módulos**, la concurrencia MariaDB **27/609** y las evidencias funcionales/visuales ya aprobadas. Las dependencias clínicas, recordatorios, enlaces y ofertas están identificadas como pendientes; no se atribuye al incremento su implementación. Este texto es una propuesta para revisión: no se creó un PR, fusión o despliegue.

## Condiciones para integración futura y estado final

- Revisar y autorizar el commit documental y, posteriormente, el PR/merge/despliegue si correspondiera. No se modifica ni fusiona ninguna rama en este cierre.
- Evaluar el diff completo contra la rama de destino y la vigencia de las certificaciones si aparecen cambios posteriores; no repetirlas sin motivo técnico concreto.
- Antes de aplicar migraciones ya versionadas en otro entorno, comprobar destino, estado y autorización específica; la migración de intenciones no se aplica a desarrollo/producción por este informe. No inferir permisos para acceder, reinicializar o limpiar bases o sesiones temporales.
- Conservar disponibles las seis fuentes locales congeladas y trasladar explícitamente los contratos pendientes a los incrementos responsables, sin asumir un consumidor operativo o debilitar autorización, auditoría, idempotencia y locking.
- Preparar por separado el siguiente incremento únicamente cuando el usuario defina alcance y autorización. **Agenda Diaria Automática no fue iniciada.**

**Dictamen: LISTO PARA CIERRE DEFINITIVO del alcance implementado y certificado.** El cierre queda formalizado mediante el commit que incorpore `AGENTS.md` y este documento, sin inventar un hash futuro ni dejar una regularización documental posterior obligatoria. No quedan subpasos UC-06 para ejecución automática; no hay un nuevo incremento iniciado.

Inventario exacto propuesto: `AGENTS.md` y `docs/implementacion/turnos-gestionar-mis-turnos-618-cierre.md`. En 6.18 no se ejecutaron PHP, JavaScript, MariaDB, build, migraciones, servidores o pruebas visuales; sólo revisión documental, comprobaciones de Git/hashes y `git diff --check`. `docs/archivo/` permanece fuera de inspección y modificación; staging vacío, sin commit/push/merge/PR.
