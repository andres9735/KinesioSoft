<?php

namespace Tests\Feature;

use Tests\TestCase;

class JavaScriptTomSelectTest extends TestCase
{
    public function test_app_entry_initializes_reusable_tom_select_module(): void
    {
        $app = file_get_contents(
            resource_path('js/app.js')
        );

        $this->assertStringContainsString(
            "import { inicializarTomSelects } from './components/tom-select';",
            $app
        );

        $this->assertStringContainsString(
            'inicializarTomSelects();',
            $app
        );

        $this->assertStringNotContainsString(
            "import TomSelect from 'tom-select';",
            $app
        );

        $this->assertStringNotContainsString(
            'window.TomSelect',
            $app
        );
    }

    public function test_reusable_module_owns_tom_select_configuration(): void
    {
        $module = file_get_contents(
            resource_path(
                'js/components/tom-select.js'
            )
        );

        $this->assertStringContainsString(
            "import TomSelect from 'tom-select';",
            $module
        );

        $this->assertStringContainsString(
            "import 'tom-select/dist/css/tom-select.css';",
            $module
        );

        $this->assertStringContainsString(
            "root.querySelectorAll('[data-tom-select]')",
            $module
        );

        $this->assertStringContainsString(
            "case 'especialidades':",
            $module
        );

        $this->assertStringContainsString(
            "plugins: ['remove_button']",
            $module
        );

        $this->assertStringContainsString(
            'allowEmptyOption: true',
            $module
        );

        $this->assertStringContainsString(
            'return select.tomselect;',
            $module
        );
    }

    public function test_professional_views_use_declarative_tom_select_contract(): void
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
                'data-tom-select=',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'new TomSelect',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'window.TomSelect',
                $contenido,
                $view
            );
        }

        $perfil = file_get_contents(
            resource_path(
                'views/perfil/completar-kinesiologa.blade.php'
            )
        );

        $this->assertStringContainsString(
            'data-tom-select="especialidades"',
            $perfil
        );

        $this->assertStringNotContainsString(
            "plugins: ['remove_button']",
            $perfil
        );

        $this->assertStringNotContainsString(
            'ts-dropdown-especialidades',
            $this->extractScriptBlocks($perfil)
        );
    }

    public function test_professional_views_leave_tom_select_instances_to_reusable_modules(): void
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

            $this->assertStringNotContainsString(
                '.tomselect',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'new TomSelect',
                $contenido,
                $view
            );

            $this->assertStringNotContainsString(
                'window.TomSelect',
                $contenido,
                $view
            );
        }
    }

    private function extractScriptBlocks(
        string $contenido
    ): string {
        preg_match_all(
            '/<script\b[^>]*>(.*?)<\/script>/is',
            $contenido,
            $matches
        );

        return implode("\n", $matches[1] ?? []);
    }
}
