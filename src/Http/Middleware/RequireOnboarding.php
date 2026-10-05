<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects an authenticated subject with an unfinished flow to its first
 * incomplete required step (optional steps never block, so they are never
 * enforced), and passes through for guests and when the flow is complete,
 * absent, or that step has no resolvable redirect target. Without a flow key it uses the same flow as
 * `$user->onboarding()` / `Onboarding::for($user)`, so the resolver applies.
 */
final class RequireOnboarding
{
    public function handle(Request $request, Closure $next, ?string $key = null): Response
    {
        $subject = $request->user();

        if ($subject === null) {
            return $next($request);
        }

        $flow = $this->resolveFlow($subject, $key);

        if ($flow === null || $flow->isCompleted()) {
            return $next($request);
        }

        $step = $flow->requiredSteps()->first(fn (Step $step): bool => $step->isNotCompleted());

        if ($step === null || $this->requestIsAtStep($request, $step)) {
            return $next($request);
        }

        // A copy narrowed to that one step reuses the flow's redirect resolution.
        return (clone $flow)->of([$step])->redirectToCurrentStep() ?? $next($request);
    }

    private function resolveFlow(Authenticatable $subject, ?string $key): ?Flow
    {
        if (method_exists($subject, 'onboarding')) {
            return $subject->onboarding($key);
        }

        return Onboarding::for($subject, $key);
    }

    /**
     * Is the request already sitting on the step the flow would redirect it to?
     *
     * This mirrors Flow::redirectForStep()'s target precedence exactly (named
     * route, then url, then the free-form action), so every target the middleware
     * can redirect *to* is also a target it passes through *on*. URL targets are
     * built the way the redirect builds them (URL::to(), so an app served from a
     * sub-directory gets its base path) and compared by host and path only — a
     * query string or fragment on the target never sends the request round again.
     */
    private function requestIsAtStep(Request $request, Step $step): bool
    {
        if ($step->route !== null && Route::has($step->route)) {
            return $request->route()?->getName() === $step->route;
        }

        $target = $step->url ?? $step->action;

        if ($target === null) {
            return false;
        }

        if (Route::has($target)) {
            return $request->route()?->getName() === $target;
        }

        if (Str::startsWith($target, ['http://', 'https://', '/'])) {
            return $this->normalize($request->url()) === $this->normalize(URL::to($target));
        }

        return false;
    }

    /**
     * Host (with a non-default port) and path, without scheme, query, fragment or a
     * trailing slash.
     */
    private function normalize(string $url): string
    {
        $parts = parse_url($url) ?: [];

        $scheme = strtolower($parts['scheme'] ?? '');
        $port = $parts['port'] ?? null;

        if ($port === (['http' => 80, 'https' => 443][$scheme] ?? null)) {
            $port = null;
        }

        return strtolower($parts['host'] ?? '')
            .($port === null ? '' : ':'.$port)
            .'/'.trim(rawurldecode($parts['path'] ?? ''), '/');
    }
}
