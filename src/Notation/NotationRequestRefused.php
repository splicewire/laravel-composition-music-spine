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
 */
class NotationRequestRefused extends InvalidArgumentException
{
    public const FORMATS_EMPTY = 'formats_empty';

    public const SECTIONS_MISMATCH = 'sections_mismatch';

    public const LYRIC_BLOCK_WITHOUT_PAGES = 'lyric_block_without_pages';

    public const LYRIC_BLOCK_EMPTY = 'lyric_block_empty';

    public function __construct(public readonly string $reason, string $detail)
    {
        parent::__construct("Notation request refused ({$reason}): {$detail}");
    }
}
