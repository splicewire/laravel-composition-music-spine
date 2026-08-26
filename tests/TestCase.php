<?php

namespace Splicewire\Composition\Music\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\DataSchemas\LaravelDataSchemasServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Splicewire\Composition\Music\MusicDataServiceProvider;

/**
 * Base testbench case for the music-spine package (api-surface-coherence ticket 85).
 *
 * Before this file existed, `tests/` was an EMPTY DIRECTORY — `orchestra/testbench` and
 * `pestphp/pest` sat in `require-dev` and nothing in this package had ever been executed by a
 * suite, while ten classes under `src/` declare themselves with `spatie/laravel-data`. This is an
 * executability floor, not a behavioural suite: the point is that the declared wire format can be
 * reflected and can derive validation rules, because that is what the Particle doctrine's
 * "declaration locks the TypeScript / OpenAPI / SDK to the backend" guarantee actually rests on.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            // Testbench does NOT auto-discover: a package harness boots exactly what this method
            // names, while `src/` freely imports anything it can autoload. This package reaches
            // `spatie/laravel-data` through its own `require` and declares ten Data classes with
            // it, but without this line `config('data')` is NULL inside this suite and every
            // hydration path fatals with `Trying to access array offset on null` before it can be
            // asserted on — a FATAL, not a failure. Measured in `splicewire/tower` first
            // (ticket 84).
            LaravelDataServiceProvider::class,

            // Same mechanism, second dependency: every Data class here is annotated with
            // `Schemastud\DataSchemas` attributes, and the schema package's own pipeline/vocabulary
            // bindings are registered by this provider. Booting laravel-data without it would test
            // half the declaration surface.
            LaravelDataSchemasServiceProvider::class,

            MusicDataServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $c = $app['config'];

        $c->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $c->set('database.default', 'testing');
        $c->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Laravel 11+ defaults cache.default to 'database', which needs a migrated `cache` table
        // this harness never creates.
        $c->set('cache.default', 'array');

        // Booting LaravelDataServiceProvider alone would be a FALSE GREEN. The package ships
        // `name_mapping_strategy.input => null`, but the only host that runs this code
        // (`~/Herd/splicewire-app/config/data.php`) sets it to CamelCaseMapper — the one semantic
        // delta between the two files. A DTO whose properties are snake_case hydrates fine under
        // testbench defaults and silently stops mapping under the host's mapper. The harness
        // mirrors the host, not the package default.
        $c->set('data.name_mapping_strategy.input', CamelCaseMapper::class);

        // Structure caching points at `app_path('Data')` by default, which does not exist under
        // testbench — and a cached reflection analysis carried across runs is exactly what a
        // harness should not have.
        $c->set('data.structure_caching.enabled', false);
    }
}
