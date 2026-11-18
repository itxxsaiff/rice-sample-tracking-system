<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Upload bigger than the server allows (post_max_size): explain instead of erroring.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, Request $request) {
            $message = 'The upload was too large. Please send fewer photos at a time.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 413)
                : back()->with('error', $message);
        });

        // Expired form (CSRF token) after leaving a page open for a long time.
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, Request $request) {
            if (! $request->expectsJson()) {
                return back()->withInput($request->except(['password', 'password_confirmation', 'current_password', '_token']))
                    ->with('warning', 'The page had expired. Please check the form and save again.');
            }
        });
    })->create();
