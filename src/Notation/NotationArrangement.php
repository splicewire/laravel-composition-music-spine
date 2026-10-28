<?php

namespace Splicewire\Composition\Music\Notation;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the score engraves (music-capability-lift ticket 02 Q3): a one-staff lead sheet (melody, chord
 * symbols above, syllables below) or a piano grand staff (the melody as the right hand's top voice over a
 * comp voiced in PHP).
 */
#[TypeScript]
enum NotationArrangement: string
{
    case LeadSheet = 'lead_sheet';
    case Piano = 'piano';
}
