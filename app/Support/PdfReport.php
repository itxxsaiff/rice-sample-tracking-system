<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a Blade view (extending pdf.layout) to an A4 PDF download with
 * the company letterhead and a "page X of Y" footer on every page.
 */
final class PdfReport
{
    public const COMPANY = 'Ukasha Pacific International (SMC-Pvt) Ltd.';

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

    /** "01 Nov 2026 – 21 Nov 2026", "from …", "up to …" or "All dates", for the line describing a printed list. */
    public static function period(?string $from, ?string $to): string
    {
        $date = fn (string $d) => rescue(fn () => Carbon::parse($d)->format('d M Y'), $d, false);

        return match (true) {
            filled($from) && filled($to) => $date($from).' – '.$date($to),
            filled($from) => 'from '.$date($from),
            filled($to) => 'up to '.$date($to),
            default => 'All dates',
        };
    }

    /**
     * A loading photo as a small JPEG data URI for the PDF (keeps the file
     * light). Without GD, JPEG photos are embedded as they are; others are skipped.
     */
    public static function photo(?string $path, int $width = 420): ?string
    {
        if (! $path || ! Storage::disk(PhotoStorage::DISK)->exists($path)) {
            return null;
        }
        $bytes = Storage::disk(PhotoStorage::DISK)->get($path);

        if (function_exists('imagecreatefromstring') && ($image = @imagecreatefromstring($bytes))) {
            $scale = min(1, $width / max(1, imagesx($image)));
            $thumb = imagescale($image, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
            ob_start();
            imagejpeg($thumb ?: $image, null, 72);

            return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
        }

        return str_starts_with($bytes, "\xFF\xD8") ? 'data:image/jpeg;base64,'.base64_encode($bytes) : null;
    }

    private static function logo(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode(File::get(resource_path('pdf/logo.jpg')));
    }
}
