<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Behind nginx -> Octane (RoadRunner) reverse proxy on localhost.
        // Trust forwarded headers so HTTPS scheme / client IP are detected correctly.
        $middleware->trustProxies(at: '*', headers:
            Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'forbid-banned-user' => \Cog\Laravel\Ban\Http\Middleware\ForbidBannedUser::class,
            // Multi-tenant & langganan
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'subscribed' => \App\Http\Middleware\EnsureSubscribed::class,
            'plan' => \App\Http\Middleware\EnsurePlanFeature::class,
            // Penjaga peran untuk modul yang dibeli terpisah (add-on).
            'addon' => \App\Http\Middleware\EnsureAddonAccess::class,
            'maintenance' => \App\Http\Middleware\MaintenanceMode::class,
            // Multi-vertical (F&B / Laundry / Retail)
            'vertical' => \App\Http\Middleware\EnsureVertical::class,
        ]);

        // 🔥 TAMBAHKAN BARIS INI (Agar logoutOtherDevices berfungsi)
        // + Identifikasi tenant aktif (setelah session/auth siap) + security headers global
        // DynamicUrlRoot harus jalan PALING AWAL (prepend): kalau di-append, ia berjalan
        // SETELAH VerifyCsrfToken, sehingga saat token kedaluwarsa (419) URL masih terkunci
        // ke APP_URL (mooda.id) dan tombol di halaman error melompat keluar subdomain.
        $middleware->web(prepend: [
            \App\Http\Middleware\DynamicUrlRoot::class,
        ]);

        $middleware->web(append: [
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\ResolveVertical::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Kecualikan webhook langganan (billing SaaS) dari CSRF.
        // (Webhook Midtrans untuk order POS sudah dihapus — POS tidak lagi memakai payment gateway.)
        $middleware->validateCsrfTokens(except: [
            'api/subscription-webhook',
            'api/doku-webhook',
            'api/tripay-webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Link aktivasi kedaluwarsa / tanda tangan tak valid -> halaman "kirim ulang".
        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, $request) {
            if ($request->routeIs('verification.verify')) {
                return redirect()->route('verification.notice')->with('link_expired', true);
            }
        });
    })->create();
