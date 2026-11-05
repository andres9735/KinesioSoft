<?php

namespace App\Actions\Agenda;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

final class ExportarAgendaAtencionPdfAction
{
    /** Recibe exclusivamente la representación administrativa ya autorizada. */
    public function ejecutar(array $datos): string
    {
        $temporal = storage_path('framework/cache/agenda-pdf');
        File::ensureDirectoryExists($temporal);

        return Pdf::setOptions([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'allowedProtocols' => ['file://'], 'defaultMediaType' => 'print',
            'tempDir' => $temporal, 'fontCache' => $temporal,
            'fontDir' => base_path('vendor/dompdf/dompdf/lib/fonts'),
            'chroot' => [$temporal, base_path('vendor/dompdf/dompdf/lib/fonts')],
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('turnos.agenda-atencion.exportacion', $datos)
            ->setPaper('a4', 'landscape')->output();
    }
}
