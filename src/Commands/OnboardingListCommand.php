<?php

declare(strict_types=1);

namespace RoundlyConsulting\Onboarding\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Onboarding\Flow;
use RoundlyConsulting\Onboarding\Registry;
use RoundlyConsulting\Onboarding\Step;

final class OnboardingListCommand extends Command
{
    protected $signature = 'onboarding:list {key? : Inspect a single flow by its key}';

    protected $description = 'List the registered onboarding flows, or inspect one flow\'s steps';

    public function handle(Registry $registry): int
    {
        $key = $this->argument('key');

        if (is_string($key)) {
            return $this->inspect($registry, $key);
        }

        return $this->listFlows($registry);
    }

    private function listFlows(Registry $registry): int
    {
        $flows = $registry->all();

        if ($flows->isEmpty()) {
            $this->info('No onboarding flows are registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Title', 'Steps', 'Percentage'],
            $flows->map(fn (Flow $flow, string $key): array => [
                $key,
                $flow->title ?? '—',
                (string) $flow->count(),
                number_format($flow->percentageCompleted(), 2).'%',
            ])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function inspect(Registry $registry, string $key): int
    {
        $flow = $registry->find($key);

        if (is_null($flow)) {
            $this->error("No onboarding flow registered under [{$key}].");

            return self::FAILURE;
        }

        $this->table(
            ['Key', 'Title', 'Optional', 'Completed'],
            $flow->steps()->map(fn (Step $step): array => [
                $step->stepKey(),
                $step->resolvedTitle() ?? '—',
                $step->isOptional() ? 'yes' : 'no',
                $step->isCompleted() ? 'yes' : 'no',
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
