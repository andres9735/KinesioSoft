<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class GlobalFlashMessageTest extends TestCase
{
    public function test_app_layout_handles_global_flash_message_categories(): void
    {
        $layout = file_get_contents(
            resource_path('views/layouts/app.blade.php')
        );

        $this->assertIsString($layout);

        foreach (
            [
                'success' => ['success', 'Éxito'],
                'error' => ['error', 'Error'],
                'warning' => ['warning', 'Atención'],
                'info' => ['info', 'Información'],
            ] as $flash => [$icono, $titulo]
        ) {
            $this->assertStringContainsString(
                "session('{$flash}')",
                $layout
            );

            $this->assertStringContainsString(
                "icon: '{$icono}'",
                $layout
            );

            $this->assertStringContainsString(
                "title: '{$titulo}'",
                $layout
            );
        }

        $this->assertStringContainsString(
            "confirmButtonText: 'Aceptar'",
            $layout
        );
    }

    public function test_feature_views_do_not_render_global_flash_messages_locally(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                resource_path('views')
            )
        );

        $layoutGlobal = realpath(
            resource_path('views/layouts/app.blade.php')
        );

        $this->assertNotFalse($layoutGlobal);

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

            if ($file->getRealPath() === $layoutGlobal) {
                continue;
            }

            $contenido = file_get_contents(
                $file->getPathname()
            );

            if (! is_string($contenido)) {
                continue;
            }

            foreach (
                [
                    'success',
                    'warning',
                    'error',
                    'info',
                ] as $flash
            ) {
                $patron = sprintf(
                    '/session\s*\(\s*[\'\"]%s[\'\"]\s*\)/',
                    preg_quote($flash, '/')
                );

                $this->assertDoesNotMatchRegularExpression(
                    $patron,
                    $contenido,
                    'Los flashes globales deben renderizarse únicamente en layouts/app.blade.php: ' .
                        $file->getPathname()
                );
            }
        }
    }
}
