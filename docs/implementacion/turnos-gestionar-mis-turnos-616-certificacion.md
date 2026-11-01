# UC–06 — Subpaso 6.16: verificación y cierre de integraciones

**6.16: IMPLEMENTADO, VERIFICADO Y CERRADO mediante el commit que incorpora esta certificación**, hasta el alcance efectivamente disponible. Los consumidores y dependencias externos identificados abajo permanecen pendientes.

## Referencia y alcance congelado

- Rama: `feature/turnos-gestionar-mis-turnos`.
- HEAD de partida: `cdf196bc0bc98f84bfb93967bba019c1db6a595a`; staging vacío y sin cambios trackeados al iniciar.
- Fuente: `docs/_codex_sources/turnos/uc06/UC06_PLANIFICACION_INCREMENTAL_V1.0.md`, Fase E, subpaso 6.16:

> Verificar contratos con UC-07, Agenda, Seguimientos, UC-08, enlaces, recordatorios y adelantamiento; distinguir lo implementado de lo pendiente.

Se contrastaron C-01–C-14 y DX-01–DX-07 de `UC06_MATRIZ_ARMONIZACION_DEPENDENCIAS_V1.0.md` con el código real y DT-03–DT-10 / DT-12 del diseño técnico congelado. No se modificaron fuentes, decisiones, reglas productivas ni infraestructura certificada.

## Resultado por contrato

| Contrato | Evidencia y resultado | Límite / estado externo |
| --- | --- | --- |
| C-01, UC-07 → UC-06 | Recorrido HTTP nuevo: alta real Primera consulta/Pendiente, detalle y documentos originales; confirmar, cancelar y volver a reservar el mismo cupo. D-01 bloquea mientras está Pendiente/Confirmado y permite una nueva intención de reserva tras Cancelado. | **Implementado y verificado**. No se reabre el wizard certificado. |
| C-02, Seguimientos | El detalle distingue el tipo y no inventa datos de planes/sesiones ni hereda motivo o documentos de Primera consulta. `Turno` no expone vínculos clínicos de plan y sesión. | **C-02 / DT-03 pendiente** de infraestructura real de programación y gestión clínica. |
| C-03, Agenda | `AgendaQueryService` consume los estados ocupantes canónicos; la confirmación mantiene la reserva y la cancelación la libera. El GET de profesionales disponibles vuelve a ofrecer el intervalo y UC-07 puede reservarlo sin reactivar el turno cancelado. | **Implementado y verificado** para ocupación administrativa. No implica ofertas automáticas. |
| C-04, UC-08 | Las Actions rechazan estados profesionales incompatibles y los replays no restauran Pendiente/Confirmado sobre estados posteriores. | **Garantía de UC-06 verificada**; iniciar atención desde Pendiente/Confirmado y restaurar el estado previo al abandonar requieren el flujo clínico de UC-08, no implementado aquí. |
| C-05, Seguridad | Policy/Requests, aislamiento entre pacientes, rechazo de autoridad enviada por cliente y middleware CSRF real; descarga de originales rechazada para otro paciente. | **Implementado y verificado** en los canales disponibles. |
| C-06, Auditoría | Transiciones efectivas auditadas, replay sin duplicados; fallas posteriores a una confirmación consolidada revierten sólo la cancelación, su auditoría e intención. | **Implementado y verificado**; eventos técnicos sin incorporar contenido clínico completo o secretos. |
| C-07, Reprogramación | Referencia del portal y comparación de programación dentro de las Actions rechazan datos obsoletos, incluso en replay. | **Defensa de UC-06 implementada**; flujo profesional completo, trazabilidad y política DX-01 siguen externos. |
| C-08, Enlaces de asistencia | Ambas Actions aceptan contexto de canal prevalidado y auditan `ENLACE_SEGURO` sin simular sesión autenticada. | **Interfaz preparada, sin resolvedor operativo** de enlace, integridad, alcance, vigencia o revocación. El enum no autoriza; la referencia cifrada del portal no sustituye ese canal. |
| C-09, Recordatorios | UC-06 funciona sin enviar correos ni notificaciones. Existe `recordatorio_enviado_at`, sin proceso operativo de selección/envío de recordatorios en las rutas, comandos o servicios relevados. | **Agenda Diaria Automática y recordatorios pendientes**; no se certifica selección, entrega, deduplicación o fallas de un emisor inexistente. |
| C-10, Evaluación de cupo | Cancelación e intención durable única por auditoría son atómicas; replay y nuevo registro no duplican ni reabren la intención. `procesado_at` distingue pendiente de procesada. | **Registro implementado; consumidor pendiente**. No se certifica evaluación efectiva ni reintento de un worker externo. |
| C-11, Ofertas ↔ Agenda | La cancelación deja el cupo libre sin crear ocupación adicional; una nueva reserva explícita ocupa el cupo como turno distinto. | **Generación de ofertas, reservas temporales y candidatos pendientes**. No se implementan desde UC-06. |
| C-12, Respuesta a oferta | UC-06 relee programación y estado persistidos y rechaza capturas obsoletas. | **Aceptación, reprogramación y trazabilidad de adelantamientos pendientes**; el guard no acredita un proceso de ofertas. |
| C-13, Gestión del plan | Cancelar Seguimiento conserva otros turnos programados y no crea reemplazos; se verifica el límite administrativo disponible. | **Plan y consumo/recuperación de sesiones clínicas no certificables** sin C-02 / DT-03 y DX-02. No se inventan vínculos ni contadores. |
| C-14, Documentos UC-07 | Motivo, tipo, programación, metadatos, asociación y bytes originales se conservan después de confirmar/cancelar. Descarga privada autorizada y rechazo del paciente ajeno. | **Implementado y verificado**. La cancelación no borra ni reescribe el snapshot original. |

Los estados ocupantes canónicos continúan siendo Pendiente, Confirmado y En atención; Atendido, Ausente y Cancelado no reservan agenda. Se reutiliza la certificación previa de los seis estados y las garantías MariaDB de 6.13, sin repetirlas.

## Cobertura añadida y reutilizada

Se identificó una ausencia real: `PrimeraConsultaIntegracionTest` llegaba desde UC-07 a Mis turnos y verificaba documentos/replay de la solicitud; las pruebas de autogestión partían de turnos preparados. Faltaba unir ambos flujos con la posterior reutilización del cupo y la elegibilidad D-01.

Se añadió únicamente `test_uc07_to_uc06_confirmation_cancellation_and_rebooking_preserve_shared_contracts` en esa clase, reutilizando sus perfiles, agenda, almacenamiento privado falso y métodos de entrada existentes. No se crearon fixtures, bases, servidores o lanzadores nuevos para la validación manual ni nuevas clases auxiliares. Los datos de las pruebas ordinarias se generan exclusivamente en memoria mediante los mecanismos de testing existentes.

La selección dirigida comprende 20 métodos y sus datasets (**45 casos**):

| Clase existente | Métodos seleccionados |
| --- | --- |
| `PrimeraConsultaIntegracionTest` | `test_uc07_to_uc06_confirmation_cancellation_and_rebooking_preserve_shared_contracts`; `test_document_from_real_flow_is_private_for_patient_professional_and_admin_roles`. |
| `AutogestionRecuperacionTest` | `test_failed_cancellation_preserves_prior_committed_confirmation_and_can_be_explicitly_retried`. |
| `CancelarTurnoActionTest` | `test_cancels_each_eligible_combination_with_one_audit_and_intention_and_releases_occupation`; `test_retry_does_not_overwrite_later_professional_state`; `test_prevalidated_link_context_is_audited_without_faking_authenticated_session`; `test_rejects_stale_programming_before_transition_or_replay`; `test_cancellation_preserves_original_documents_without_opening_storage`; `test_followup_cancellation_does_not_touch_other_scheduled_turnos_or_create_replacements`. |
| `ConfirmarAsistenciaTurnoActionTest` | `test_confirms_both_types_preserving_all_fields_and_occupation`; `test_current_state_prevents_reactivation_even_when_received_model_is_pending`; `test_retry_after_later_transition_cannot_restore_confirmation`; `test_prevalidated_link_context_uses_same_action_without_simulating_an_authenticated_session`; `test_rejects_stale_programming_before_new_transition_or_replay`. |
| `RegistrarIntencionEvaluacionCupoServiceTest` | `test_repeated_registration_returns_the_same_intention_without_writes`; `test_retry_does_not_reset_processed_status_or_depend_on_later_turno_changes`; `test_requires_an_active_transaction_in_the_same_connection`. |
| `MisTurnosAutogestionTest` | `test_policy_runs_before_reference_validation_and_client_authority_is_rejected`; `test_real_csrf_middleware_rejects_missing_token_and_accepts_session_token`. |
| `MisTurnosTest` | `test_followup_does_not_inherit_request_data_or_fabricate_plan_or_session_information`. |

Estas pruebas reutilizadas comprueban fronteras entre componentes; no se reejecutaron clases completas ajenas a esa selección ni la regresión integral.

## Aislamiento y resultados exactos

Preflight anterior a las pruebas: `APP_ENV=testing`, `configurationIsCached()=false`, conexión/configuración efectiva SQLite `:memory:`, URL de conexión vacía y `PRAGMA database_list` con un único `main` sin archivo. PHP se inició con `-n` y sólo PDO SQLite; PDO MySQL no estaba disponible. Variables de proceso temporales restauradas mediante `finally`, incluida la clave sintética no registrada. `phpunit.xml` incluye sólo Unit/Feature, no la suite especial MariaDB.

| Verificación | Resultado |
| --- | --- |
| PHPUnit dirigido | **45 tests / 617 assertions PASS**, 12,496 s; PHP 8.2.12, PHPUnit 11.5.55; exit code **0**. |
| Incidencias PHPUnit finales | Sin failures, errors, warnings ni risky; se exigió `--fail-on-risky --fail-on-warning`. |
| Lint del PHP ampliado | **PASS**, `php -n -l tests/Feature/Turnos/PrimeraConsultaIntegracionTest.php`. |
| Pint acotado | **PASS**, `pint --test tests/Feature/Turnos/PrimeraConsultaIntegracionTest.php`. |
| `git diff --check` | **PASS**. |

Invocación final: `C:\xampp\php\php.exe -n -d extension_dir=C:/xampp/php/ext -d extension=pdo_sqlite -d extension=mbstring -d extension=fileinfo -d extension=openssl -d memory_limit=512M vendor/bin/phpunit -c phpunit.xml --do-not-cache-result --colors=never --fail-on-risky --fail-on-warning --filter <selección>`; el filtro ancla `::(?:métodos)(?: with data set|$)`, con los 20 nombres exactos anteriores unidos por `|`.

Incidencia de invocación resuelta: una primera ejecución con bootstrap de preflight dentro del mismo proceso PHPUnit completó 45 casos / 617 assertions, pero produjo 45 risky por manejadores de errores/excepciones preexistentes. No se utilizó como PASS. Se separó la comprobación del entorno de la invocación habitual de PHPUnit; no se modificaron assertions o código de aplicación ni se flexibilizaron guards o severidad para resolverlo. La ejecución final anterior es la certificación válida; no se suman ambas cantidades.

No se ejecutaron comandos manuales de migración o seed, conexiones MariaDB, nuevas validaciones visuales, JavaScript o build. La preparación interna de las pruebas ordinarias mediante los traits existentes quedó limitada al SQLite en memoria. No se repitieron las 27 pruebas MariaDB de 6.13, las validaciones visuales de 6.14–6.15 ni la regresión integral de 6.17.

## Dictamen y transición

No se detectaron defectos productivos en las integraciones disponibles verificadas. Las dependencias externas y decisiones DX-01–DX-07 conservan sus responsables y límites; su ausencia no se disfraza como una integración completa ni se resuelve implementando Agenda Diaria Automática dentro de UC-06.

Archivos del commit propuesto: `AGENTS.md`, `tests/Feature/Turnos/PrimeraConsultaIntegracionTest.php` y este documento. No se modificaron Actions, infraestructura transaccional/concurrente, migraciones, configuración persistente ni fuentes congeladas. No se incorporan datos personales reales, credenciales o evidencia temporal.

6.16 quedará formalmente cerrado mediante el commit que incorpore esta certificación, sin anticipar su hash. **6.17: siguiente subpaso pendiente, no iniciado; 6.18: pendiente.** Esta preparación no ejecuta staging, commit ni push y no autoriza iniciar esos subpasos.
