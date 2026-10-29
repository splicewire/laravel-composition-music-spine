<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Splicewire\Composition\Music\ChordChange;

/**
 * A {@see ChartDraftSections::apply()} refused **by name** (music-capability-lift ticket 04 Q2 "The conversion after
 * sectioning"). {@see self::$reason} is the stable machine name a caller maps to its own refusal (the chart
 * worksheet's import, issue 28); the message carries it too, with what to fix.
 *
 * - `sectioning_empty` — no section was given.
 * - `sectioning_not_from_bar_zero` — the first section does not start at bar 0.
 * - `sectioning_not_ascending` — a section does not start after the previous one.
 * - `section_past_last_bar` — a section starts at or past the draft's last bar.
 * - `change_before_pickup` — a draft change starts before the pickup (`start` < `-pickup_beats`). A change inside the
 *   pickup is a pickup chord in section 0 (build/26); one before it has no place and is never moved.
 *   {@see self::$changes} lists every such change by its draft index.
 * - `meter_change_inside_section` — a bar inside a section is not as long as the section's first bar; re-section there.
 * - `meter_unrepresentable` — a section's bar length cannot be written as `n/d` on the draft meter's denominator.
 * - `bar_without_chord` — no change sounds at a bar's downbeat (before the draft's first change).
 */
class ChartDraftSectionsRefused extends InvalidArgumentException
{
    public const SECTIONING_EMPTY = 'sectioning_empty';

    public const SECTIONING_NOT_FROM_BAR_ZERO = 'sectioning_not_from_bar_zero';

    public const SECTIONING_NOT_ASCENDING = 'sectioning_not_ascending';

    public const SECTION_PAST_LAST_BAR = 'section_past_last_bar';

    public const CHANGE_BEFORE_PICKUP = 'change_before_pickup';

    public const METER_CHANGE_INSIDE_SECTION = 'meter_change_inside_section';

    public const METER_UNREPRESENTABLE = 'meter_unrepresentable';

    public const BAR_WITHOUT_CHORD = 'bar_without_chord';

    /** The machine name of the refusal. */
    public string $reason;

    /**
     * For `change_before_pickup`: every change before the pickup, keyed by its index in the draft's `changes`. Empty
     * otherwise.
     *
     * @var array<int, ChordChange>
     */
    public array $changes;

    /**
     * @param  array<int, ChordChange>  $changes
     */
    public function __construct(string $reason, string $detail, array $changes = [])
    {
        $this->reason = $reason;
        $this->changes = $changes;

        parent::__construct("Chart draft sectioning refused ({$reason}): {$detail}");
    }
}
