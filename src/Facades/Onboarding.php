<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Onboarding\Registry;

/**
 * @method static Registry register(\RoundlyConsulting\Onboarding\Flow|array<int, \RoundlyConsulting\Onboarding\Step>|class-string<\RoundlyConsulting\Onboarding\Flow> $key, \RoundlyConsulting\Onboarding\Flow|array<int, \RoundlyConsulting\Onboarding\Step>|class-string<\RoundlyConsulting\Onboarding\Flow>|null $flow = null)
 * @method static \RoundlyConsulting\Onboarding\Flow flow(string $key)
 * @method static \RoundlyConsulting\Onboarding\Flow|null find(string $key, ?\RoundlyConsulting\Onboarding\Flow $default = null)
 * @method static bool has(string $key)
 * @method static Registry forget(string $key)
 * @method static Registry flush()
 * @method static \Illuminate\Support\Collection<string, \RoundlyConsulting\Onboarding\Flow> all()
 *
 * @see Registry
 */
class Onboarding extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Registry::class;
    }
}
