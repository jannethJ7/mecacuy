<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Verifica que el usuario autenticado tenga al menos uno de los roles permitidos.
     *
     * Uso en rutas:
     *   ->middleware('role:admin')
     *   ->middleware('role:admin,operador')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $userRole = strtolower((string) ($request->user()?->rol ?? ''));

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => strtolower(trim($role)))
            ->filter()
            ->values()
            ->all();

        abort_unless(
            $userRole !== '' && in_array($userRole, $allowedRoles, true),
            403,
            'No tienes permisos para acceder a esta sección.'
        );

        return $next($request);
    }
}
