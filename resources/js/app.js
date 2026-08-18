import './bootstrap';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import { inicializarTomSelects } from './components/tom-select';
import { inicializarRelacionesJurisdiccionColegio } from './components/jurisdiccion-colegio';

window.Alpine = Alpine;
window.Swal = Swal;

inicializarTomSelects();
inicializarRelacionesJurisdiccionColegio();

Alpine.start();
