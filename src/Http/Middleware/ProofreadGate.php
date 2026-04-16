<?php

declare(strict_types=1);

namespace Mosaiqo\Proofread\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProofreadGate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Gate::check('viewEvals', [$request->user()])) {
            abort(403);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
