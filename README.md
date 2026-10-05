<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/onboarding-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=onboarding-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/onboarding-for-laravel/main/art/hero.png" alt="Onboarding for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/onboarding-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/onboarding-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/onboarding-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/onboarding-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/onboarding-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/onboarding-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=onboarding-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Onboarding for Laravel

Describe onboarding as flows of ordered steps and derive each user's progress on the fly from the
data you already have — no tables, nothing to persist. Ask a model for its flow to get the next
step, the completion percentage and a JSON-ready array for your frontend, and keep unfinished
users on their current step with one middleware.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/onboarding-for-laravel
```

## Usage

Register a flow once, in a service provider's `boot()`:

```php
use App\Models\User;
use RoundlyConsulting\Onboarding\Facades\Onboarding;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Step;

Onboarding::register(Flow::make('Profile Onboarding')->of([
    Step::make('Verify email')->route('verification.notice')->completeWhenFilled('email_verified_at'),
    Step::make('Upload photo')->cta('Upload now')->route('profile.photo')->completeWhenFilled('avatar_path'),
    Step::make('Add a bio')->optional()->completeIf(fn (?User $user) => filled($user?->bio)),
]));
```

Add `GetsOnboarded` to the model and read its progress:

```php
use RoundlyConsulting\Onboarding\Traits\GetsOnboarded;

class User extends Authenticatable
{
    use GetsOnboarded;
}

$user->onboardingProgress();             // 33.33
$user->nextOnboardingStep()?->title;     // "Upload photo"
Onboarding::for($user)?->toArray();      // title, percentage, next step, every step
```

Send users who haven't finished to their current step:

```php
Route::middleware(['auth', 'onboarding'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
});
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/onboarding-for-laravel](https://roundly-consulting.com/open-source/docs/onboarding-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=onboarding-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=onboarding-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=onboarding-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
