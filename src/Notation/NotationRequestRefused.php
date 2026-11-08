<?php

namespace Splicewire\Composition\Music\Notation;

use InvalidArgumentException;

/**
 * A {@see NotationRequest} refused at construction, **by name** (music-capability-lift ticket 02 Q1/Q2,
 * ticket 09 Q8). {@see self::$reason} is the stable machine name a host maps to its refusal response; the
 * message carries it too.
 *
 * - `formats_empty` — `formats` is required and non-empty; there is no default.
 * - `sections_mismatch` — `sections` does not align one-to-one with the intent's sections.
 * - `lyric_block_without_pages` — `lyricBlock` is true but no paged format (`svg`, `pdf`) is asked for.
 * - `lyric_block_empty` — `lyricBlock` is true but no section has lyric `lines`.
 *
 * Syllables (build/26, ticket 05 E3, Q4 amended; {@see NotationSyllable}):
 * - `syllables_out_of_order` — a section's timed syllables do not start in strictly increasing order.
 * - `syllable_too_early` — a timed syllable starts before its section allows: `-pickup` in section 0, one bar of
 *   the previous section's meter in a later one.
 * - `syllable_untimed` — a syllable in a section with melody has no `start`.
 * - `syllable_without_note` — a syllable in a section with melody has no melody note starting at its onset.
 * - `syllable_off_chord` — a timed syllable in a section without melody sits under no chord change: none starts at its
 *   onset in its own section or, for a lead-in, in the previous one (orchestrator reading R4, from 05 Q4).
 */
class NotationRequestRefused extends InvalidArgumentException
{
    public const FORMATS_EMPTY = 'formats_empty';

    public const COMP_MIDI_NOT_PIANO = 'comp_midi_not_piano';

    public const SECTIONS_MISMATCH = 'sections_mismatch';

    public const LYRIC_BLOCK_WITHOUT_PAGES = 'lyric_block_without_pages';

    public const LYRIC_BLOCK_EMPTY = 'lyric_block_empty';

    public const SYLLABLES_OUT_OF_ORDER = 'syllables_out_of_order';

    public const SYLLABLE_TOO_EARLY = 'syllable_too_early';

    public const SYLLABLE_UNTIMED = 'syllable_untimed';

    public const SYLLABLE_WITHOUT_NOTE = 'syllable_without_note';

    public const SYLLABLE_OFF_CHORD = 'syllable_off_chord';

    public function __construct(public readonly string $reason, string $detail)
    {
        parent::__construct("Notation request refused ({$reason}): {$detail}");
    }
}
