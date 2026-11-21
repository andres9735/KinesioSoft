const listas = ['antecedentes', 'cirugias', 'alergias', 'medicacion'];
const campos = ['objetivo_salud', 'peso_kg', 'altura_cm', 'ocupacion', 'nivel_actividad_fisica', 'detalle_actividad_fisica'];
const tipos = ['personales', 'familiares', 'cirugias', 'alergias', 'medicacion'];
const objeto = (valor) => valor && typeof valor === 'object' && !Array.isArray(valor) ? valor : {};

export default function revisionDeclaracion(originales, entrada = {}) {
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
