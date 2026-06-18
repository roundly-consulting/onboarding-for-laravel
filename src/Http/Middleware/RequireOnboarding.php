<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects an authenticated subject with an unfinished flow to their current
 * step, and passes through when the flow is complete, absent, or has no
 * resolvable redirect target.
 */
final class RequireOnboarding
{
    public function handle(Request $request, Closure $next, ?string $key = null): Response
    {
        $flow = $this->resolveFlow($request, $key);

        if ($flow === null || $flow->isCompleted()) {
            return $next($request);
        }

        if ($this->currentRouteIsTarget($request, $flow)) {
            return $next($request);
        }

        return $flow->redirectToCurrentStep() ?? $next($request);
    }

    private function resolveFlow(Request $request, ?string $key): ?Flow
    {
        $subject = $request->user();

        if (is_object($subject) && method_exists($subject, 'onboarding')) {
            return $subject->onboarding($key);
        }

        return Onboarding::find($key ?? Registry::$default)?->for($subject);
    }

    private function currentRouteIsTarget(Request $request, Flow $flow): bool
    {
        $step = $flow->currentStep();

        if ($step === null || $step->route === null) {
            return false;
        }

        return $request->route()?->getName() === $step->route;
    }
}
