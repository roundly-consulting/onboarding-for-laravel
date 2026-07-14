<?php

declare(strict_types=1);

/**
 * The package is configuration-free by design: it ships no `config/onboarding.php`
 * and reads no `onboarding.*` config key. Those two facts have to stay true
 * together — a package that *reads* a key it never *ships* has an unreachable
 * feature (the key silently resolves to null on every host), which is exactly how
 * an entire feature went dead in another package in this fleet.
 */
function onboardingSourceFiles(): array
{
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', RecursiveDirectoryIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('ships no config file', function () {
    expect(is_dir(dirname(__DIR__, 2).'/config'))->toBeFalse();
});

it('reads no config key it does not ship', function () {
    $files = onboardingSourceFiles();

    // Guard the guard: an empty file list would make the assertion below vacuous.
    expect($files)->not->toBeEmpty();

    $offenders = [];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);

        if (preg_match_all("/config\(\s*'([^']+)'/", $source, $matches) > 0) {
            foreach ($matches[1] as $key) {
                $offenders[] = basename($file).': '.$key;
            }
        }
    }

    expect($offenders)->toBe([]);
});
