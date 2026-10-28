<?php

namespace Splicewire\Composition\Music;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * One ordered section of a {@see MusicIntent}: an energy `tag`, one chord symbol per bar (bar count =
 * chord count), the active roles, an optional fenced melody, optional per-section tempo/meter
 * overrides (T09), and (v2) optional sub-bar {@see ChordChange}s.
 *
 * **`changes` (v2).** The section's harmony with onsets, for harmonic rhythm finer than a bar. `chords`
 * stays the chord sounding at each bar's downbeat, so the bar count and every v1 consumer keep working;
 * when `changes` is present `chords[i]` must equal the change sounding at bar *i*'s downbeat (a mandatory
 * {@see Conformance\StructuralMusicConformance} check). Absent or empty means what v1 meant: one chord per
 * bar, sounding the whole bar. The section's host identity (`tag`, authored fence, refrain linkage) is owned by the
 * host in the additive door; the model drafts chords + energy inside that skeleton (ADR-0125 §6).
 *
 * `Optional` (not `?…=null`) on omittable fields so the derived JSON Schema does NOT mark them
 * `required` — the writability constraint that lets the LLM omit them.
 */
#[Title('Section')]
class MusicSection extends Data
{
    /**
     * @param  array<int, string>  $chords
     * @param  array<int, string>  $roles
     * @param  array<int, MelodyNote>|Optional  $melody
     * @param  array<int, ChordChange>|Optional  $changes
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

        #[Title('Chord changes')]
        #[Description('Optional sub-bar harmony: each chord with its start in beats from the section start, in increasing order. chords[i] must equal the change sounding at bar i downbeat. Omit → one chord per bar.')]
        #[DataCollectionOf(ChordChange::class)]
        public array|Optional $changes = new Optional,
    ) {}

    /**
     * The section's chord changes, or `[]` when it declares none (v1 meaning: one chord per bar).
     *
     * @return array<int, ChordChange>
     */
    public function changeList(): array
    {
        return is_array($this->changes) ? array_values($this->changes) : [];
    }
}
