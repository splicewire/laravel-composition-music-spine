<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData;

use Illuminate\Support\ServiceProvider;

/**
 * The shared music-spine-data surface is pure Data/contract shape — no bindings of its own. This
 * provider exists so the package is a first-class Laravel package (auto-discovered) and has a home for
 * any future schema-registration wiring; the {@see MusicIntent} format and the
 * {@see Contracts\RenderInvocable} port are plain classes consumed directly.
 */
class CompositionMusicSpineDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
