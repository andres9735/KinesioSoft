import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import { inicializarTomSelects } from './components/tom-select';
import { inicializarRelacionesJurisdiccionColegio } from './components/jurisdiccion-colegio';
import { inicializarRelacionesColegioMatricula } from './components/colegio-matricula';

window.Alpine = Alpine;
window.Swal = Swal;

inicializarTomSelects();
inicializarRelacionesJurisdiccionColegio();
inicializarRelacionesColegioMatricula();

Alpine.start();
