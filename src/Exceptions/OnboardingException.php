<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for the onboarding package.
 *
 * Extends InvalidArgumentException so consumers that already catch the SPL
 * type keep working after the package switched to typed exceptions.
 */
class OnboardingException extends InvalidArgumentException {}
