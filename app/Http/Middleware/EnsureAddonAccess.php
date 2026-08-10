<?php

namespace App\Http\Middleware;

use App\Tenancy\Addon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Penjaga LAYAR untuk modul yang dibeli sebagai add-on.
 *
 * Dipasang berdampingan dengan `plan:<modul>`: `plan` memastikan fiturnya hidup
 * untuk toko itu, middleware ini memastikan ORANG yang membukanya memang
 * termasuk peran yang disepakati saat add-on dibeli.
 *
 * Pemakaian: ->middleware(['plan:inventory_hpp', 'addon:inventory_hpp'])
 */
class EnsureAddonAccess
{
    public function handle(Request $request, Closure $next, string $module)
    {
        $user = Auth::user();

        if ($user && $user->isSuperadmin()) {
            return $next($request);
        }

        if (! Addon::bolehLihat($user?->tenant, $module, $user)) {
            $pesan = 'Menu ini dibuka sebagai fitur tambahan dan hanya bisa diakses oleh peran '
                . 'yang ditetapkan saat pembeliannya. Hubungi pemilik toko bila Anda perlu aksesnya.';

            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $pesan], 403);
            }

            abort(403, $pesan);
        }

        return $next($request);
    }
}
