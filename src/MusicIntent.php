<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Schemastud\DataSchemas\Contracts\SchemaIdentity;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * The platform-coined, song-agnostic **music-intent** format an upstream LLM drafts from freeform
 * instructions (ADR-0125). Level = "between": chords + energy `tag` + roles + an *optional* real
 * beat-relative melody per section; the deterministic Arranger keeps everything below that line
 * (voice-leading, bass/drums/keys/pad, GM mapping, the MelodyWriter fallback). A coined JSON dialect
 * embedding universal micro-wheels (MIDI pitch, chord symbols, tonic+mode key) — NOT ABC/MusicXML, and
 * NOT the song-shaped SongData (no lyric/vocalist/occasion; tempo/meter/key carried *structurally*).
 * MIDI is one render target; the T05 adapter materializes this into the Arranger's input.
 *
 * Renamed from the pre-ratification `MidiIntentData` (T06): bare wire-DTO name (no `Data` suffix), Kind
 * `music/intent`. Versioned via {@see SchemaIdentity} (`music/intent` v1) so a persisted intent carries
 * the schema version it was written under; the *platform cell layer* (T04, app-local) adds the
 * migrate-on-read concern over this plain payload. Non-`final` so a subclass may host that concern.
 */
#[Title('Music intent')]
class MusicIntent extends Data implements SchemaIdentity
{
    /**
     * @param  array<int, MusicSection>  $sections
     */
    public function __construct(
        #[Title('Key')]
        public MusicKey $key,

        #[Title('Sections')]
        #[Description('Ordered musical form; each section is N bars = N chords.')]
        #[DataCollectionOf(MusicSection::class)]
        public array $sections,

        #[Title('Tempo (BPM)')]
        #[Description('Default quarter-note tempo. The Arranger no longer infers from occasion. A section may override.')]
        public int $tempo = 96,

        #[Title('Meter')]
        #[Description('Default time signature, e.g. "4/4", "3/4", "6/8". A section may override.')]
        public string $meter = '4/4',

        #[Title('Feel')]
        #[Description('Named vibe biasing density/tempo (Arranger Feel::fromArray).')]
        public string|Optional $feel = new Optional,

        #[Title('Seed')]
        #[Description('Optional performance-pin for byte-reproducible render. Omit → content-hash default. The LLM never invents a seed.')]
        public int|Optional $seed = new Optional,
    ) {}

    public static function schemaName(): string
    {
        return 'music/intent';
    }

    public static function schemaVersion(): int
    {
        return 1;
    }
}
