<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Separation\StemData;

/**
 * One role's note transcription (music-capability-lift ticket 04 Q2, E1). A stem below its signal threshold is not
 * transcribed: it is recorded `gated: true`, carries no `media_ref`, and contributes no evidence (which is not
 * silence) to confidence. A stem above it is transcribed and carries its note MIDI's media uuid. Any other pairing
 * is refused at construction.
 *
 * This `gated` is the stem's signal gate. It is not {@see TranscriptionResult::$gated}, which says whether the
 * transcription gate admitted the run's stage set.
 *
 * `relative_db` is the stem's level from its separation run ({@see StemData}),
 * null when that run had no reference. Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Transcribed notes')]
class TranscriptionNotesData extends Data
{
    public function __construct(
        #[Title('Role')]
        #[Description('The part transcribed: bass, guitar, piano, other, vocals …')]
        public string $role,
        #[Title('Media ref')]
        #[Description('The note MIDI media uuid; null exactly when the stem was gated.')]
        #[MapName('media_ref')]
        public ?string $mediaRef,
        #[Title('Relative dB')]
        #[Description('The stem\'s relative_db from its separation run; null when that run had no reference.')]
        #[MapName('relative_db')]
        public ?float $relativeDb,
        #[Title('Gated')]
        #[Description('True when the stem fell below its signal threshold and was not transcribed.')]
        public bool $gated,
    ) {
        if ($gated && $mediaRef !== null) {
            throw new InvalidArgumentException("A gated stem is not transcribed; role {$role} carries a media_ref.");
        }

        if (! $gated && ($mediaRef === null || $mediaRef === '')) {
            throw new InvalidArgumentException("A transcribed stem carries its note media; role {$role} has no media_ref.");
        }
    }
}
