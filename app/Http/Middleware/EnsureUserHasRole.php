<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Akses ditolak untuk role Anda.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
