<?php

namespace Flute\Core\Router\Middlewares;

use Closure;
use Flute\Core\Support\BaseMiddleware;
use Flute\Core\Support\FluteRequest;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMiddleware extends BaseMiddleware
{
    public function handle(FluteRequest $request, Closure $next, ...$args): Response
    {
        if (!is_installed()) {
            return $next($request);
        }

        $path = $request->getPathInfo();

        if ($path === '/login' || $path === '/live' || str_starts_with($path, '/social/')) {
            return $next($request);
        }

        if (!config('app.maintenance_mode')) {
            return $next($request);
        }

        if ($request->hasAuthenticationCookie()) {
            if (user()->can('admin')) {
                return $next($request);
            }

            $allowedRoles = config('app.maintenance_allowed_roles', []);
            if (!empty($allowedRoles) && user()->isLoggedIn()) {
                foreach ($allowedRoles as $roleId) {
                    foreach (user()->getCurrentUser()->roles as $role) {
                        if ((int) $role->id === (int) $roleId) {
                            return $next($request);
                        }
                    }
                }
            }
        }

        if ($request->expectsJson() || $request->isAjax()) {
            return json([
                'error' => config('app.maintenance_message')
                    ? __(config('app.maintenance_message'))
                    : __('def.maintenance_mode'),
            ], 503);
        }

        $title = config('app.maintenance_title') ? __(config('app.maintenance_title')) : __('def.maintenance_mode');

        $message = config('app.maintenance_message') ? __(config('app.maintenance_message')) : '';

        $showTimer = (bool) config('app.maintenance_show_timer', false);
        $endTime = config('app.maintenance_end_time', '');

        return response()->view(
            'flute::pages.maintenance',
            [
                'title' => $title,
                'message' => $message,
                'showTimer' => $showTimer,
                'endTime' => $endTime,
            ],
            503,
        );
    }
}
