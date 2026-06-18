<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Tests;

use RoundlyConsulting\Onboarding\Flow;

class CustomFlow extends Flow
{
    protected function setup(): void
    {
        $this->title('My Custom Flow');
    }
}
