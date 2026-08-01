<?php

namespace Splicewire\Composition\Music;

use Illuminate\Support\ServiceProvider;

/**
 * The shared music-data surface is pure Data/grammar — the {@see MusicIntent} wire format and its value
 * objects, plus the synthesis wire DTOs. It carries no behavioral ports (those moved to the private
 * engine) and no bindings of its own; this provider exists only so the package is a first-class,
 * auto-discovered Laravel package with a home for any future schema-registration wiring.
 */
class MusicDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
