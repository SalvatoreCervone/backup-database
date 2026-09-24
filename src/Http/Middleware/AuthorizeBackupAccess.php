<?php

namespace SalvatoreCervone\BackupDatabase\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeBackupAccess
{
    /**
     * Handle an incoming request.
     *
     * Checks the configured authorization gate. If no gate is configured,
     * the request passes through (relying on route middleware for auth).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('backup-database.authorization_gate');

        if ($gate && Gate::has($gate) && Gate::denies($gate)) {
            abort(403, 'Unauthorized access to backup operations.');
        }

        return $next($request);
    }
}
