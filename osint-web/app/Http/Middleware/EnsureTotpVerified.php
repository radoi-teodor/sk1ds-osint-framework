<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTotpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->hasTotpEnabled() && ! $request->session()->get('totp_verified')) {
            return redirect('/auth/totp-challenge');
        }
        return $next($request);
    }
}
