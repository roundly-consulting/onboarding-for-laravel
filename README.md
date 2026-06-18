# Onboarding for Laravel

Define and track multiple onboarding flows for your Laravel application.

This package lets you describe onboarding as a set of **flows**, each made of ordered
**steps**. Steps decide whether they are completed (or should be excluded) for a given model
through closures, so progress is derived on the fly from your existing data — there are no
extra tables to migrate and nothing to persist. Register the flows you need once, then ask a
model for its flow to get the next step, completion percentage, and a JSON-ready array for
your frontend.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/onboarding-for-laravel
```

The service provider and the `Onboarding` facade alias are registered automatically through
package discovery. There is **no config file, no migrations, and no views** to publish — the
package is entirely in-memory and stateless.

## Core concepts

- **`Registry`** — the central store of named flows. Resolved as a singleton and reachable
  through the `Onboarding` facade.
- **`Flow`** — a named, ordered set of steps. Reports overall progress and the next step.
- **`Step`** — a single onboarding task. Its completion and exclusion are decided by optional
  closures that receive the model the flow is bound to.
- **`GetsOnboarded`** — a trait for your Eloquent models that resolves the model's flow and
  binds the model to it.

## Usage

### Registering flows

Register flows once — typically in the `boot()` method of one of your application's service
providers.

```php
use App\Models\User;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

Onboarding::register(Flow::make('Profile Onboarding')->of([
    Step::make('Upload Photo')
        ->cta('Upload Now')
        ->action('users@photo@upload') // a route name, URL, or any hint your frontend understands
        ->completeIf(fn (?User $user) => (bool) $user?->photo),
]));
```

Registering a flow without a key stores it under the default key (`default`). You can also
register additional flows under explicit keys:

```php
Onboarding::register('admin', Flow::make('Admin Setup')->of([
    Step::make('Invite your team'),
    Step::make('Connect billing')->completeIf(fn (?User $user) => $user?->hasBilling()),
]));
```

You may register a flow straight from an array of steps — it is wrapped in a `Flow` for you,
and with no key it becomes the default flow:

```php
Onboarding::register([
    Step::make('Upload Photo')->cta('Upload Now'),
]);
```

### Custom flow classes

For reusable flows, extend `Flow` and configure it in `setup()`:

```php
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

final class UserOnboarding extends Flow
{
    protected function setup(): void
    {
        $this->title('User Onboarding')->of([
            Step::make('Say My Name'),
        ]);
    }
}
```

Register a custom flow class by its class string — the registry instantiates it:

```php
use RoundlyConsulting\Onboarding\Facades\Onboarding;

Onboarding::register(UserOnboarding::class);            // stored as the default flow
Onboarding::register('user', UserOnboarding::class);    // stored under the "user" key
```

### Preparing a model

Add the `GetsOnboarded` trait to any Eloquent model that should expose its onboarding flow:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Onboarding\Traits\GetsOnboarded;

class User extends Model
{
    use GetsOnboarded;
}
```

The trait resolves the model's flow from the registry and binds the model to it, so every
step's `completeIf` / `excludeIf` closure receives that model.

By default the model uses the `default` flow key. Override `defaultOnboardingKey()` to choose
a different one — for example, from a column on the model:

```php
public function defaultOnboardingKey(): string
{
    return $this->onboarding_flow ?? 'default';
}
```

### Reading a model's onboarding

```php
$user = User::first();

$flow = $user->onboarding();                 // the model's default flow, bound to $user
$flow = $user->onboarding('admin');          // a specific flow by key
$flow = $user->onboarding('missing', $fallback = new Flow()); // fallback when the key is unknown

$flow?->title;                 // "Profile Onboarding"
$flow?->isCompleted();         // bool — every (non-excluded) step is complete
$flow?->isInProgress();        // bool — at least one step remains
$flow?->nextStep();            // the first incomplete Step, or null
$flow?->steps();               // Collection<int, Step> visible to this model (excluded steps removed)
$flow?->all();                 // Collection<int, Step> including excluded steps
$flow?->percentageCompleted(); // float, e.g. 66.67
$flow?->toArray();             // a JSON-ready array for your API/frontend
```

`onboarding()` returns `null` when no matching flow is registered and no fallback is given.

### Steps

A step is complete unless a `completeIf` closure says otherwise, and is never excluded unless
an `excludeIf` closure says so. Both closures receive the bound model (or `null` when the flow
has no model).

```php
use App\Models\User;
use RoundlyConsulting\Onboarding\Step;

Step::make('Verify email')
    ->cta('Resend verification')
    ->action('verification.notice')
    ->meta(['icon' => 'mail'])
    ->completeIf(fn (?User $user) => $user?->hasVerifiedEmail())
    ->excludeIf(fn (?User $user) => $user?->is_guest);
```

Available step helpers: `title()`, `cta()`, `action()`, `meta()`, `completeIf()`,
`excludeIf()`, plus the predicates `isCompleted()`, `isNotCompleted()`, `isExcluded()`,
`isNotExcluded()`, and `toArray()`.

### Serializing for an API

`Flow::toArray()` produces a structure ready to hand to a frontend:

```php
[
    'title' => 'Profile Onboarding',
    'percentage' => 50.0,
    'next_step' => [
        'title' => 'Upload Photo',
        'cta' => 'Upload Now',
        'action' => 'users@photo@upload',
        'is_completed' => false,
        'meta' => [],
    ],
    'steps' => [
        // each step as ['title', 'cta', 'action', 'is_completed', 'meta']
    ],
]
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
