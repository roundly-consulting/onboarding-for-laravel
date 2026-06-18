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

### Fluent registration

`Onboarding::flow()` creates, registers, and returns an empty named flow you can build
inline:

```php
Onboarding::flow('default')
    ->title('Profile Onboarding')
    ->add('Upload Photo')->key('upload-photo')->cta('Upload Now')
        ->action('users@photo@upload')
        ->completeIf(fn (?User $user) => (bool) $user?->photo);
```

The registry also exposes `has()`, `forget()`, and `flush()`:

```php
Onboarding::has('admin');     // bool
Onboarding::forget('admin');  // remove one flow
Onboarding::flush();          // remove all flows
```

Registering a non-`Flow` class string throws
`RoundlyConsulting\Onboarding\Exceptions\InvalidFlowException` (which extends
`InvalidArgumentException`).

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
$flow?->isCompleted();         // bool — every required, non-excluded step is complete
$flow?->isInProgress();        // bool — at least one required step remains
$flow?->nextStep();            // the first incomplete Step, or null
$flow?->currentStep();         // alias of nextStep() — the step to resume on
$flow?->steps();               // Collection<int, Step> visible to this model (excluded steps removed)
$flow?->all();                 // Collection<int, Step> including excluded steps
$flow?->percentageCompleted(); // float across all visible steps, e.g. 66.67
$flow?->toArray();             // a JSON-ready array for your API/frontend
$flow?->toData();              // a typed RoundlyConsulting\Onboarding\DataTransferObjects\FlowData
```

`onboarding()` returns `null` when no matching flow is registered and no fallback is given.

### Model readers

The `GetsOnboarded` trait adds null-safe one-liners so you don't have to chain through
`onboarding()?->...` yourself. Each accepts an optional flow key:

```php
$user->hasCompletedOnboarding();   // bool — false when no flow is registered
$user->isOnboarding();             // bool — in progress
$user->onboardingProgress();       // float — 0.0 when no flow is registered
$user->nextOnboardingStep();       // ?Step
$user->hasCompletedOnboarding('admin'); // target a specific flow key
```

### Position and resume

A flow derives position information on the fly — nothing is stored:

```php
$flow->position();          // 1-based human position, e.g. "step 3"
$flow->count();             // number of visible steps
$flow->completedCount();    // visible steps already complete
$flow->currentStepIndex();  // 0-based index of the current step, null when complete
$flow->isStarted();         // bool — at least one step complete
$flow->isEmpty();           // bool — no visible steps
```

An **empty** flow is treated as fully complete: `isCompleted()` is `true` and
`percentageCompleted()` is `100.0`.

### Addressing steps by key

Every step has a stable key — set it explicitly with `key()`, or let it fall back to a slug
of the title. Read it with `stepKey()`:

```php
$flow->step('upload-photo')?->isCompleted();
$flow->hasStep('upload-photo');           // bool
$step->stepKey();                          // 'upload-photo' (explicit or slugged from the title)
```

### Optional steps

Mark a step `optional()` so it guides the user without blocking completion. Optional steps are
excluded from `isCompleted()` and from `requiredPercentageCompleted()`, but still count toward
the default `percentageCompleted()`:

```php
Onboarding::flow('default')
    ->add('Add a bio')->key('bio')->optional()
        ->completeIf(fn (?User $user) => filled($user?->bio));

$flow->requiredSteps();               // Collection<int, Step>
$flow->optionalSteps();               // Collection<int, Step>
$flow->requiredPercentageCompleted(); // float over required steps only
```

### Ordering

Steps follow insertion order by default. Give any step an explicit `order()` and the flow
sorts by it:

```php
Step::make('Verify email')->order(1);
Step::make('Upload photo')->order(2);
```

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

Available step helpers: `title()`, `cta()`, `action()`, `meta()`, `key()`, `order()`,
`optional()`, `required()`, `completeIf()`, `excludeIf()`, plus the predicates
`isCompleted()`, `isNotCompleted()`, `isExcluded()`, `isNotExcluded()`, `isOptional()`,
`isRequired()`, the `stepKey()` reader, and `toArray()` / `toData()`.

### Analytics events

Because the package is stateless it can't detect transitions on its own, but it can announce
the current truth when you ask it to. Call `record()` at your own checkpoint (for example,
after a request that may have completed a step) and it dispatches `StepCompleted` for every
complete step and `FlowCompleted` when the whole flow is complete. **Reads never dispatch** —
only `record()` does.

```php
use RoundlyConsulting\Onboarding\Events\FlowCompleted;
use RoundlyConsulting\Onboarding\Events\StepCompleted;

$user->onboarding()?->record();

Event::listen(StepCompleted::class, fn (StepCompleted $e) => /* $e->step, $e->for */);
Event::listen(FlowCompleted::class, fn (FlowCompleted $e) => /* $e->flow, $e->for */);
```

### Inspecting flows from the CLI

```bash
php artisan onboarding:list            # all registered flows
php artisan onboarding:list default    # the steps of one flow
```

### Serializing for an API

`Flow::toArray()` produces a structure ready to hand to a frontend:

```php
[
    'title' => 'Profile Onboarding',
    'percentage' => 50.0,
    'next_step' => [
        'key' => 'upload-photo',
        'title' => 'Upload Photo',
        'cta' => 'Upload Now',
        'action' => 'users@photo@upload',
        'is_completed' => false,
        'is_optional' => false,
        'meta' => [],
    ],
    'current_step' => [
        // same shape as next_step
    ],
    'is_completed' => false,
    'steps' => [
        // each step as ['key', 'title', 'cta', 'action', 'is_completed', 'is_optional', 'meta']
    ],
]
```

For a typed equivalent, `Flow::toData()` returns a `FlowData` DTO (and `Step::toData()` a
`StepData`); both implement `Arrayable` and `JsonSerializable`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
