<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('proofread.dashboard.enabled', true)) {
            abort(404);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
