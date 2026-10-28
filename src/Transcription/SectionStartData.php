<?php

namespace Splicewire\Composition\Music\Transcription;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

/**
 * One entry of the owner's sectioning of a chart draft (music-capability-lift ticket 05 E2): the section's energy
 * `tag`, the owner's free `label`, and the draft bar it starts on. A sectioning is a list of these, ascending from
 * bar 0 with no gaps, and is the input of {@see ChartDraftSections::apply()}. Which tags are admitted is the
 * worksheet parser's refusal (05 E2), not this shape's.
 *
 * It does not cross a boundary; it is Data so a caller can build it `::from(['tag', 'label', 'start_bar'])`.
 */
#[Title('Section start')]
class SectionStartData extends Data
{
    public function __construct(
        #[Title('Tag')]
        #[Description('The section\'s energy tag: INTRO | VERSE | PRE-CHORUS | CHORUS | BRIDGE | INSTRUMENTAL | OUTRO.')]
        public string $tag,
        #[Title('Label')]
        #[Description('The owner\'s free label for the section.')]
        public string $label,
        #[Title('Start bar')]
        #[Description('The draft bar index the section starts on.')]
        #[MapName('start_bar')]
        public int $startBar,
    ) {}
}
