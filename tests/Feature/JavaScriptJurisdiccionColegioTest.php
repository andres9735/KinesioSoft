<?php

namespace Tests\Feature;

use Tests\TestCase;

class JavaScriptJurisdiccionColegioTest extends TestCase
{
    public function test_app_initializes_dependency_after_tom_select(): void
    {
        $app = file_get_contents(
            resource_path('js/app.js')
        );

        $this->assertStringContainsString(
            "import { inicializarRelacionesJurisdiccionColegio } from './components/jurisdiccion-colegio';",
            $app
        );

        $tomSelectPosition = strpos(
            $app,
            'inicializarTomSelects();'
        );

        $dependencyPosition = strpos(
            $app,
            'inicializarRelacionesJurisdiccionColegio();'
        );

        $this->assertNotFalse($tomSelectPosition);
        $this->assertNotFalse($dependencyPosition);

        $this->assertLessThan(
            $dependencyPosition,
            $tomSelectPosition
        );
    }

    public function test_reusable_module_owns_jurisdiccion_colegio_behavior(): void
    {
        $module = file_get_contents(
            resource_path(
                'js/components/jurisdiccion-colegio.js'
            )
        );

        $expectedFragments = [
            "[data-jurisdiccion-colegio-target]",
            'option.dataset.jurisdiccion',
            '.tomselect.getValue()',
            '.tomselect.on(',
            "'change'",
            'colegioTom.clear(true)',
            'colegioTom.clearOptions()',
            'colegioTom.addOptions(',
            'colegioTom.enable()',
            'colegioTom.disable()',
            'colegioTom.setValue(',
            'colegioTom.refreshOptions(false)',
            "jurisdiccionSelect.addEventListener(",
        ];

        foreach ($expectedFragments as $fragment) {
            $this->assertStringContainsString(
                $fragment,
                $module
            );
        }
    }

    public function test_professional_views_declare_dependency_without_implementing_it(): void
    {
        $views = [
            resource_path(
                'views/perfil/matriculas/create.blade.php'
            ),
            resource_path(
                'views/perfil/matriculas/edit.blade.php'
            ),
            resource_path(
                'views/perfil/completar-kinesiologa.blade.php'
            ),
        ];

        foreach ($views as $view) {
            $contenido = file_get_contents($view);

            $this->assertStringContainsString(
                'data-jurisdiccion-colegio-target="#colegio_emisor_id"',
                $contenido,
                $view
            );

            $this->assertStringContainsString(
                'data-colegio-placeholder-sin-jurisdiccion="Primero seleccione una jurisdicción"',
                $contenido,
                $view
            );

            $this->assertStringContainsString(
                'data-colegio-placeholder-con-jurisdiccion="Seleccione un colegio emisor"',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'actualizarColegiosPorJurisdiccion',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'document.addEventListener',
                $contenido,
                $view
            );
        }
    }

    public function test_colegio_options_keep_jurisdiccion_metadata(): void
    {
        $views = [
            resource_path(
                'views/perfil/matriculas/create.blade.php'
            ),
            resource_path(
                'views/perfil/matriculas/edit.blade.php'
            ),
            resource_path(
                'views/perfil/completar-kinesiologa.blade.php'
            ),
        ];

        foreach ($views as $view) {
            $contenido = file_get_contents($view);

            $this->assertStringContainsString(
                'data-jurisdiccion="{{ $colegio->jurisdiccion_id }}"',
                $contenido,
                $view
            );
        }
    }

    public function test_professional_profile_has_no_inline_javascript(): void
    {
        $perfil = file_get_contents(
            resource_path(
                'views/perfil/completar-kinesiologa.blade.php'
            )
        );

        $this->assertStringNotContainsString(
            '<script',
            $perfil
        );

        $this->assertStringNotContainsString(
            'document.addEventListener',
            $perfil
        );

        $this->assertStringNotContainsString(
            'new TomSelect',
            $perfil
        );

        $this->assertStringNotContainsString(
            'window.TomSelect',
            $perfil
        );

        $this->assertStringNotContainsString(
            '.tomselect',
            $perfil
        );

        $this->assertStringNotContainsString(
            'actualizarColegiosPorJurisdiccion',
            $perfil
        );

        $this->assertStringNotContainsString(
            '@json',
            $perfil
        );

        $this->assertStringContainsString(
            'data-tom-select="especialidades"',
            $perfil
        );

        $this->assertStringContainsString(
            'data-jurisdiccion-colegio-target="#colegio_emisor_id"',
            $perfil
        );

        $this->assertStringContainsString(
            'data-colegio-placeholder-sin-jurisdiccion="Primero seleccione una jurisdicción"',
            $perfil
        );

        $this->assertStringContainsString(
            'data-colegio-placeholder-con-jurisdiccion="Seleccione un colegio emisor"',
            $perfil
        );
    }
}
