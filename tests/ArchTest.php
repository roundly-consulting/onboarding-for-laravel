<?php

declare(strict_types=1);

use RoundlyConsulting\Onboarding\Exceptions\OnboardingException;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use RoundlyConsulting\Onboarding\Step;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Onboarding shipped exactly one arch rule (the debug-leftover ban), so six of the seven
 * presets here are new guards rather than replacements.
 */
ArchPresets::strictTypes('RoundlyConsulting\Onboarding');

/**
 * Exempt from finality, each deliberately:
 *  - Flow / Step — the package's two builder types. A host subclasses Flow to declare its own
 *    onboarding (tests/CustomFlow.php does exactly that, which is the documented usage), and
 *    Step is extended alongside it.
 *  - OnboardingException — the base every onboarding error extends, so a host can catch them
 *    uniformly.
 *  - Registry — the Onboarding facade's accessor, resolved from the container as a singleton.
 *    A host can rebind it to its own subclass, so `final` would close a real (if undocumented)
 *    seam.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Onboarding', [
    Flow::class,
    Step::class,
    Registry::class,
    OnboardingException::class,
]);

/**
 * `swappableModelsAreNotFinal` is NOT adopted, and this is structural rather than a
 * preference: onboarding ships no config file at all, so there is no `*_model` key inviting a
 * swap and no Eloquent model to pin. The preset takes a model => config-key map and there is
 * no honest entry to put in it.
 */

/**
 * Onboarding does no cryptography; the ban is a standing guard against a step token or a
 * resume link being hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Onboarding');

/**
 * `modelsResolveThroughSeam` is NOT adopted, on the pre-classified rule (Swap? = 0). Onboarding
 * ships no Eloquent model and no `*_model` key, so both halves of the preset are structurally
 * inert: there is no swap literal to keep inside a seam, and no `static::query()` to ban. This
 * is jwt's case exactly, and the same reasoning — not a per-row re-litigation.
 */

/**
 * The Dependency Policy as a test. No `alsoAllow`: onboarding's `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red the graph is wrong —
 * never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * Replaces the hand-written ban, which covered dd/dump/ray only — the preset also catches
 * var_dump and print_r.
 */
ArchPresets::noDebuggingLeftovers();
