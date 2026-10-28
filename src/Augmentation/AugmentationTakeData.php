<?php

namespace Splicewire\Composition\Music\Augmentation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The kept candidate's artifacts (music-capability-lift ticket 06 Q4, ticket 10 Q5): tenant media on the
 * composition, collection `augmentation`, by uuid. The stems are the sampler's, for the kept candidate only,
 * keyed by instrument; the MIDI is the Arranger's, kept because tenant code changes out of the platform's sight.
 *
 * The wire names are snake_case, mapped per property (see {@see AugmentationMixData}).
 */
#[TypeScript]
#[Title('Augmentation take')]
class AugmentationTakeData extends Data
{
    /**
     * @param  array<string, string>  $stemMediaIds
     */
    public function __construct(
        #[Title('Mix media id')]
        #[Description('The mixed take, by media uuid.')]
        #[MapName('mix_media_id')]
        public string $mixMediaId,
        #[Title('Stem media ids')]
        #[Description('Each sampled stem by media uuid, keyed by instrument.')]
        #[MapName('stem_media_ids')]
        public array $stemMediaIds,
        #[Title('MIDI media id')]
        #[Description('The arranged MIDI the take was sampled from, by media uuid.')]
        #[MapName('midi_media_id')]
        public string $midiMediaId,
    ) {}
}
