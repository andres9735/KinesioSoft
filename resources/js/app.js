import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import { inicializarTomSelects } from './components/tom-select';
import { inicializarRelacionesJurisdiccionColegio } from './components/jurisdiccion-colegio';
import { inicializarRelacionesColegioMatricula } from './components/colegio-matricula';
import ejercicioCatalogo from './components/ejercicio-catalogo';

window.Alpine = Alpine;
window.Swal = Swal;

inicializarTomSelects();
inicializarRelacionesJurisdiccionColegio();
inicializarRelacionesColegioMatricula();
Alpine.data('ejercicioCatalogo', ejercicioCatalogo);

Alpine.start();
