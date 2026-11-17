# Verificación aislada de adelantos, subpaso 9

Este arnés adapta las guardas, el bootstrap y el control de procesos de
`AgendaDiariaMariaDb639`. No modifica ese arnés histórico. Reutiliza su clase
`Barrera`. Los nombres `Entorno639`, `Guardas639` y `Caso639` se conservan dentro
del namespace nuevo para que la procedencia de la adaptación sea visible.

El destino está fijado en `127.0.0.1:3308`, MariaDB 10.11.19, esquema exclusivo
`adelantos_09_sandbox`, directorio descartable
`storage/framework/testing/agenda-mariadb-639-instancia/data`. Cualquier perfil
distinto se rechaza antes de fixtures. No usar la base habitual ni cambiar las
guardas para conseguir que una conexión pase.

Las variables de proceso `AAT09_*` son AUTORIZACION, DATABASE, HOST, PORT,
DATADIR, USERNAME, PASSWORD, RUN, APP_KEY y TIMEZONE. AUTORIZACION vale
`AUTORIZADA_639_migraciones` o `AUTORIZADA_639_pruebas`, según el rol. Las cuentas
son `adelanto09_migrador` y `adelanto09_runner`; sus privilegios se comprueban
contra el esquema literal escapado, sin GRANT OPTION. El runner sólo tiene DML.
La clave y las credenciales locales se conservan cifradas por DPAPI fuera de Git.

PHP se ejecuta con `-n`, exclusivamente PDO MySQL, mbstring, fileinfo y openssl,
sin cargar `.env`, con `zend.exception_ignore_args=1`. Storage/cachés/vistas son
exclusivos por RUN y PID, correo/cache/sesión array, NullHandler. No se usan
transacciones envolventes ni traits de migración/limpieza en PHPUnit.

`preparar.php` exige esquema vacío, verifica servidor y permisos y ejecuta sólo
`migrate`, una vez. Agrega una marca RUN. Rechaza esquemas poblados: nunca hace
fresh, reset, truncate ni drop para repetir. La suite agrega fixtures nuevos y
los conserva; no reejecutar preparación sobre esta base poblada.

Los workers reciben referencias por stdin, heredan exclusivamente el perfil
autorizado y variables básicas de Windows, y abren conexiones propias. Una
barrera detiene al ganador después del SELECT FOR UPDATE de la kinesióloga;
el padre verifica que el otro proceso ya intentó el lock, mantiene la espera
250 ms y libera al primero. Se verifican PID y CONNECTION_ID distintos, espera
mayor de 200 ms, resultado persistido y ausencia de duplicados. Los resultados
no imprimen credenciales, SQL de error ni mensajes de transporte.

La carrera aceptación → bloqueo acredita además el rechazo del snapshot
profesional anterior. El worker vuelve a revisar y registrar el bloqueo con la
advertencia actualizada, siguiendo el recorrido implementado; no omite esa
protección ni cancela el turno aceptado.

Comando de suite, después de preparar el perfil y comprobar identidad:

```text
php -n -d extension_dir=C:/xampp/php/ext -d extension=pdo_mysql -d extension=mbstring -d extension=fileinfo -d extension=openssl -d zend.exception_ignore_args=1 -d variables_order=EGPCS -d memory_limit=512M vendor/bin/phpunit -c tests/Integration/AdelantosMariaDb09/phpunit.xml --do-not-cache-result --colors=never --log-junit <evidencia-exclusiva>
```

El wrapper local de esta ejecución y sus perfiles están ignorados en
`storage/framework/testing/asignacion-cierre-20261117-09/`. El informe
`docs/implementacion/asignacion-automatica-turnos-09-cierre.md` identifica la
corrida definitiva, el volumen, los límites y los pendientes de navegador.
La suite no es una prueba de carga HTTP/SMTP ni de candidatos de Seguimiento.
