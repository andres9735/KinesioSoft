<?php

namespace Tests\Feature;

use Tests\TestCase;

class JavaScriptEjercicioCatalogoTest extends TestCase
{
    public function test_app_imports_and_registers_component_before_starting_alpine(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "import ejercicioCatalogo from './components/ejercicio-catalogo';",
            $app
        );
        $this->assertStringContainsString("Alpine.data('ejercicioCatalogo', ejercicioCatalogo);", $app);
        $this->assertLessThan(strpos($app, 'Alpine.start();'), strpos($app, "Alpine.data('ejercicioCatalogo'"));
        $this->assertSame(1, substr_count($app, 'Alpine.start();'));
        $this->assertStringNotContainsString('cdn', strtolower($app));
    }

    public function test_module_exposes_resource_ux_operations_and_stable_keys(): void
    {
        $module = file_get_contents(resource_path('js/components/ejercicio-catalogo.js'));

        foreach ([
            'agregarRecurso()',
            'cambiarOrigen(recurso)',
            'alternarActivo(recurso)',
            'moverArriba(index)',
            'moverAbajo(index)',
            'quitarRecurso(index)',
            'actualizarOrdenes()',
            '`persisted-${id}`',
            '`new-${contadorNuevos++}`',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $module);
        }
    }

    public function test_persisted_resources_are_defended_from_ordinary_removal(): void
    {
        $module = file_get_contents(resource_path('js/components/ejercicio-catalogo.js'));
        $partial = file_get_contents(resource_path('views/catalogos/ejercicios-domiciliarios/partials/recursos.blade.php'));

        $this->assertStringContainsString('if (!recurso || recurso.id)', $module);
        $this->assertStringContainsString('x-show="!recurso.id"', $partial);
        $this->assertStringContainsString('Quitar recurso nuevo', $partial);
    }

    public function test_real_file_input_and_nested_names_are_rendered_without_storage_path(): void
    {
        $partial = file_get_contents(resource_path('views/catalogos/ejercicios-domiciliarios/partials/recursos.blade.php'));

        $this->assertStringContainsString('type="file"', $partial);

        foreach (['id', 'tipo', 'origen', 'titulo', 'orden', 'activo', 'archivo', 'url_externa'] as $campo) {
            $this->assertStringContainsString('[${index}]['.$campo.']', $partial);
        }

        $this->assertStringNotContainsString('[ruta_storage]', $partial);
        $this->assertStringNotContainsString('type="hidden" name="archivo', $partial);
    }

    public function test_file_inputs_are_not_serialized_as_base64(): void
    {
        $module = file_get_contents(resource_path('js/components/ejercicio-catalogo.js'));
        $partial = file_get_contents(resource_path('views/catalogos/ejercicios-domiciliarios/partials/recursos.blade.php'));
        $contenido = $module."\n".$partial;

        foreach (['FileReader', 'readAsDataURL', 'base64', 'data:'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $contenido);
        }
    }

    public function test_opposite_origin_input_is_disabled_and_reordering_updates_orders(): void
    {
        $module = file_get_contents(resource_path('js/components/ejercicio-catalogo.js'));
        $partial = file_get_contents(resource_path('views/catalogos/ejercicios-domiciliarios/partials/recursos.blade.php'));

        $this->assertStringContainsString(':disabled="recurso.origen !== \'LOCAL\'"', $partial);
        $this->assertStringContainsString(':disabled="recurso.origen !== \'EXTERNO\'"', $partial);
        $this->assertStringContainsString('recurso.orden = index;', $module);
        $this->assertStringContainsString('this.actualizarOrdenes();', $module);
    }

    public function test_view_warns_that_files_must_be_reselected_after_validation(): void
    {
        $partial = file_get_contents(resource_path('views/catalogos/ejercicios-domiciliarios/partials/recursos.blade.php'));

        $this->assertStringContainsString('deben seleccionarse nuevamente después de un error de validación', $partial);
        $this->assertStringNotContainsString('value="{{ old(\'recursos', $partial);
    }

    public function test_module_has_no_demo_persistence_or_development_logging(): void
    {
        $module = file_get_contents(resource_path('js/components/ejercicio-catalogo.js'));

        foreach (['localStorage', 'fetch(', 'console.log', '.save(', 'XMLHttpRequest'] as $fragment) {
            $this->assertStringNotContainsString($fragment, $module);
        }
    }
}
