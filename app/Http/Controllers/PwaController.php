<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/** Service worker and offline shell for the installable / offline app. */
class PwaController extends Controller
{
    public function serviceWorker(): Response
    {
        $manifestPath = public_path('build/manifest.json');
        $manifest = File::exists($manifestPath) ? json_decode(File::get($manifestPath), true) : [];
        $source = File::get(resource_path('pwa/sw.js'));

        // Everything Vite built (hashed file names), except legacy .woff fonts every supported browser skips.
        $assets = collect(File::exists(public_path('build/assets')) ? File::files(public_path('build/assets')) : [])
            ->map(fn ($file) => '/build/assets/'.$file->getFilename())
            ->reject(fn ($path) => str_ends_with($path, '.woff'))
            ->sort()->values();

        $precache = $assets->merge([
            '/offline-shell', '/images/logo-dark.png', '/icon-192.png', '/icon-512.png', '/apple-touch-icon.png', '/favicon-32.png', '/site.webmanifest',
        ])->unique()->values();

        // Changes whenever the build, the worker or the shell changes.
        $version = substr(md5(json_encode($manifest).$source.File::get(resource_path('views/offline/shell.blade.php'))), 0, 12);

        $js = str_replace(['__VERSION__', '__PRECACHE__'], [$version, $precache->toJson(JSON_UNESCAPED_SLASHES)], $source);

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /** Anonymous app shell used when the server is unreachable. Contains no user data. */
    public function shell(): Response
    {
        // Business vocabulary only (config/rice.php) — no user data.
        $config = [
            'sample_units' => config('rice.sample_units'),
            'sample_default' => config('rice.sample_default'),
            'quantity_units' => config('rice.quantity_units'),
            'rate_units' => config('rice.rate_units'),
            'packing_types' => config('rice.packing_types'),
            'cities' => config('rice.cities'),
            'max_photos' => config('rice.max_photos_per_upload'),
            'max_days_without_server' => config('offline.max_days_without_server'),
            'prefixes' => config('rice.prefixes'),
            'unit_kg' => config('rice.unit_kg'),
            'sample_number_start' => config('rice.number_start.sample'),
        ];

        return response()->view('offline.shell', compact('config'))->header('Cache-Control', 'no-cache');
    }
}
