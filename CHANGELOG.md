# Changelog

All notable changes to `onboarding-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Onboarding flows made of ordered steps, registered once through the `Onboarding` facade —
  inline, fluently or as reusable `Flow` classes — with no tables to migrate.
- Step completion and exclusion derived from your existing data with closures or declarative
  helpers such as `completeWhenFilled()`, `completeWhenTrue()` and `completeWhenHas()`.
- A `GetsOnboarded` model trait for the next step, completion percentage and resume position;
  flows fall back to the authenticated user.
- Several flows per app, with a resolver that picks the right flow for each subject.
- An `onboarding` route middleware that redirects unfinished, logged-in subjects to their
  current step (guests pass through), honours the resolver, and never loops on its own target.
- Named-route step targets with route parameters (`route($name, $parameters)`, an array or a
  closure over the subject).
- Optional steps, explicit ordering, stable step keys and sections (step groups) with
  per-section progress.
- Localizable titles and calls to action through Laravel's translator.
- JSON-ready `toArray()` output for your frontend.
- `StepCompleted` and `FlowCompleted` analytics events announced on demand with `record()`.
- An optional persistence seam (`OnboardingStore`: `isDismissed()`, `completedAt()`,
  `markDismissed()`) for dismissible steps and once-only events, configured with
  `Onboarding::useStore()` (or a container binding); `store()` / `hasStore()` report it.
- `Onboarding::for($user, ?$key)` returns the subject's flow, and `$user->dismissOnboardingStep()`
  dismisses an optional step; the injectable `OnboardingManager` serves the same API.
- `php artisan onboarding:list`, and `Onboarding::fake()` for your tests: it keeps your flows,
  seeds state (`seedCompleted()`, `seedDismissed()`), records dismissals and asserts them
  (`assertDismissed()`, `assertNotDismissed()`, `assertNothingDismissed()`) alongside the event
  assertions.

### Changed

- The facade root is `OnboardingManager` (was `Registry`); `OnboardingFake` extends it, so
  injected managers and the `GetsOnboarded` trait see the fake.
- `OnboardingStore` implementations must add `markDismissed()` (previously called only when
  present).

### Fixed

- A flow read for one subject is no longer rebound when another subject reads the same flow:
  `for()`, `resolveFor()` and the trait return a copy per subject.
