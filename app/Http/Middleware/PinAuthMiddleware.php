<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Proteksi endpoint publik (tanpa JWT) dengan kode akses / PIN.
 * PIN dikirim client via header `X-Access-Pin` dan dibandingkan dengan
 * config('heavy_equipment.access_pin').
 */
class PinAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('heavy_equipment.access_pin');
        $provided = (string) $request->header('X-Access-Pin', '');

        if ($expected === '' || !hash_equals($expected, $provided)) {
            abort(401, 'Kode akses tidak valid.');
        }

        return $next($request);
    }
}
