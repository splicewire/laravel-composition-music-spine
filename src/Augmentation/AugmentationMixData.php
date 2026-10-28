<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A brief's optional mix overrides (music-capability-lift ticket 06 Q4): per-instrument gain and pan, keyed by
 * the instrument the sampler names (drums, bass, keys, pad, lead). An instrument left out keeps the mix stage's
 * default. Values are held as floats, so `0` and `0.0` are one brief and one digest.
 *
 * `gains_db` is mapped per property: laravel-data 4.23 lets the host's configured input mapper (the flagship's
 * CamelCaseMapper) override a class-level `#[MapName]` (measured in build issue 03).
 */
#[TypeScript]
#[Title('Augmentation mix')]
class AugmentationMixData extends Data
{
    /**
     * @param  array<string, float>  $gainsDb
     * @param  array<string, float>  $pans
     */
    public function __construct(
        #[Title('Gains (dB)')]
        #[Description('Per-instrument gain override in dB, keyed by instrument.')]
        #[MapName('gains_db')]
        public array $gainsDb = [],
        #[Title('Pans')]
        #[Description('Per-instrument pan override, -1 (left) to 1 (right), keyed by instrument.')]
        public array $pans = [],
    ) {
        $this->gainsDb = array_map(floatval(...), $gainsDb);
        $this->pans = array_map(floatval(...), $pans);
    }
}
