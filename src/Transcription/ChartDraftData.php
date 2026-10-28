<?php

namespace Splicewire\Composition\Music\Transcription;

use InvalidArgumentException;
use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MusicKey;

/**
 * A **chart draft** (music-capability-lift ticket 04 Q2 amended, Q5; ticket 08 Q6): a take transcribed into bars
 * with chord changes, key, tempo and meter, each bar carrying a confidence. It has no sections and is not canonical
 * until the owner corrects it; {@see ChartDraftSections::apply()} turns a sectioned draft into v2 sections.
 *
 * **The draft coordinate.** `changes` reuse the spine's {@see ChordChange} unchanged, with `start` in beats from
 * the downbeat of draft bar 0, so a pickup change is negative, down to `-pickup_beats`. Confidence never enters a
 * change: it rides beside it in `change_meta`, aligned by index.
 *
 * **Held at construction** (the coordinate's own consistency, so a malformed draft fails where it is made, not in a
 * conversion later): bars are indexed 0…n−1 in order, each a positive number of beats, bar 0 starting at beat 0 and
 * each later bar where the previous one ends; `pickup_beats` is not negative; changes are strictly increasing and lie
 * in `[-pickup_beats, Σ beats)`; every `change_meta` index names a change and every `review_order` entry a bar, at
 * most once each.
 *
 * Wire names are snake_case, mapped per property.
 */
#[TypeScript]
#[Title('Chart draft')]
class ChartDraftData extends Data
{
    /** Beat comparisons tolerate float noise, as {@see StructuralMusicConformance} does. */
    public const EPSILON = 1e-9;

    /** Bar starts accumulate float beats; contiguity tolerates their rounding. */
    private const CONTIGUITY_EPSILON = 1e-6;

    /**
     * @param  list<ChordChange>  $changes
     * @param  list<DraftChangeMetaData>  $changeMeta
     * @param  list<DraftBarData>  $bars
     * @param  list<int>  $reviewOrder
     */
    public function __construct(
        #[Title('Key')]
        public MusicKey $key,
        #[Title('Tempo (BPM)')]
        #[Description('The tempo the draft measured. Never the tempo prior.')]
        #[MapName('tempo_bpm')]
        public float $tempoBpm,
        #[Title('Tempo prior')]
        #[Description('The prior the draft was transcribed under, with its source; null when there was none.')]
        #[MapName('tempo_prior')]
        public ?TempoPriorData $tempoPrior,
        #[Title('Meter')]
        #[Description('The draft\'s meter, e.g. "4/4".')]
        public string $meter,
        #[Title('Pickup (beats)')]
        #[Description('Beats before the downbeat of draft bar 0; 0 when there is no pickup.')]
        #[MapName('pickup_beats')]
        public float $pickupBeats,
        #[Title('Downbeat margin')]
        #[Description('How clearly the chosen downbeat phase beat the next one.')]
        #[MapName('downbeat_margin')]
        public float $downbeatMargin,
        #[Title('Chord changes')]
        #[Description('In the draft coordinate: start in beats from draft bar 0\'s downbeat; pickup changes are negative.')]
        #[DataCollectionOf(ChordChange::class)]
        public array $changes,
        #[Title('Change confidence')]
        #[Description('Confidence beside the changes, aligned by index.')]
        #[MapName('change_meta')]
        #[DataCollectionOf(DraftChangeMetaData::class)]
        public array $changeMeta,
        #[Title('Bars')]
        #[DataCollectionOf(DraftBarData::class)]
        public array $bars,
        #[Title('Review order')]
        #[Description('Bar indices by ascending confidence; the correction surface opens at the first.')]
        #[MapName('review_order')]
        #[ArrayItems('integer')]
        public array $reviewOrder,
    ) {
        if (! is_finite($pickupBeats) || $pickupBeats < 0) {
            throw new InvalidArgumentException("A chart draft's pickup_beats must be a non-negative number of beats, got {$pickupBeats}.");
        }

        $end = $this->checkBars($bars);
        $this->checkChanges($changes, $pickupBeats, $end);
        $this->checkIndices('change_meta', array_map(static fn (DraftChangeMetaData $meta): int => $meta->index, $changeMeta), count($changes), 'change');
        $this->checkIndices('review_order', $reviewOrder, count($bars), 'bar');
    }

    /** The draft's length in beats: Σ of its bars' beats. */
    public function totalBeats(): float
    {
        return array_sum(array_map(static fn (DraftBarData $bar): float => $bar->beats, $this->bars));
    }

    /**
     * @param  array<int, mixed>  $bars
     * @return float the beat where the last bar ends
     */
    private function checkBars(array $bars): float
    {
        if (! array_is_list($bars)) {
            throw new InvalidArgumentException('A chart draft\'s bars are a list.');
        }

        $expected = 0.0;
        foreach ($bars as $i => $bar) {
            if (! $bar instanceof DraftBarData) {
                throw new InvalidArgumentException("Draft bar {$i} is not a DraftBarData.");
            }
            if ($bar->index !== $i) {
                throw new InvalidArgumentException("Draft bar at position {$i} carries index {$bar->index}; bars are indexed 0…n−1 in order.");
            }
            if (! is_finite($bar->beats) || $bar->beats <= 0) {
                throw new InvalidArgumentException("Draft bar {$i} must be a positive number of beats, got {$bar->beats}.");
            }
            if (abs($bar->startBeat - $expected) > self::CONTIGUITY_EPSILON) {
                throw new InvalidArgumentException("Draft bar {$i} starts at beat {$bar->startBeat}, not {$expected} where the previous bar ends.");
            }
            $expected += $bar->beats;
        }

        return $expected;
    }

    /**
     * @param  array<int, mixed>  $changes
     */
    private function checkChanges(array $changes, float $pickupBeats, float $end): void
    {
        if (! array_is_list($changes)) {
            throw new InvalidArgumentException('A chart draft\'s changes are a list.');
        }

        $previous = null;
        foreach ($changes as $c => $change) {
            if (! $change instanceof ChordChange) {
                throw new InvalidArgumentException("Draft change {$c} is not a ChordChange.");
            }
            if (! is_finite($change->start)) {
                throw new InvalidArgumentException("Draft change {$c} start must be a finite number of beats.");
            }
            if ($previous !== null && $change->start <= $previous + self::EPSILON) {
                throw new InvalidArgumentException("Draft change {$c} (\"{$change->symbol}\") start {$change->start} is not after the previous change; changes are strictly increasing.");
            }
            if ($change->start < -$pickupBeats - self::EPSILON) {
                throw new InvalidArgumentException("Draft change {$c} (\"{$change->symbol}\") start {$change->start} is before the pickup (-{$pickupBeats}).");
            }
            if ($change->start >= $end - self::EPSILON) {
                throw new InvalidArgumentException("Draft change {$c} (\"{$change->symbol}\") start {$change->start} is past the last bar (the draft ends at beat {$end}).");
            }
            $previous = $change->start;
        }
    }

    /**
     * @param  array<int, mixed>  $indices
     */
    private function checkIndices(string $member, array $indices, int $count, string $what): void
    {
        $seen = [];
        foreach ($indices as $index) {
            if (! is_int($index) || $index < 0 || $index >= $count) {
                throw new InvalidArgumentException("A chart draft's {$member} names {$what} ".var_export($index, true).', which does not exist.');
            }
            if (isset($seen[$index])) {
                throw new InvalidArgumentException("A chart draft's {$member} names {$what} {$index} twice.");
            }
            $seen[$index] = true;
        }
    }
}
