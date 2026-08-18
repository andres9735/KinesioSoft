<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class GlobalConfirmationTest extends TestCase
{
    public function test_global_confirmation_handler_uses_the_supported_contract(): void
    {
        $layout = file_get_contents(
            resource_path('views/layouts/app.blade.php')
        );

        $this->assertIsString($layout);

        $this->assertStringContainsString(
            "document.querySelectorAll('.js-confirm-action')",
            $layout
        );

        $this->assertStringContainsString(
            "form.addEventListener('submit'",
            $layout
        );

        $this->assertStringContainsString(
            'form.dataset.confirmTitle',
            $layout
        );

        $this->assertStringContainsString(
            'form.dataset.confirmText',
            $layout
        );

        $this->assertStringContainsString(
            'form.dataset.confirmButton',
            $layout
        );

        $this->assertStringContainsString(
            'form.dataset.cancelButton',
            $layout
        );

        $this->assertStringContainsString(
            'form.dataset.confirmIcon',
            $layout
        );

        $this->assertStringContainsString(
            'form.submit();',
            $layout
        );
    }

    public function test_all_global_confirmation_hooks_use_the_form_contract(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                resource_path('views')
            )
        );

        $hookCount = 0;

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if (! str_ends_with(
                $file->getFilename(),
                '.blade.php'
            )) {
                continue;
            }

            $contenido = file_get_contents(
                $file->getPathname()
            );

            if (! is_string($contenido)) {
                continue;
            }

            $this->assertStringNotContainsString(
                'data-confirm-button-text=',
                $contenido,
                $file->getPathname()
            );

            $this->assertStringNotContainsString(
                'data-cancel-button-text=',
                $contenido,
                $file->getPathname()
            );

            $lineas = preg_split(
                '/\R/',
                $contenido
            );

            if (! is_array($lineas)) {
                continue;
            }

            foreach ($lineas as $indice => $linea) {
                if (! preg_match(
                    '/class="[^"]*\bjs-confirm-action\b[^"]*"/',
                    $linea
                )) {
                    continue;
                }

                $hookCount++;

                $inicio = max(
                    0,
                    $indice - 6
                );

                $finAtributos = $indice;

                while (
                    $finAtributos < count($lineas) &&
                    ! str_contains(
                        $lineas[$finAtributos],
                        '@csrf'
                    ) &&
                    $finAtributos <= $indice + 30
                ) {
                    $finAtributos++;
                }

                $this->assertLessThan(
                    count($lineas),
                    $finAtributos,
                    'No se encontró @csrf para el formulario de confirmación: ' .
                        $file->getPathname()
                );

                $this->assertStringContainsString(
                    '@csrf',
                    $lineas[$finAtributos],
                    'El contrato de confirmación debe definirse antes de @csrf: ' .
                        $file->getPathname()
                );

                $contextoAtributos = implode(
                    "\n",
                    array_slice(
                        $lineas,
                        $indice,
                        $finAtributos - $indice + 1
                    )
                );

                $contextoAnterior = implode(
                    "\n",
                    array_slice(
                        $lineas,
                        $inicio,
                        $indice - $inicio + 1
                    )
                );

                $this->assertStringContainsString(
                    '<form',
                    $contextoAnterior,
                    $file->getPathname()
                );

                $this->assertStringNotContainsString(
                    '<button',
                    $contextoAnterior,
                    'La clase js-confirm-action debe estar en el <form>, no en el <button>: ' .
                        $file->getPathname()
                );

                foreach (
                    [
                        'data-confirm-title=',
                        'data-confirm-text=',
                        'data-confirm-button=',
                        'data-cancel-button=',
                        'data-confirm-icon=',
                    ] as $atributo
                ) {
                    $this->assertStringContainsString(
                        $atributo,
                        $contextoAtributos,
                        $file->getPathname()
                    );
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $hookCount
        );
    }
}
