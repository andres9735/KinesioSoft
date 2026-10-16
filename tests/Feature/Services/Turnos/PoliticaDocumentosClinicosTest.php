<?php

namespace Tests\Feature\Services\Turnos;

use App\Services\Turnos\PoliticaDocumentosClinicosService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PoliticaDocumentosClinicosTest extends TestCase
{
    /** @var list<string> */
    private array $temporales = [];

    private PoliticaDocumentosClinicosService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PoliticaDocumentosClinicosService::class);
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->temporales as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_configuration_preserves_the_approved_policy_parameters(): void
    {
        $this->assertSame([
            'max_archivos' => 5,
            'max_bytes_por_archivo' => 10485760,
            'max_bytes_total' => 26214400,
            'mime_permitidos' => ['application/pdf', 'image/jpeg', 'image/png'],
        ], array_intersect_key(config('documentos_clinicos'), array_flip([
            'max_archivos', 'max_bytes_por_archivo', 'max_bytes_total', 'mime_permitidos',
        ])));
    }

    #[DataProvider('cantidades')]
    public function test_optional_attachments_and_count_limit(int $cantidad, array $errores): void
    {
        $archivos = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $archivos[] = $this->pdf();
        }

        $this->assertSame(['coleccion' => $errores, 'archivos' => []], $this->service->validar($archivos));
    }

    public static function cantidades(): array
    {
        return [
            'opcionales' => [0, []],
            'cinco permitidos' => [5, []],
            'seis rechazados' => [6, ['cantidad_excedida']],
        ];
    }

    #[DataProvider('formatos')]
    public function test_allowed_formats_are_detected_from_real_content(string $nombre, string $mime): void
    {
        $contenido = match ($mime) {
            'application/pdf' => "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n",
            'image/png' => base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jhL8AAAAASUVORK5CYII=', true),
            'image/jpeg' => base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCfAAv/2Q==', true),
        };
        $archivo = $this->archivo($contenido, $nombre, 'application/octet-stream');

        $this->assertSame($mime, $archivo->getMimeType());
        $this->assertSame(strlen($contenido), $archivo->getSize());
        $this->assertValido([$archivo]);
    }

    public static function formatos(): array
    {
        return [
            'PDF' => ['informe.pdf', 'application/pdf'],
            'JPG' => ['estudio.jpg', 'image/jpeg'],
            'JPEG' => ['estudio.jpeg', 'image/jpeg'],
            'PNG' => ['estudio.png', 'image/png'],
        ];
    }

    #[DataProvider('extensionesFalsas')]
    public function test_allowed_names_and_spoofed_client_mime_do_not_authorize_plain_text(string $nombre, string $mimeCliente): void
    {
        $archivo = $this->archivo('Esto es texto plano, no es un documento permitido.', $nombre, $mimeCliente);

        $this->assertSame($mimeCliente, $archivo->getClientMimeType());
        $this->assertSame('text/plain', $archivo->getMimeType());
        $this->assertSame([
            'coleccion' => [],
            'archivos' => [0 => ['mime_no_permitido']],
        ], $this->service->validar([$archivo]));
    }

    public static function extensionesFalsas(): array
    {
        return [
            'pdf MIME falsificado' => ['informe.pdf', 'application/pdf'],
            'pdf mismo contenido otro MIME cliente' => ['informe.pdf', 'text/plain'],
            'jpg' => ['estudio.jpg', 'image/jpeg'],
            'jpeg' => ['estudio.jpeg', 'image/jpeg'],
            'png' => ['estudio.png', 'image/png'],
        ];
    }

    public function test_allowed_content_does_not_require_a_matching_name_or_client_mime(): void
    {
        $archivo = $this->pdf(nombre: 'nombre.txt', mimeCliente: 'text/plain');

        $this->assertSame('application/pdf', $archivo->getMimeType());
        $this->assertValido([$archivo]);
    }

    #[DataProvider('excesosEnBytes')]
    public function test_individual_limit_uses_exact_physical_bytes(int $exceso): void
    {
        $bytes = config('documentos_clinicos.max_bytes_por_archivo') + $exceso;
        $archivo = $this->pdf($bytes);

        $this->assertSame($bytes, $archivo->getSize());
        $this->assertSame('application/pdf', $archivo->getMimeType());
        $this->assertSame([
            'coleccion' => [],
            'archivos' => $exceso === 0 ? [] : [0 => ['tamanio_individual_excedido']],
        ], $this->service->validar([$archivo]));
    }

    public static function excesosEnBytes(): array
    {
        return ['limite exacto' => [0], 'un byte superior' => [1]];
    }

    #[DataProvider('excesosEnBytes')]
    public function test_total_limit_uses_exact_physical_bytes_without_exceeding_individual_limit(int $exceso): void
    {
        $individual = config('documentos_clinicos.max_bytes_por_archivo');
        $total = config('documentos_clinicos.max_bytes_total') + $exceso;
        $archivos = [$this->pdf($individual), $this->pdf($individual), $this->pdf($total - 2 * $individual)];

        foreach ($archivos as $archivo) {
            $this->assertLessThanOrEqual($individual, $archivo->getSize());
            $this->assertSame('application/pdf', $archivo->getMimeType());
        }
        $this->assertSame($total, array_sum(array_map(fn (UploadedFile $archivo) => $archivo->getSize(), $archivos)));
        $this->assertSame([
            'coleccion' => $exceso === 0 ? [] : ['tamanio_total_excedido'],
            'archivos' => [],
        ], $this->service->validar($archivos));
    }

    public function test_partial_upload_is_rejected_at_its_original_index(): void
    {
        $valido = $this->pdf();
        $fallido = new UploadedFile($valido->getPathname(), 'informe.pdf', 'application/pdf', UPLOAD_ERR_PARTIAL, true);

        $this->assertSame(UPLOAD_ERR_PARTIAL, $fallido->getError());
        $this->assertFalse($fallido->isValid());
        $this->assertSame([
            'coleccion' => [],
            'archivos' => [7 => ['upload_invalido']],
        ], $this->service->validar([3 => $valido, 7 => $fallido]));
    }

    public function test_failed_upload_is_not_asked_for_size_or_mime(): void
    {
        $fallido = $this->createMock(UploadedFile::class);
        $fallido->method('isValid')->willReturn(false);
        $fallido->expects($this->never())->method('getSize');
        $fallido->expects($this->never())->method('getMimeType');

        $this->assertSame([
            'coleccion' => [], 'archivos' => [0 => ['upload_invalido']],
        ], $this->service->validar([$fallido]));
    }

    public function test_strings_paths_and_arbitrary_objects_are_rejected_without_being_opened(): void
    {
        $path = $this->pdf()->getPathname();
        $objeto = new class
        {
            public function __toString(): string
            {
                throw new \LogicException('No debe interpretarse como una ruta.');
            }
        };

        $this->assertSame([
            'coleccion' => [],
            'archivos' => [2 => ['tipo_invalido'], 5 => ['tipo_invalido'], 9 => ['tipo_invalido']],
        ], $this->service->validar([2 => 'texto', 5 => $path, 9 => $objeto]));
    }

    public function test_unreadable_temporary_file_is_rejected_instead_of_counting_as_zero_bytes(): void
    {
        $archivo = $this->pdf();
        unlink($archivo->getPathname());
        clearstatcache();

        $this->assertSame([
            'coleccion' => [],
            'archivos' => [4 => ['tamanio_ilegible', 'mime_ilegible']],
        ], $this->service->validar([4 => $archivo]));
    }

    public function test_unusable_size_is_rejected_even_with_an_allowed_mime(): void
    {
        $archivo = $this->createMock(UploadedFile::class);
        $archivo->method('isValid')->willReturn(true);
        $archivo->method('getSize')->willReturn(false);
        $archivo->method('getMimeType')->willReturn('application/pdf');

        $this->assertSame([
            'coleccion' => [], 'archivos' => [0 => ['tamanio_ilegible']],
        ], $this->service->validar([$archivo]));
    }

    public function test_multiple_file_and_collection_errors_accumulate_without_truncation_or_reindexing(): void
    {
        $archivos = [
            1 => $this->pdf(config('documentos_clinicos.max_bytes_por_archivo') + 1),
            3 => $this->pdf(),
            5 => $this->pdf(),
            7 => $this->pdf(),
            9 => $this->pdf(),
            11 => $this->archivo('Contenido de texto plano no permitido.', 'falso.png', 'image/png'),
        ];

        $this->assertSame([
            'coleccion' => ['cantidad_excedida'],
            'archivos' => [1 => ['tamanio_individual_excedido'], 11 => ['mime_no_permitido']],
        ], $this->service->validar($archivos));
    }

    public function test_service_consumes_the_central_configuration(): void
    {
        config(['documentos_clinicos' => [
            'max_archivos' => 1,
            'max_bytes_por_archivo' => 100,
            'max_bytes_total' => 150,
            'mime_permitidos' => ['image/png'],
        ]]);

        $this->assertSame([
            'coleccion' => ['cantidad_excedida', 'tamanio_total_excedido'],
            'archivos' => [0 => ['tamanio_individual_excedido', 'mime_no_permitido'], 1 => ['mime_no_permitido']],
        ], $this->service->validar([$this->pdf(101), $this->pdf(100)]));
    }

    private function assertValido(array $archivos): void
    {
        $this->assertSame(['coleccion' => [], 'archivos' => []], $this->service->validar($archivos));
    }

    private function pdf(?int $bytes = null, string $nombre = 'informe.pdf', string $mimeCliente = 'application/pdf'): UploadedFile
    {
        return $this->archivo("%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n", $nombre, $mimeCliente, $bytes);
    }

    private function archivo(string $contenido, string $nombre, string $mimeCliente, ?int $bytes = null): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'ks-politica-');
        $this->temporales[] = $ruta;
        $handle = fopen($ruta, 'wb');
        try {
            fwrite($handle, $contenido);
            if ($bytes !== null) {
                ftruncate($handle, $bytes);
            }
        } finally {
            fclose($handle);
        }

        return new UploadedFile($ruta, $nombre, $mimeCliente, UPLOAD_ERR_OK, true);
    }
}
