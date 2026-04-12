<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If no users exist yet, send every request to /setup.
 * Once a user exists, never let anyone hit /setup again.
 */
class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $hasUsers = User::query()->exists();
        $path = $request->path();

        $allowedWhileEmpty = ['setup', 'setup/create', 'up'];

        if (str_starts_with($path, 'docs') || str_starts_with($path, 'auth/totp')) {
            return $next($request);
        }

        if (! $hasUsers && ! in_array($path, $allowedWhileEmpty, true)) {
            return redirect('/setup');
        }

        if ($hasUsers && str_starts_with($path, 'setup')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
