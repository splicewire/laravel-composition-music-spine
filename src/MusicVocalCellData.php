<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Keyword;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The typed payload for a `music`-profile vocal cell's `slots` — the within-profile
 * vocabulary that makes a piece a *song* (a sung lyric paired with the melody it is
 * sung to). The melody is a first-class guide: `melodyRef` points at the guide lane's
 * MIDI (User Story 10). This Data class is the `music-vocal-cell` Kind's vocabulary,
 * registered against the `music-vocal` category in the host Kind registry.
 *
 * Lives in the music SPINE (render-brief-and-voice / ticket 09) so BOTH the platform
 * intake AND a satellite's projection type off the one shape — the projected cell slots
 * break at construct, not silently at read (drift-safe projection).
 */
#[Title('Vocal')]
final class MusicVocalCellData extends Data
{
    public function __construct(
        #[Title('Lyric')]
        #[Description('The sung lyric for this part.')]
        #[Keyword('x-widget', 'textarea')]
        public string $lyric,
        #[Title('Melody reference')]
        #[Description('The guide-lane MIDI this lyric is sung to.')]
        #[MapInputName(SnakeCaseMapper::class)]
        public ?string $melodyRef = null,
        #[Title('Section')]
        #[Description('The song section (verse, chorus, …).')]
        public ?string $section = null,
    ) {}
}
