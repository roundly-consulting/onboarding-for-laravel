# Changelog

All notable changes to `onboarding-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Onboarding flows made of ordered steps, registered once through the `Onboarding` facade —
  inline, fluently or as reusable `Flow` classes — with no tables to migrate.
- Step completion and exclusion derived from your existing data with closures or declarative
  helpers such as `completeWhenFilled()`, `completeWhenTrue()` and `completeWhenHas()`.
- A `GetsOnboarded` model trait for the next step, completion percentage and resume position;
  flows fall back to the authenticated user.
- Several flows per app, with a resolver that picks the right flow for each subject.
- An `onboarding` route middleware that redirects unfinished subjects to their current step.
- Optional steps, explicit ordering, stable step keys and sections (step groups) with
  per-section progress.
- Localizable titles and calls to action through Laravel's translator.
- JSON-ready `toArray()` output for your frontend.
- `StepCompleted` and `FlowCompleted` analytics events announced on demand with `record()`.
- An optional persistence seam (`OnboardingStore`) for dismissible steps and once-only events.
- `php artisan onboarding:list` and `Onboarding::fake()` with assertions for your tests.
