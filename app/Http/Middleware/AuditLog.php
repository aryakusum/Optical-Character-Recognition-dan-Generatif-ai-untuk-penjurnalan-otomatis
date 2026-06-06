<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    protected array $sensitiveRoutes = [
        'journals.verify-unit',
        'journals.verify-finance',
        'journals.reject',
        'journals.destroy',
        'journals.status',
        'journals.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            $routeName = $request->route()?->getName();
            $user = Auth::user();

            Log::channel('daily')->info('AUDIT', [
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_role' => $user?->role,
                'action' => $request->method(),
                'route' => $routeName,
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
                'status_code' => $response->getStatusCode(),
                'is_sensitive' => in_array($routeName, $this->sensitiveRoutes),
            ]);
        }

        return $response;
    }
}
