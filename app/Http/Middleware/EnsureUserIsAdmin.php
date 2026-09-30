<?php

namespace App\Http\Middleware;

use App\Enums\AdminRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only active admin accounts may pass. Optionally restrict to roles:
 *   ->middleware('admin:super_admin,manager')
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless($user->isAdmin() && $user->isActive() && $user->admin !== null, 403);

        if ($roles !== []) {
            abort_unless($user->hasAdminRole(...array_map(AdminRole::from(...), $roles)), 403);
        }

        return $next($request);
    }
}
