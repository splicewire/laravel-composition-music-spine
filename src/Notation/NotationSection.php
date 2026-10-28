<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What is printed for one section of the request's intent, aligned by index (music-capability-lift ticket 02
 * Q1): the owner's heading (`label`, `direction`), the lyric `lines`, optional timed `syllables`, and inline
 * `cues`. A section's `tag` is an energy tag, not a label; the label is the owner's own heading.
 */
#[TypeScript]
#[Title('Notation section')]
class NotationSection extends Data
{
    /**
     * @param  array<int, string>  $lines
     * @param  array<int, NotationSyllable>  $syllables
     * @param  array<int, NotationCue>  $cues
     */
    public function __construct(
        #[Title('Label')]
        #[Description('The section heading as printed, e.g. "VERSE 1".')]
        public ?string $label = null,
        #[Title('Direction')]
        #[Description('The owner performance note beside the heading, e.g. "start sparse, build".')]
        public ?string $direction = null,
        #[Title('Lyric lines')]
        #[Description('The section lyrics, one entry per printed line.')]
        #[ArrayItems('string')]
        public array $lines = [],
        #[Title('Syllables')]
        #[Description('Optional timed syllables (syllable onsets) for underlay and chord anchoring.')]
        #[DataCollectionOf(NotationSyllable::class)]
        public array $syllables = [],
        #[Title('Cues')]
        #[Description('Inline performance cues at onsets.')]
        #[DataCollectionOf(NotationCue::class)]
        public array $cues = [],
    ) {}
}
