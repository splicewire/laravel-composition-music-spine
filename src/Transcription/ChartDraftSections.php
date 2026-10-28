<?php

namespace Splicewire\Composition\Music\Transcription;

use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;

/**
 * **The conversion after sectioning** (music-capability-lift ticket 04 Q2; placed in the spine by ticket 05 E1): one
 * pure function, {@see self::apply()}, from a {@see ChartDraftData} and the owner's sectioning to v2
 * {@see MusicSection}s. It lives here, not in the engine, because the satellite holding the sectioning (the chart
 * worksheet's parser) reaches only the spines.
 *
 * What it does, in 04's order:
 * 1. **Origins.** Section *k*'s origin is `O_k = Σ beats` of the draft bars before its `start_bar`, accumulated per
 *    bar, so a meter change at a section start shifts every later origin.
 * 2. **Changes.** A draft change at `s` with `O_k ≤ s < O_{k+1}` becomes `ChordChange{symbol, s − O_k}` in section
 *    *k*. A chord still sounding from the previous section is restated at the section's beat 0, so every section
 *    carries the change sounding at its own first downbeat.
 * 3. **`chords[]`.** Each bar's chord is the last change at or before its downbeat, so 02's downbeat floor
 *    ({@see StructuralMusicConformance}) holds by construction.
 * 4. **Meter.** A section's meter is its first bar's; a bar inside the section of another length refuses by name.
 *    The draft's meter is the song meter; a section whose meter differs from the one in force carries it as its
 *    override ("from this section onward").
 * 5. **Pickup.** {@see self::$pickup} is the draft's `pickup_beats`. A change in the pickup (`start` < 0) has no v2
 *    place until issue 26, so it refuses by name and is listed, never shifted to beat 0.
 * 6. **Confidence** (`change_meta`, bar confidence and reasons) stays on the draft; nothing here carries it.
 *
 * Every refusal is a {@see ChartDraftSectionsRefused} with a stable reason.
 */
class ChartDraftSections
{
    private const EPSILON = ChartDraftData::EPSILON;

    /** The largest denominator a derived section meter is written on. */
    private const MAX_DENOMINATOR = 64;

    /**
     * @param  list<MusicSection>  $sections  one per sectioning entry, in order
     * @param  list<float>  $origins  each section's origin in draft beats
     * @param  float  $pickup  the intent's `pickup`: the draft's `pickup_beats`
     * @param  string  $meter  the song meter the sections' overrides are relative to: the draft's meter
     */
    public function __construct(
        public array $sections,
        public array $origins,
        public float $pickup,
        public string $meter,
    ) {}

    /**
     * The owner's sectioning applied to a draft. The sectioning is `list<{tag, label, start_bar}>`, ascending from
     * bar 0, as {@see SectionStartData} or plain arrays; the result's sections align with it one to one, so a caller
     * keeps each label beside its section.
     *
     * The intent the result belongs to is `MusicIntent{key: draft key, sections, meter: $meter, pickup: $pickup}`
     * with the caller's integer tempo ({@see MusicIntent}).
     *
     * @param  array<int, SectionStartData|array{tag: string, label: string, start_bar: int}>  $sectioning
     *
     * @throws ChartDraftSectionsRefused
     */
    public static function apply(ChartDraftData $draft, array $sectioning): self
    {
        $starts = array_values(array_map(
            static fn (SectionStartData|array $s): SectionStartData => $s instanceof SectionStartData ? $s : SectionStartData::from($s),
            $sectioning,
        ));
        $bars = array_values($draft->bars);
        $changes = array_values($draft->changes);

        self::checkSectioning($starts, count($bars));
        self::refusePickupChords($changes);

        // Downbeats in draft beats, accumulated per bar (04 Q2 step 1).
        $downbeats = [];
        $at = 0.0;
        foreach ($bars as $bar) {
            $downbeats[] = $at;
            $at += $bar->beats;
        }
        $end = $at;

        // Each section's last bar: the bar before the next section starts, or the draft's last bar.
        $lastBars = [];
        foreach ($starts as $k => $start) {
            $lastBars[$k] = ($starts[$k + 1]->startBar ?? count($bars)) - 1;
        }

        $sectionMeters = [];
        foreach ($starts as $k => $start) {
            $last = $lastBars[$k];
            $first = $bars[$start->startBar]->beats;

            for ($b = $start->startBar + 1; $b <= $last; $b++) {
                if (abs($bars[$b]->beats - $first) > self::EPSILON) {
                    throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::METER_CHANGE_INSIDE_SECTION,
                        "bar {$b} is {$bars[$b]->beats} beats inside section {$k} (\"{$start->label}\"), whose first bar (bar {$start->startBar}) is {$first}; start a section at bar {$b}.");
                }
            }

            $sectionMeters[] = self::meterOf($first, $draft->meter, $k, $start);
        }

        $chords = [];
        $unsounded = [];
        foreach ($downbeats as $b => $downbeat) {
            $sounding = self::soundingAt($changes, $downbeat);
            if ($sounding === null) {
                $unsounded[] = "bar {$b}";
            }
            $chords[$b] = $sounding?->symbol;
        }
        if ($unsounded !== []) {
            throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::BAR_WITHOUT_CHORD,
                'no chord change sounds at the downbeat of '.implode(', ', $unsounded).'; the first change is the first chord.');
        }

        $sections = [];
        $origins = [];
        $meterInForce = $draft->meter;

        foreach ($starts as $k => $start) {
            $origin = $downbeats[$start->startBar];
            $next = isset($starts[$k + 1]) ? $downbeats[$starts[$k + 1]->startBar] : $end;

            $sectionChanges = [];
            foreach ($changes as $change) {
                if ($change->start >= $origin - self::EPSILON && $change->start < $next - self::EPSILON) {
                    $relative = $change->start - $origin;
                    $sectionChanges[] = new ChordChange(symbol: $change->symbol, start: abs($relative) < self::EPSILON ? 0.0 : $relative);
                }
            }

            if ($sectionChanges === [] || $sectionChanges[0]->start > 0.0) {
                // A chord sounding across the boundary: restate it at the section's first downbeat.
                $carried = self::soundingAt($changes, $origin);
                array_unshift($sectionChanges, new ChordChange(symbol: $carried->symbol, start: 0.0));
            }

            $sectionChords = array_values(array_slice($chords, $start->startBar, $lastBars[$k] - $start->startBar + 1));

            $arguments = ['tag' => $start->tag, 'chords' => $sectionChords, 'roles' => [], 'changes' => $sectionChanges];
            if ($sectionMeters[$k] !== $meterInForce) {
                $arguments['meter'] = $sectionMeters[$k];
                $meterInForce = $sectionMeters[$k];
            }

            $sections[] = new MusicSection(...$arguments);
            $origins[] = $origin;
        }

        return new self(sections: $sections, origins: $origins, pickup: $draft->pickupBeats, meter: $draft->meter);
    }

    /**
     * @param  list<SectionStartData>  $starts
     */
    private static function checkSectioning(array $starts, int $barCount): void
    {
        if ($starts === []) {
            throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::SECTIONING_EMPTY, 'the sectioning names no section.');
        }

        if ($starts[0]->startBar !== 0) {
            throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::SECTIONING_NOT_FROM_BAR_ZERO,
                "the first section (\"{$starts[0]->label}\") starts at bar {$starts[0]->startBar}; the sectioning starts at bar 0.");
        }

        foreach ($starts as $k => $start) {
            if ($k > 0 && $start->startBar <= $starts[$k - 1]->startBar) {
                throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::SECTIONING_NOT_ASCENDING,
                    "section {$k} (\"{$start->label}\") starts at bar {$start->startBar}, not after section ".($k - 1)."'s bar {$starts[$k - 1]->startBar}.");
            }
            if ($start->startBar >= $barCount) {
                throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::SECTION_PAST_LAST_BAR,
                    "section {$k} (\"{$start->label}\") starts at bar {$start->startBar}; the draft has {$barCount} bars.");
            }
        }
    }

    /**
     * @param  list<ChordChange>  $changes
     */
    private static function refusePickupChords(array $changes): void
    {
        $pickupBorne = array_filter($changes, static fn (ChordChange $c): bool => $c->start < -self::EPSILON);

        if ($pickupBorne !== []) {
            $named = implode(', ', array_map(
                static fn (int $i, ChordChange $c): string => "change {$i} \"{$c->symbol}\" at {$c->start}",
                array_keys($pickupBorne), $pickupBorne,
            ));

            throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::PICKUP_CHORD,
                "{$named} sounds in the pickup, which has no place for a chord until issue 26; it is not moved to beat 0.",
                $pickupBorne);
        }
    }

    /**
     * The last change at or before a draft beat, or null before the first change.
     *
     * @param  list<ChordChange>  $changes
     */
    private static function soundingAt(array $changes, float $beat): ?ChordChange
    {
        $sounding = null;
        foreach ($changes as $change) {
            if ($change->start <= $beat + self::EPSILON) {
                $sounding = $change;
            }
        }

        return $sounding;
    }

    /**
     * A section's meter from its bar length: the draft meter when the bar is that meter's length, else `n/d` on the
     * draft meter's denominator, doubled until `n` is whole (a 4.5-beat bar in 6/8 is 9/8; a 2.5-beat bar in 4/4 is 5/8).
     */
    private static function meterOf(float $beats, string $draftMeter, int $k, SectionStartData $start): string
    {
        $denominator = 4;
        if (preg_match('/^(\d+)\/(\d+)$/', $draftMeter, $m) && (int) $m[1] > 0 && (int) $m[2] > 0) {
            if (abs((int) $m[1] * 4 / (int) $m[2] - $beats) <= self::EPSILON) {
                return $draftMeter;
            }
            $denominator = (int) $m[2];
        }

        for ($d = $denominator; $d <= self::MAX_DENOMINATOR; $d *= 2) {
            $n = $beats * $d / 4;
            if (abs($n - round($n)) <= self::EPSILON && round($n) >= 1) {
                return ((int) round($n)).'/'.$d;
            }
        }

        throw new ChartDraftSectionsRefused(ChartDraftSectionsRefused::METER_UNREPRESENTABLE,
            "section {$k} (\"{$start->label}\")'s bars are {$beats} beats, which no n/d up to /".self::MAX_DENOMINATOR.' on the draft meter writes.');
    }
}
