const listas = ['antecedentes', 'cirugias', 'alergias', 'medicacion'];
const campos = ['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica'];
const tipos = ['personales', 'familiares', 'cirugias', 'alergias', 'medicacion'];
const objeto = (valor) => valor && typeof valor === 'object' && !Array.isArray(valor) ? valor : {};

export default function revisionDeclaracion(originales, entrada = {}, contextoUX = {}) {
    entrada = objeto(entrada);
    const decisiones = objeto(entrada.dictamenes);
    const complemento = objeto(entrada.complemento_profesional);
    const aportes = objeto(complemento.bloques);
    const dictamenes = {};
    for (const bloque of [...listas, ...campos]) {
        const previo = objeto(decisiones[bloque]);
        const correccion = objeto(previo.correccion);
        dictamenes[bloque] = {
            dictamen: previo.dictamen ?? '', justificacion: previo.justificacion ?? '',
            clasificacion: objeto(previo.clasificacion),
            correccion: listas.includes(bloque) ? {
                items: Array.isArray(correccion.items) ? correccion.items : (originales[bloque]?.items ?? []).map(i => ({
                    item_origen_id: i.item_origen_id, tipo_destino: bloque === 'antecedentes' ? '' : bloque,
                    descripcion: i.descripcion, procedencia: '',
                })),
            } : { valor: Object.hasOwn(correccion, 'valor') ? correccion.valor : originales[bloque]?.valor ?? '' },
        };
    }
    return {
        ...presentacionRevision(originales, contextoUX),
        especialidad_id: entrada.especialidad_id ?? '', procedencia_relevamiento: entrada.procedencia_relevamiento ?? '',
        dictamenes,
        bloques: Object.fromEntries(tipos.map(tipo => [tipo, {
            resultado: objeto(aportes[tipo]).resultado ?? '',
            items: Array.isArray(aportes[tipo]?.items) ? aportes[tipo].items : [],
        }])),
        actualizarFamilia: Object.hasOwn(complemento, 'familia'),
        familia: { resultado: '', motivo: '', integrantes: [], incluir_contacto: false, vinculo_contacto: '', ...objeto(complemento.familia) },
        sustituciones: Array.isArray(entrada.sustituciones_base) ? entrada.sustituciones_base : [],
        agregarCorreccion(bloque) {
            this.dictamenes[bloque].correccion.items.push({ item_origen_id: null,
                tipo_destino: bloque === 'antecedentes' ? '' : bloque, descripcion: '', procedencia: '' });
        },
        agregarAporte(tipo) {
            this.bloques[tipo].items.push({ descripcion: '', procedencia: '', estado_codificacion: 'PENDIENTE_CODIFICACION' });
        },
        contrato() {
            const dictamenes = {};
            for (const bloque of [...listas, ...campos]) {
                const decision = this.dictamenes[bloque];
                const dato = { dictamen: decision.dictamen, justificacion: decision.justificacion };
                if (decision.dictamen === 'CORREGIR') {
                    dato.correccion = listas.includes(bloque) ? { items: decision.correccion.items.map(i => ({
                        item_origen_id: i.item_origen_id === '' ? null : i.item_origen_id,
                        tipo_destino: i.tipo_destino, descripcion: i.descripcion, procedencia: i.procedencia,
                    })) } : { valor: decision.correccion.valor === '' ? null : decision.correccion.valor };
                }
                if (bloque === 'antecedentes' && decision.dictamen === 'VALIDAR') dato.clasificacion = decision.clasificacion;
                dictamenes[bloque] = dato;
            }
            const complemento_profesional = { bloques: {} };
            for (const tipo of tipos) {
                const bloque = this.bloques[tipo];
                if (bloque.resultado !== '') complemento_profesional.bloques[tipo] = {
                    resultado: bloque.resultado,
                    items: bloque.resultado === 'INFORMADO' ? bloque.items : [],
                };
            }
            if (this.actualizarFamilia) complemento_profesional.familia = {
                resultado: this.familia.resultado, motivo: this.familia.motivo,
                integrantes: this.familia.resultado === 'INFORMADO' ? this.familia.integrantes : [],
                incluir_contacto: this.familia.resultado === 'INFORMADO' && !!this.familia.incluir_contacto,
                vinculo_contacto: this.familia.vinculo_contacto,
            };
            return { especialidad_id: this.especialidad_id, procedencia_relevamiento: this.procedencia_relevamiento,
                dictamenes, complemento_profesional, sustituciones_base: this.sustituciones };
        },
    };
}

const seccionesRevision = [
    { id: 'salud', titulo: 'Datos de salud y características personales' },
    { id: 'declarados', titulo: 'Antecedentes declarados' },
    { id: 'complementos', titulo: 'Relevamientos complementarios' },
    { id: 'familia', titulo: 'Núcleo familiar' },
];
const etiquetasRevision = {
    objetivo_salud: 'Objetivo de salud', peso_kg: 'Peso', altura_cm: 'Altura', ocupacion: 'Ocupación',
    nivel_actividad_fisica: 'Actividad física', detalle_actividad_fisica: 'Detalle de actividad física',
    antecedentes: 'Antecedentes declarados', personales: 'Antecedentes personales', familiares: 'Antecedentes familiares',
    cirugias: 'Cirugías', alergias: 'Alergias', medicacion: 'Medicación habitual', familia: 'Núcleo familiar',
    especialidad_id: 'Especialidad de la profesional', procedencia_relevamiento: 'Procedencia general',
};
const texto = valor => String(valor ?? '').trim();
const procedencias = ['REFERIDO_PACIENTE', 'RELEVAMIENTO_PROFESIONAL'];
const dictamenesPermitidos = ['VALIDAR', 'CORREGIR', 'OBSERVAR', 'DESCARTAR'];

// Sólo diagnóstico visual: no compone, modifica ni autoriza el resultado clínico.
// El POST y los validadores del servidor continúan siendo la autoridad.
function presentacionRevision(originales, contexto) {
    return {
        abiertas: { salud: true, declarados: false, complementos: false, familia: false },
        progresoAbierto: false,
        enfocandoInvalido: false,
        erroresServidor: { ...objeto(contexto.errores) },
        erroresNativos: {},
        intentoVistaPrevia: false,
        init() {
            if (!contexto.redisenio) return;
            this.progresoAbierto = globalThis.matchMedia?.('(min-width: 1280px)').matches ?? true;
            for (const clave of Object.keys(this.erroresServidor)) {
                const destino = this.localizarError(clave);
                if (destino) this.abiertas[destino.seccion] = true;
            }
            if (Object.keys(this.erroresServidor).length) this.$nextTick(() => this.irPrimerPendiente());
        },
        idCampo(campo) { return `revision-${campo.replace(/[^a-zA-Z0-9_-]/g, '-')}`; },
        textoPendiente(problema) {
            const partes = problema.campo.split('.');
            const clave = ['dictamenes', 'bloques', 'sustituciones_base'].includes(partes[0]) ? partes[1] : partes[0];
            let etiqueta = etiquetasRevision[clave];
            // No atribuir una categoría a claves ambiguas como items.0.procedencia.
            if (!etiqueta) return problema.mensajes.join(' ');
            const item = partes.indexOf('items');
            if (item >= 0 && /^\d+$/.test(partes[item + 1] ?? '')) etiqueta += `, aporte ${Number(partes[item + 1]) + 1}`;
            if (partes[1] === 'integrantes' && /^\d+$/.test(partes[2] ?? '')) etiqueta += `, integrante ${Number(partes[2]) + 1}`;
            if (partes[2] === 'clasificacion' && /^\d+$/.test(partes[3] ?? '')) etiqueta += `, ítem #${partes[3]}`;
            return `${etiqueta}: ${problema.mensajes.join(' ')}`;
        },
        localizarError(clave) {
            if (['especialidad_id', 'procedencia_relevamiento'].includes(clave)) return { seccion: 'salud', campo: clave };
            const sustitucion = clave.match(/^sustituciones_base\.([^\.]+)$/);
            if (sustitucion && contexto.base && [...campos, ...tipos, 'familia'].includes(sustitucion[1])) {
                return { seccion: campos.includes(sustitucion[1]) ? 'salud' : sustitucion[1] === 'familia' ? 'familia' : 'complementos', campo: clave };
            }
            if (campos.includes(clave)) return { seccion: 'salud', campo: `dictamenes.${clave}.${this.dictamenes[clave].dictamen === 'CORREGIR' ? 'correccion.valor' : 'dictamen'}` };
            const decision = clave.match(/^dictamenes\.([^\.]+)(?:\.|$)/);
            if (decision && [...campos, ...listas].includes(decision[1])) {
                // Los errores de estructura/colección se presentan en el grupo ya existente.
                const campo = clave === `dictamenes.${decision[1]}` ? `${clave}.dictamen`
                    : /^dictamenes\.[^.]+\.correccion\.items(?:\.\d+)?$/.test(clave) ? `dictamenes.${decision[1]}.correccion` : clave;
                return { seccion: campos.includes(decision[1]) ? 'salud' : 'declarados', campo };
            }
            if (clave.startsWith('familia.') || clave === 'familia' || clave.startsWith('complemento_profesional.familia')) {
                const campo = clave.replace(/^complemento_profesional\./, '');
                return { seccion: 'familia', campo: /^familia\.integrantes\.\d+$/.test(campo) ? 'familia.integrantes' : campo };
            }
            if (/^(complemento_profesional\.)?bloques\.(personales|familiares|cirugias|alergias|medicacion)(\.|$)/.test(clave)) {
                let campo = clave.replace(/^complemento_profesional\./, '');
                if (/^bloques\.[^.]+\.items\.\d+\.estado_codificacion$/.test(campo)) campo = campo.split('.').slice(0, 2).join('.');
                else if (/^bloques\.[^.]+\.items\.\d+$/.test(campo)) campo = campo.split('.').slice(0, 3).join('.');
                return { seccion: 'complementos', campo };
            }
            // items.0.* y sustituciones_base sin categoría no identifican un control.
            return null;
        },
        alternar(seccion) { this.abiertas[seccion] = !this.abiertas[seccion]; },
        navegar(seccion, campo = null) {
            this.abiertas[seccion] = true;
            this.$nextTick(() => {
                const region = this.$root.querySelector(`#revision-seccion-${seccion}`);
                const controles = [...this.$root.querySelectorAll('[data-campo]')];
                const visible = el => !el.matches(':disabled') && el.getClientRects().length > 0;
                const control = campo ? controles.find(el => el.dataset.campo === campo && visible(el))
                    ?? controles.find(el => el.dataset.campo.startsWith(`${campo}.`) && visible(el))
                    ?? (seccion === 'familia' ? controles.find(el => el.dataset.campo === 'familia' && visible(el)) : null) : null;
                const destino = control ?? region?.querySelector('button[aria-controls]');
                (control ?? region)?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
                destino?.focus?.({ preventScroll: true });
            });
        },
        irPrimerPendiente() {
            const problemas = this.progreso().problemas;
            const primero = problemas.find(p => p.error && p.seccion) ?? problemas.find(p => p.seccion);
            if (primero) this.navegar(primero.seccion, primero.campo);
            else if (problemas.length) this.$root.querySelector('#revision-errores-generales')?.focus();
        },
        registrarInvalido(evento) {
            const control = evento.target;
            const campo = control.dataset?.campo;
            if (!campo) return;
            this.erroresNativos[campo] = [control.validationMessage];
            this.intentoVistaPrevia = true;
            const destino = this.localizarError(campo);
            if (destino) {
                this.abiertas[destino.seccion] = true;
                if (!this.enfocandoInvalido) {
                    this.enfocandoInvalido = true;
                    this.navegar(destino.seccion, campo);
                    this.$nextTick(() => { this.enfocandoInvalido = false; });
                }
            }
        },
        actualizarErrores(evento) {
            const campo = evento.target.dataset?.campo;
            if (!campo) return;
            delete this.erroresNativos[campo];
            for (const clave of Object.keys(this.erroresServidor)) {
                const destino = this.localizarError(clave);
                if (destino && (destino.campo === campo || campo.startsWith(`${destino.campo}.`))) delete this.erroresServidor[clave];
            }
        },
        mensajes(campo) {
            return this.progreso().problemas.filter(p => p.campo === campo && p.error).flatMap(p => p.mensajes);
        },
        resumenFuentes(tipo) {
            return this.progreso(true).fuentes[tipo];
        },
        respuestaAntecedentesGenerales() {
            const respuesta = originales.antecedentes?.respuesta;
            return respuesta === 'no' ? 'Antecedentes generales: el paciente declaró No.'
                : respuesta === 'si' ? 'Antecedentes generales: el paciente declaró Sí.'
                    : 'Antecedentes generales: sin respuesta original Sí/No disponible.';
        },
        progreso(incluirFuentes = false) {
            const problemas = new Map();
            const dependientes = new Set();
            const agregar = (seccion, campo, mensaje, inconsistencia = false) => {
                const previo = problemas.get(campo);
                problemas.set(campo, { seccion, campo, mensajes: [...new Set([...(previo?.mensajes ?? []), mensaje])],
                    error: !!previo?.error || inconsistencia || this.intentoVistaPrevia });
            };
            const requerido = (seccion, campo, valor, etiqueta) => {
                if (!texto(valor)) agregar(seccion, campo, etiqueta);
            };
            const longitud = (seccion, campo, valor, maximo) => {
                if (texto(valor).length > maximo) agregar(seccion, campo, `El máximo es ${maximo} caracteres.`, true);
            };
            const validarValor = (campo, valor, destino) => {
                if (valor === null || texto(valor) === '') return;
                longitud('salud', destino, valor, campo === 'ocupacion' ? 255 : 5000);
                if (['peso_kg', 'altura_cm'].includes(campo)) {
                    const numero = Number(valor);
                    if (!/^\d+(\.\d{1,2})?$/.test(String(valor)) || numero < (campo === 'peso_kg' ? 1 : 30) || numero > (campo === 'peso_kg' ? 500 : 250)) {
                        agregar('salud', destino, 'Revisá el rango y la precisión de hasta dos decimales.', true);
                    }
                }
                if (campo === 'nivel_actividad_fisica' && !['SEDENTARIO', 'MODERADO', 'ACTIVO'].includes(valor)) agregar('salud', destino, 'Seleccioná una actividad física admitida.', true);
            };
            const entrada = this.contrato();
            const base = contexto.base ?? null;
            // Proyección visual opcional del mismo recorrido; no cambia el contrato ni el diagnóstico.
            const fuentes = incluirFuentes ? Object.fromEntries(tipos.map(tipo => [tipo, {
                declaracion: { items: [], respuesta: listas.includes(tipo) ? originales[tipo]?.respuesta ?? null : null,
                    general: ['personales', 'familiares'].includes(tipo),
                    dictamen: entrada.dictamenes[['personales', 'familiares'].includes(tipo) ? 'antecedentes' : tipo].dictamen },
                previa: { existe: !!base, sustituida: this.sustituciones.includes(tipo),
                    resultado: base?.bloques?.[tipo]?.resultado ?? null,
                    items: (base?.bloques?.[tipo]?.items ?? []).map(item => ({ ...item })) },
                complemento: { resultado: entrada.complemento_profesional.bloques[tipo]?.resultado ?? '', cantidad: 0 },
            }])) : null;
            requerido('salud', 'especialidad_id', this.especialidad_id, 'Seleccioná la especialidad.');
            if (texto(this.especialidad_id) && contexto.especialidades && !contexto.especialidades.map(String).includes(String(this.especialidad_id))) agregar('salud', 'especialidad_id', 'Seleccioná una especialidad vigente.', true);
            requerido('salud', 'procedencia_relevamiento', this.procedencia_relevamiento, 'Seleccioná la procedencia general.');
            if (texto(this.procedencia_relevamiento) && !procedencias.includes(this.procedencia_relevamiento)) agregar('salud', 'procedencia_relevamiento', 'Seleccioná una procedencia admitida.', true);
            const admitidos = Object.fromEntries(tipos.map(t => [t, []]));
            const sinDecidir = new Set();
            for (const bloque of [...campos, ...listas]) {
                const decision = entrada.dictamenes[bloque];
                const seccion = campos.includes(bloque) ? 'salud' : 'declarados';
                const prefijo = `dictamenes.${bloque}`;
                if (!dictamenesPermitidos.includes(decision.dictamen)) {
                    agregar(seccion, `${prefijo}.dictamen`, 'Seleccioná el dictamen profesional.');
                    if (listas.includes(bloque)) {
                        for (const tipo of bloque === 'antecedentes' ? ['personales', 'familiares'] : [bloque]) {
                            if (originales[bloque]?.items?.length) sinDecidir.add(tipo);
                        }
                    }
                    continue;
                }
                if (['OBSERVAR', 'DESCARTAR'].includes(decision.dictamen)) requerido(seccion, `${prefijo}.justificacion`, decision.justificacion, 'Ingresá la justificación clínica.');
                longitud(seccion, `${prefijo}.justificacion`, decision.justificacion, 5000);
                if (campos.includes(bloque)) {
                    if (['VALIDAR', 'CORREGIR'].includes(decision.dictamen)) {
                        const valor = decision.dictamen === 'CORREGIR' ? decision.correccion.valor : originales[bloque]?.valor;
                        validarValor(bloque, valor, decision.dictamen === 'CORREGIR' ? `${prefijo}.correccion.valor` : `${prefijo}.dictamen`);
                        const anterior = base?.campos?.[bloque];
                        const iguales = ['peso_kg', 'altura_cm'].includes(bloque) && valor !== null && texto(valor) !== '' && anterior !== null
                            ? Number(anterior) === Number(valor) : anterior === (texto(valor) === '' ? null : valor);
                        if (base && anterior !== null && anterior !== undefined && !iguales && !this.sustituciones.includes(bloque)) agregar(seccion, `sustituciones_base.${bloque}`, 'Confirmá expresamente la sustitución del dato clínico previo.');
                    }
                    continue;
                }
                let items = [];
                if (decision.dictamen === 'VALIDAR') {
                    items = (originales[bloque]?.items ?? []).map(i => ({ ...i, tipo_destino: bloque === 'antecedentes' ? decision.clasificacion[i.item_origen_id] : bloque }));
                    for (const item of items) {
                        if (!tipos.includes(item.tipo_destino)) {
                            agregar('declarados', `${prefijo}.clasificacion.${item.item_origen_id}`, 'Clasificá el antecedente como personal o familiar.');
                            sinDecidir.add('personales'); sinDecidir.add('familiares');
                        }
                    }
                }
                if (decision.dictamen === 'CORREGIR') {
                    items = decision.correccion.items;
                    if (items.length > 30) agregar(seccion, `${prefijo}.correccion`, 'El máximo es 30 registros.', true);
                    items.forEach((item, indice) => {
                        const ruta = `${prefijo}.correccion.items.${indice}`;
                        requerido(seccion, `${ruta}.descripcion`, item.descripcion, 'Describí el dato corregido.');
                        longitud(seccion, `${ruta}.descripcion`, item.descripcion, 5000);
                        if (!(bloque === 'antecedentes' ? ['personales', 'familiares'] : [bloque]).includes(item.tipo_destino)) agregar(seccion, `${ruta}.tipo_destino`, 'Seleccioná el tipo de antecedente.');
                        if (!procedencias.includes(item.procedencia)) agregar(seccion, `${ruta}.procedencia`, 'Seleccioná la procedencia individual.');
                        if (item.item_origen_id !== null && item.item_origen_id !== '' && !(originales[bloque]?.items ?? []).some(i => String(i.item_origen_id) === String(item.item_origen_id))) agregar(seccion, `${ruta}.item_origen_id`, 'El ítem debe pertenecer a la declaración original.', true);
                    });
                }
                for (const item of items) if (admitidos[item.tipo_destino]) {
                    const clinico = { descripcion: texto(item.descripcion), procedencia: item.procedencia ?? 'REFERIDO_PACIENTE', estado_codificacion: 'PENDIENTE_CODIFICACION' };
                    admitidos[item.tipo_destino].push(clinico);
                    if (fuentes) fuentes[item.tipo_destino].declaracion.items.push({ ...clinico,
                        dictamen: decision.dictamen, item_origen_id: item.item_origen_id ?? null });
                }
            }
            for (const tipo of tipos) {
                const previo = this.sustituciones.includes(tipo) ? null : base?.bloques?.[tipo];
                const finales = [...(previo?.items ?? [])];
                const sumar = item => {
                    if (!finales.some(i => i.descripcion === item.descripcion && i.procedencia === item.procedencia && i.estado_codificacion === item.estado_codificacion)) finales.push(item);
                };
                admitidos[tipo].forEach(sumar);
                const cantidad = finales.length;
                const aporte = entrada.complemento_profesional.bloques[tipo];
                let resultado = cantidad ? 'INFORMADO' : previo?.resultado;
                if (aporte) {
                    resultado = aporte.resultado;
                    if (aporte.resultado === 'INFORMADO') {
                        // El mínimo existente descarta filas sin descripción al normalizar.
                        const items = aporte.items.filter(i => texto(i.descripcion));
                        if (fuentes) fuentes[tipo].complemento.cantidad = items.length;
                        if (!items.length) agregar('complementos', `bloques.${tipo}.items`, 'Agregá al menos un antecedente con descripción.');
                        items.forEach(item => {
                            const indice = aporte.items.indexOf(item);
                            longitud('complementos', `bloques.${tipo}.items.${indice}.descripcion`, item.descripcion, 5000);
                            if (!procedencias.includes(item.procedencia)) agregar('complementos', `bloques.${tipo}.items.${indice}.procedencia`, 'Seleccioná la procedencia individual.');
                            if (item.estado_codificacion !== 'PENDIENTE_CODIFICACION') agregar('complementos', `bloques.${tipo}.items.${indice}.estado_codificacion`, 'Revisá el estado de codificación admitido.', true);
                            sumar({ ...item, descripcion: texto(item.descripcion), procedencia: texto(item.procedencia) });
                        });
                        if (items.length > 30) agregar('complementos', `bloques.${tipo}.items`, 'El máximo es 30 aportes.', true);
                    } else if (cantidad) agregar('complementos', `bloques.${tipo}.resultado`, 'Este resultado es incompatible con antecedentes admitidos o previos.', true);
                }
                if (!resultado || resultado === 'NO_RELEVADO') {
                    if (!aporte && sinDecidir.has(tipo)) dependientes.add('complementos');
                    else agregar('complementos', `bloques.${tipo}.resultado`, 'Completá el relevamiento no resuelto por la declaración o la versión previa.');
                } else if (!['INFORMADO', 'NO_PRESENTA', 'DESCONOCIDO'].includes(resultado)) agregar('complementos', `bloques.${tipo}.resultado`, 'Revisá el resultado del relevamiento.', true);
                if (finales.length > 30) agregar('complementos', `bloques.${tipo}.items`, 'El resultado conjunto supera el máximo de 30 antecedentes.', true);
            }
            const familia = this.actualizarFamilia ? entrada.complemento_profesional.familia : base?.familia;
            if (!familia || !familia.resultado || familia.resultado === 'NO_RELEVADO') agregar('familia', 'familia.resultado', 'Completá el relevamiento del núcleo familiar.');
            if (this.actualizarFamilia) {
                if (base && !this.sustituciones.includes('familia')) agregar('familia', 'sustituciones_base.familia', 'Confirmá expresamente la modificación del núcleo familiar previo.');
                longitud('familia', 'familia.motivo', familia.motivo, 2000);
                if (familia.resultado === 'INFORMADO') {
                    const integrantes = familia.integrantes.filter(i => texto(i.nombre_apellido) || texto(i.vinculo));
                    if (!integrantes.length && !familia.incluir_contacto) agregar('familia', 'familia.integrantes', 'Agregá un integrante o confirmá el contacto familiar.');
                    integrantes.forEach(i => {
                        const indice = familia.integrantes.indexOf(i);
                        requerido('familia', `familia.integrantes.${indice}.vinculo`, i.vinculo, 'Indicá el vínculo familiar.');
                        longitud('familia', `familia.integrantes.${indice}.vinculo`, i.vinculo, 100);
                        longitud('familia', `familia.integrantes.${indice}.nombre_apellido`, i.nombre_apellido, 255);
                    });
                    if (integrantes.length > 30) agregar('familia', 'familia.integrantes', 'El máximo es 30 integrantes.', true);
                    if (familia.incluir_contacto) {
                        requerido('familia', 'familia.vinculo_contacto', familia.vinculo_contacto, 'Indicá el vínculo del contacto confirmado.');
                        longitud('familia', 'familia.vinculo_contacto', familia.vinculo_contacto, 100);
                    }
                } else if (familia.resultado && !['SIN_INTEGRANTES', 'INFORMACION_NO_DISPONIBLE', 'NO_RELEVADO'].includes(familia.resultado)) agregar('familia', 'familia.resultado', 'Seleccioná un resultado familiar admitido.', true);
            }
            for (const [clave, mensajes] of Object.entries({ ...this.erroresServidor, ...this.erroresNativos })) {
                const destino = this.localizarError(clave);
                // Un error de grupo del servidor no vuelve a contar sus hijos.
                if (destino) for (const campo of [...problemas.keys()]) if (campo.startsWith(`${destino.campo}.`)) problemas.delete(campo);
                for (const mensaje of mensajes) agregar(destino?.seccion ?? null, destino?.campo ?? clave, mensaje, true);
            }
            const pendientes = [...problemas.values()];
            const secciones = seccionesRevision.map(s => ({ ...s, estado: pendientes.some(p => p.seccion === s.id && p.error) ? 'Requiere atención'
                : pendientes.some(p => p.seccion === s.id) || dependientes.has(s.id) ? 'Pendiente' : 'Completa' }));
            const contadores = Object.fromEntries(dictamenesPermitidos.map(d => [d, Object.values(this.dictamenes).filter(v => v.dictamen === d).length]));
            const completas = secciones.filter(s => s.estado === 'Completa').length;
            if (fuentes) for (const tipo of tipos) {
                const bloque = ['personales', 'familiares'].includes(tipo) ? 'antecedentes' : tipo;
                fuentes[tipo].requiereRevision = pendientes.some(p => p.campo.startsWith(`dictamenes.${bloque}.`)
                    || p.campo.startsWith(`bloques.${tipo}.`) || p.campo === `sustituciones_base.${tipo}`);
            }
            return { secciones, problemas: pendientes, contadores, completas, porcentaje: completas * 25,
                ambiguos: pendientes.filter(p => !p.seccion), ...(fuentes ? { fuentes } : {}) };
        },
    };
}
