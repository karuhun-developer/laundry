<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerSessionIsCurrent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! $user->hasRole(RoleName::Customer->value)
            || ! $request->hasSession()
        ) {
            return $next($request);
        }

        $sessionVersion = $request->session()->get('customer_session_version');

        if (is_numeric($sessionVersion) && (int) $sessionVersion === $user->session_version) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Sesi telah berakhir. Silakan masuk kembali.'], 401);
        }

        return to_route('login');
    }
}
