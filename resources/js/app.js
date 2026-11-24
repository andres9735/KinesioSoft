import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import { inicializarTomSelects } from './components/tom-select';
import { inicializarRelacionesJurisdiccionColegio } from './components/jurisdiccion-colegio';
import { inicializarRelacionesColegioMatricula } from './components/colegio-matricula';
import ejercicioCatalogo from './components/ejercicio-catalogo';
import domicilioPaciente from './components/domicilio-paciente';
import matrizSemanalAgenda from './agenda/matriz-semanal';
import bloqueoAgendaForm from './agenda/bloqueo-form';
import solicitarTurno from './turnos/solicitar-turno';
import misTurnos from './turnos/mis-turnos';
import agendaAtencion from './agenda/agenda-atencion';
import revisionDeclaracion from './historia-clinica/revision-declaracion';
import confirmacionRevision from './historia-clinica/confirmacion-revision';

window.Alpine = Alpine;
window.Swal = Swal;

inicializarTomSelects();
inicializarRelacionesJurisdiccionColegio();
inicializarRelacionesColegioMatricula();
Alpine.data('ejercicioCatalogo', ejercicioCatalogo);
Alpine.data('domicilioPaciente', domicilioPaciente);
Alpine.data('matrizSemanalAgenda', matrizSemanalAgenda);
Alpine.data('bloqueoAgendaForm', bloqueoAgendaForm);
Alpine.data('solicitarTurno', solicitarTurno);
Alpine.data('misTurnos', misTurnos);
Alpine.data('agendaAtencion', agendaAtencion);
Alpine.data('revisionDeclaracion', revisionDeclaracion);
Alpine.data('confirmacionRevision', confirmacionRevision);

Alpine.start();
