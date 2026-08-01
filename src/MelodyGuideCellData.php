<?php

namespace Splicewire\Composition\Music;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The typed payload for a `music`-profile melody-guide cell's `slots` — the
 * first-class melody guide lane's vocabulary (a MIDI `melodyRef` over a section).
 * It is the `melody-guide` Kind, registered against the `melody-guide` category in
 * the host Kind registry; a vocal cell pairs its lyric with the guide this carries.
 *
 * Lives in the music SPINE (render-brief-and-voice / ticket 09) so both the platform
 * intake and a satellite's projection share the one shape (drift-safe projection).
 */
#[Title('Melody guide')]
class MelodyGuideCellData extends Data
{
    public function __construct(
        #[Title('Melody reference')]
        #[Description('The guide-lane MIDI for this section.')]
        #[MapInputName(SnakeCaseMapper::class)]
        public string $melodyRef = '',
        #[Title('Section')]
        #[Description('The song section this guide covers.')]
        public ?string $section = null,
    ) {}
}
