<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use RoundlyConsulting\Onboarding\Step;
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

        if ($this->requestIsTarget($request, $flow)) {
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

    /**
     * Is the request already sitting on the step the flow would redirect it to?
     *
     * This mirrors Flow::redirectForStep()'s target precedence exactly (named
     * route, then url, then the free-form action), so every target the middleware
     * can redirect *to* is also a target it passes through *on*. Checking only
     * the named route sent a step declaring a `url()` target into an infinite
     * redirect loop as soon as that URL sat inside the guarded group.
     */
    private function requestIsTarget(Request $request, Flow $flow): bool
    {
        $step = $flow->currentStep();

        if ($step !== null && $step->route !== null && Route::has($step->route)) {
            return $request->route()?->getName() === $step->route;
        }

        return $step !== null && $this->requestIsAtTarget($request, $step);
    }

    private function requestIsAtTarget(Request $request, Step $step): bool
    {
        $target = $step->url ?? $step->action;

        if ($target === null) {
            return false;
        }

        if (Route::has($target)) {
            return $request->route()?->getName() === $target;
        }

        if (Str::startsWith($target, ['http://', 'https://'])) {
            return $this->normalize($request->url()) === $this->normalize($target);
        }

        if (Str::startsWith($target, '/')) {
            return $this->normalize($request->url()) === $this->normalize($request->getSchemeAndHttpHost().$target);
        }

        return false;
    }

    /**
     * Compare URLs without a trailing slash; the query string is already absent
     * from Request::url().
     */
    private function normalize(string $url): string
    {
        return rtrim($url, '/');
    }
}
