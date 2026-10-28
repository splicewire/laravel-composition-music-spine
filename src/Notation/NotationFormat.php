<?php

namespace Splicewire\Composition\Music\Notation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One notation output a caller asks for (music-capability-lift ticket 02 Q2). `formats` is required and
 * non-empty; there is no default. MusicXML is always produced internally as the engraving source, but is
 * stored only when asked for; SVG is one artifact per page; PDF is assembled from those pages; `chart` is
 * Markdown in the owner's chord-sheet format.
 */
#[TypeScript]
enum NotationFormat: string
{
    case MusicXml = 'musicxml';
    case Svg = 'svg';
    case Pdf = 'pdf';
    case Chart = 'chart';

    /** Whether this format is paged engraving (where a lyric block can print). */
    public function isPaged(): bool
    {
        return $this === self::Svg || $this === self::Pdf;
    }
}
