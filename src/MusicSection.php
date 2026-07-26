<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * One ordered section of a {@see MusicIntent}: an energy `tag`, one chord symbol per bar (bar count =
 * chord count), the active roles, an optional fenced melody, and optional per-section tempo/meter
 * overrides (T09). The section's host identity (`tag`, authored fence, refrain linkage) is owned by the
 * host in the additive door; the model drafts chords + energy inside that skeleton (ADR-0125 §6).
 *
 * `Optional` (not `?…=null`) on omittable fields so the derived JSON Schema does NOT mark them
 * `required` — the writability constraint that lets the LLM omit them.
 */
#[Title('Section')]
final class MusicSection extends Data
{
    /**
     * @param  array<int, string>  $chords
     * @param  array<int, string>  $roles
     * @param  array<int, MelodyNote>|Optional  $melody
     */
    public function __construct(
        #[Title('Role / energy tag')]
        #[Description('INTRO | VERSE | CHORUS | BRIDGE | OUTRO | INSTRUMENTAL — drives density/velocity.')]
        public string $tag,

        #[Title('Chords (one per bar)')]
        #[Description('Chord symbols, one per bar: ["C","Am","F","G"]. Bar count = array length.')]
        #[ArrayItems('string')]
        public array $chords,

        #[Title('Active roles')]
        #[Description('Which parts play: drums|bass|keys|pad|lead|vocal. Empty = Arranger default (all).')]
        #[ArrayItems('string')]
        public array $roles = [],

        #[Title('Melody')]
        #[Description('Optional real melodic line (beat-relative). Omit → Arranger derives one (MelodyWriter).')]
        #[DataCollectionOf(MelodyNote::class)]
        public array|Optional $melody = new Optional,

        #[Title('Tempo override (BPM)')]
        #[Description('Optional. Overrides the song tempo from this section onward.')]
        public int|Optional $tempo = new Optional,

        #[Title('Meter override')]
        #[Description('Optional. Overrides the song meter from this section onward, e.g. "6/8".')]
        public string|Optional $meter = new Optional,
    ) {}
}
