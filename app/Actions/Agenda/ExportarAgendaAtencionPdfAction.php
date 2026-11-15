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

        $pdf = Pdf::setOptions([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'allowedProtocols' => ['file://'], 'defaultMediaType' => 'print',
            'tempDir' => $temporal, 'fontCache' => $temporal,
            'fontDir' => base_path('vendor/dompdf/dompdf/lib/fonts'),
            'chroot' => [$temporal, base_path('vendor/dompdf/dompdf/lib/fonts')],
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('turnos.agenda-atencion.exportacion', $datos)
            ->setPaper('a4', 'landscape');
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fuente = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(34, $canvas->get_height() - 26, 'KinesioSoft · Agenda de atención', $fuente, 8, [0.4, 0.45, 0.5]);
        $canvas->page_text($canvas->get_width() - 120, $canvas->get_height() - 26, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fuente, 8, [0.4, 0.45, 0.5]);

        return $pdf->output();
    }
}
