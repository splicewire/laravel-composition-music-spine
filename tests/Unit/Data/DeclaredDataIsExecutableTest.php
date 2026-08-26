<?php

use Spatie\LaravelData\Contracts\BaseData;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Support\DataConfig;

/**
 * The executability gate for this package's declared wire DTOs (api-surface-coherence ticket 85).
 *
 * This package is the worst case the sweep found: ten classes under `src/` declare themselves with
 * `spatie/laravel-data` and `tests/` was an empty directory — nothing here had ever been executed
 * by a suite at all. Declaring a particle is only worth what the declaration can be EXECUTED to
 * prove; the generated TypeScript, the OpenAPI document and the SDK all agree with a DTO that would
 * throw the moment a host tried to hydrate it otherwise.
 *
 * So this walks every concrete declared Data class and executes the two halves of the hydration
 * path that `validateAndCreate()` runs before it ever touches a payload:
 *
 *  1. `DataConfig::getDataClass()` — the full reflection analysis: every property's type, every
 *     attribute instantiated, casts / mappers / nested Data classes resolved.
 *  2. `::getValidationRules([])` — the rule inferrers plus any hand-written `rules()`, projected
 *     through the input name mapper.
 *
 * Neither needs a fixture payload, which is what lets it be data-driven across the whole surface;
 * a per-class payload test is a finer instrument and belongs with the class that needs one.
 */
function musicSpineDataClasses(): array
{
    $root = dirname(__DIR__, 3).'/src';

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    $classes = [];

    foreach ($files as $file) {
        $path = $file->getPathname();

        if (substr($path, -4) !== '.php') {
            continue;
        }

        // Namespace is the path under src/, PSR-4'd onto Splicewire\Composition\Music\.
        $relative = substr($path, strlen($root) + 1, -4);
        $class = 'Splicewire\\Composition\\Music\\'.str_replace('/', '\\', $relative);

        if (! class_exists($class)) {
            continue;
        }

        if (! is_subclass_of($class, BaseData::class)) {
            continue;
        }

        if ((new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        $classes[$class] = [$class];
    }

    ksort($classes);

    return $classes;
}

it('has the laravel-data provider booted, mirroring the host config', function () {
    // Guards the fix itself. `config('data')` alone is NOT a sufficient probe here, and that was
    // measured: this harness's own `defineEnvironment()` sets two `data.*` keys, which materializes
    // the `data` config array whether or not the provider ever booted. Assert the provider itself.
    expect(app()->getLoadedProviders())
        ->toHaveKey(LaravelDataServiceProvider::class);

    // The package's own config, merged only by that provider. With the provider absent this is
    // empty and NO rule is ever inferred — measured: `MusicKey::getValidationRules([])` returns
    // `['tonic' => []]` instead of `['tonic' => ['required', 'string']]`, which still satisfies a
    // bare `toBeArray()`. That is the exact false green this test exists to refuse.
    expect(config('data.rule_inferrers'))->toBeArray()->not->toBeEmpty();

    // And the second false green: the package default for the input mapper is `null`, only the host
    // (`~/Herd/splicewire-app/config/data.php`) sets CamelCaseMapper, and a DTO that hydrates
    // without it can still stop mapping under the host.
    expect(config('data.name_mapping_strategy.input'))
        ->toBe(CamelCaseMapper::class);
});

it('finds the declared Data classes to check', function () {
    // Ten files under src/ import spatie/laravel-data and all ten declare a concrete `Data`
    // subclass. A discovery bug that silently found fewer would make the cases below vacuous.
    expect(count(musicSpineDataClasses()))->toBeGreaterThanOrEqual(10);
});

it('can analyse and derive validation rules for every declared Data class', function (string $class) {
    $dataClass = app(DataConfig::class)->getDataClass($class);

    expect($dataClass->name)->toBe($class);

    $rules = $class::getValidationRules([]);

    // Deliberately only a shape assertion per class: laravel-data omits a rule entirely for a
    // property that has a default and is absent from the payload, so an all-defaulted DTO
    // legitimately derives `[]` here (measured: MelodyGuideCellData, both properties defaulted).
    // The guard that the inferrers actually RAN is the surface-wide one below.
    expect($rules)->toBeArray();
})->with(musicSpineDataClasses());

it('derives real inferred rules across the declared surface', function () {
    // The per-class case above cannot assert non-empty rules (see its note), which would leave the
    // whole dataset satisfiable by a rule-less result — exactly what an unbooted laravel-data
    // provider produces (measured: `MusicKey::getValidationRules([])` degrades from
    // `['tonic' => ['required', 'string']]` to `['tonic' => []]`, and a bare `toBeArray()` accepts
    // it). So assert it once over the whole surface instead.
    $withRules = 0;

    foreach (array_keys(musicSpineDataClasses()) as $class) {
        $rules = array_filter(
            $class::getValidationRules([]),
            fn ($rule) => is_array($rule) ? $rule !== [] : $rule !== null
        );

        if ($rules !== []) {
            $withRules++;
        }
    }

    // 9 of the 10 declared classes carry at least one required/typed property; only the
    // all-defaulted MelodyGuideCellData does not.
    expect($withRules)->toBeGreaterThanOrEqual(9);
});
