<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

/**
 * The secret-safe `about` capture (A). Onboarding had NO about test at all, so this is a new
 * guard, not a replacement.
 *
 * Purchases #13 is the bug it exists for: the fleet's most credential-heavy `about` section was
 * guarded by negative assertions against `app(Kernel::class)->output()`, which returns `''`.
 * Every "does not leak" check was vacuous — passing against empty output. This capture goes
 * through `Artisan::call('about', …)` + `Artisan::output()` and runs in order: output non-empty,
 * every `mustRender` string present, and only THEN no secret rendered.
 *
 * The provider's own docblock states the policy this pins: "Report the shape of the registered
 * onboarding, never its content: a flow key names a host's own business process ('kyc',
 * 'series-c-payout') and a step carries the host's copy, routes and URLs." That is a real
 * secret — a flow key leaks what a company is doing, and a step CTA leaks its internal routes.
 * Nothing enforced it until now.
 */
it('renders the onboarding section without leaking the host business process it describes', function (): void {
    Onboarding::register('series-c-payout', Flow::make('Series C payout')->of([
        Step::make(title: 'Verify cap table', cta: 'https://admin.acme-internal.example/cap-table'),
        Step::make(title: 'Sign SAFE note', cta: 'https://admin.acme-internal.example/safe'),
    ]));

    expect('onboarding')->toLeakNoSecrets(
        secrets: [
            // A flow key names the host's own business process. Reported by count only.
            'series-c-payout',
            // Step titles carry the host's copy, and CTAs are its internal routes.
            'Verify cap table',
            'Sign SAFE note',
            'admin.acme-internal.example',
        ],
        mustRender: [
            'Flows',
            'Steps',
            'Default flow',
            'Persistence store',
            'Middleware alias',
            // The positive proof that the flow and step lines REPORT rather than sit silently
            // empty — which is what makes hiding the keys meaningful rather than accidental.
            '1 registered',
            '2 declared',
        ],
    );
});

/**
 * The subtler half of the provider's stated policy: `about` must not evaluate a step's
 * completion closure. Those closures run against the current subject and can hit the database,
 * an API, or a host's auth state — `about` has no business doing any of that. The provider
 * deliberately counts `$flow->steps` rather than calling `count()`/`percentageCompleted()`,
 * and its comment says so, but nothing pinned it, so a future edit could undo it silently.
 */
it('never evaluates a step completion closure while rendering', function (): void {
    $evaluated = false;

    Onboarding::register('kyc', Flow::make('KYC')->of([
        Step::make(title: 'ID check', complete: function () use (&$evaluated): bool {
            $evaluated = true;

            return true;
        }),
    ]));

    expect('onboarding')->toLeakNoSecrets(
        secrets: ['kyc', 'ID check'],
        mustRender: ['Flows', '1 registered', 'Steps', '1 declared'],
    );

    expect($evaluated)->toBeFalse();
});

/**
 * The stateless default: with nothing registered, the section must say so rather than render
 * nothing. An empty registry is the state a fresh host is in, and it is exactly the state a
 * negative-only leak check would pass vacuously against.
 */
it('reports an empty registry honestly', function (): void {
    expect('onboarding')->toLeakNoSecrets(
        secrets: [],
        mustRender: ['Flows', 'NONE', 'Persistence store', 'NONE (stateless)'],
    );
});
