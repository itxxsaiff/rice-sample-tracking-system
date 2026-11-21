<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a Blade view (extending pdf.layout) to an A4 PDF download with
 * the company letterhead and a "page X of Y" footer on every page.
 */
final class PdfReport
{
    public const COMPANY = 'Ukasha Pacific International';

    /** Rows beyond this are left out of one PDF (the document says so). */
    public const MAX_ROWS = 3000;

    public static function download(string $view, array $data, string $title, string $filename): Response
    {
        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::loadView($view, $data + [
            'title' => $title,
            'company' => self::COMPANY,
            'logo' => self::logo(),
            'generatedAt' => now(),
            'generatedBy' => auth()->user()?->name,
        ])->setPaper('a4');

        $dompdf = $pdf->getDomPDF();
        $dompdf->addInfo('Title', $title);
        $dompdf->addInfo('Author', self::COMPANY);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('Helvetica');
        $grey = [0.45, 0.48, 0.53];
        $y = $canvas->get_height() - 28;
        $left = 32; // matches the @page side margins in pdf.layout
        $right = $canvas->get_width() - 32;

        $footer = self::COMPANY.'  ·  '.$title;
        $canvas->page_script(function (int $page, int $pages, $canvas, $metrics) use ($footer, $font, $grey, $y, $left, $right) {
            $label = "Page {$page} of {$pages}";
            $canvas->text($left, $y, $footer, $font, 7.5, $grey);
            $canvas->text($right - $metrics->getTextWidth($label, $font, 7.5), $y, $label, $font, 7.5, $grey);
        });

        $name = Str::slug($filename).'-'.now()->format('Y-m-d').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function logo(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode(File::get(resource_path('pdf/logo.jpg')));
    }
}
