<?php

namespace Splicewire\Composition\Music\Conformance;

use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\Contracts\MusicConformance;
use Splicewire\Composition\Music\MelodyNote;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;

/**
 * The BUILT PHP-native conformance checks (ADR-0125 §9 / PRD §11.2) — the thin rules layered on the
 * OTIO-validator popcorn shape, needing no external library. It is the opt-in real binding over
 * {@see MusicConformance} (the default stays {@see NullMusicConformance}); the deeper Tonal/`music21`
 * pass is a separate config-gated popcorn Process override.
 *
 * **Mandatory checks (block the render):**
 *  - MIDI note range: every melody pitch in 0–127;
 *  - tempo sanity: song + per-section tempo strictly positive;
 *  - meter sanity: song + per-section meter parses as `n/d`, numerator > 0, denominator a power of two;
 *  - bar math: every section has at least one chord (bar count = chord count ≥ 1);
 *  - melody timing: duration > 0, and start no earlier than the section allows — `-pickup` in section 0
 *    (0 without a pickup), one bar of the previous section's meter in a later section (a lead-in);
 *  - pickup (v2): not negative;
 *  - chord changes (v2): `start` ≥ 0, except the **pickup chord** — a section-0 change with `-pickup ≤ start < 0`
 *    (build/26, ticket 05 E3) — strictly increasing, inside the section, a non-empty symbol, and **downbeat
 *    consistency** — `chords[i]` equals the change sounding at bar *i*'s downbeat (music-capability-lift ticket
 *    02 Q1). A pickup chord still sounding at bar 0's downbeat is `chords[0]`.
 *
 * Bars are measured in beats (quarter notes, as {@see MelodyNote}) in the section's effective meter: its own
 * override, else the latest earlier override, else the song meter ("from this section onward").
 *
 * **Advisory checks (surfaced, never block):**
 *  - in-key coherence: a thin diatonic membership test of melody pitch-classes against the key. This is
 *    the pure-PHP floor the PRD flags Tonal/`music21` would deepen (spelling, borrowed chords, modes).
 *
 * The mandatory-vs-advisory split is the build-effort call recorded in issue 07.
 */
class StructuralMusicConformance implements MusicConformance
{
    /** Semitone offsets of the major and (natural) minor scales from the tonic. */
    private const MAJOR = [0, 2, 4, 5, 7, 9, 11];

    private const MINOR = [0, 2, 3, 5, 7, 8, 10];

    /** Beat comparisons tolerate float noise (a beat is a quarter note; 1e-9 is far below any real onset). */
    private const EPSILON = 1e-9;

    /** Pitch class of each note name (sharps + flats). */
    private const PITCH_CLASS = [
        'C' => 0, 'B#' => 0, 'C#' => 1, 'Db' => 1, 'D' => 2, 'D#' => 3, 'Eb' => 3,
        'E' => 4, 'Fb' => 4, 'F' => 5, 'E#' => 5, 'F#' => 6, 'Gb' => 6, 'G' => 7,
        'G#' => 8, 'Ab' => 8, 'A' => 9, 'A#' => 10, 'Bb' => 10, 'B' => 11, 'Cb' => 11,
    ];

    public function check(MusicIntent $intent): ConformanceResult
    {
        $mandatory = [];
        $advisory = [];

        if ($intent->tempo <= 0) {
            $mandatory[] = "Song tempo must be positive, got {$intent->tempo}.";
        }
        $this->checkMeter('Song', $intent->meter, $mandatory);

        $tonicPc = self::PITCH_CLASS[$intent->key->tonic] ?? null;
        $scale = $intent->key->mode === 'minor' ? self::MINOR : self::MAJOR;
        $inKey = $tonicPc === null ? null : array_map(static fn (int $s): int => ($tonicPc + $s) % 12, $scale);

        if ($tonicPc === null) {
            $mandatory[] = "Unrecognized key tonic \"{$intent->key->tonic}\".";
        }

        $pickup = $intent->pickupBeats();
        if (! is_finite($pickup) || $pickup < 0) {
            $mandatory[] = "The pickup {$pickup} must be a non-negative number of beats.";
            $pickup = 0.0;
        }

        $bars = self::sectionBars($intent);
        $floors = self::earliestStarts($intent);

        foreach ($intent->sections as $i => $section) {
            if (! $section instanceof MusicSection) {
                continue;
            }
            $where = "Section {$i} ({$section->tag})";
            $bar = $bars[$i];
            $floor = $floors[$i];

            $this->checkChanges($where, $section, $bar, $i === 0 ? -$pickup : 0.0, $mandatory);

            if (count($section->chords) < 1) {
                $mandatory[] = "{$where} has no chords (a section must be at least one bar).";
            }

            if (is_int($section->tempo) && $section->tempo <= 0) {
                $mandatory[] = "{$where} tempo override must be positive, got {$section->tempo}.";
            }
            if (is_string($section->meter)) {
                $this->checkMeter($where, $section->meter, $mandatory);
            }

            if (is_array($section->melody)) {
                foreach ($section->melody as $n => $note) {
                    if (! $note instanceof MelodyNote) {
                        continue;
                    }
                    if ($note->pitch < 0 || $note->pitch > 127) {
                        $mandatory[] = "{$where} melody note {$n} pitch {$note->pitch} is outside MIDI range 0–127.";
                    }
                    if ($note->start < $floor - self::EPSILON) {
                        $mandatory[] = $floor === 0.0
                            ? "{$where} melody note {$n} start {$note->start} is negative."
                            : "{$where} melody note {$n} start {$note->start} is before {$floor}, the earliest this section allows.";
                    }
                    if ($note->duration <= 0) {
                        $mandatory[] = "{$where} melody note {$n} duration {$note->duration} must be positive.";
                    }
                    if ($inKey !== null && $note->pitch >= 0 && $note->pitch <= 127 && ! in_array($note->pitch % 12, $inKey, true)) {
                        $advisory[] = "{$where} melody note {$n} (pitch {$note->pitch}) is out of key.";
                    }
                }
            }
        }

        return new ConformanceResult(mandatory: $mandatory, advisory: $advisory);
    }

    /**
     * The v2 chord-change floor for one section. `$bar` is the section's bar length in beats, or null when
     * its effective meter does not parse (that is already a meter violation; bar positions are then unknown).
     * `$earliest` is the earliest start a change may have: `-pickup` in section 0 (the pickup chord, build/26),
     * 0 everywhere else — a change never leads in from the previous section the way a melody note may.
     *
     * @param  array<int, string>  $mandatory
     */
    private function checkChanges(string $where, MusicSection $section, ?float $bar, float $earliest, array &$mandatory): void
    {
        $changes = $section->changeList();
        if ($changes === []) {
            return;
        }

        $ordered = true;
        $previous = null;
        foreach ($changes as $c => $change) {
            if (! $change instanceof ChordChange) {
                return;
            }
            if (trim($change->symbol) === '') {
                $mandatory[] = "{$where} chord change {$c} has an empty symbol.";
            }
            if (! is_finite($change->start)) {
                $mandatory[] = "{$where} chord change {$c} start must be a finite number of beats.";
                $ordered = false;

                continue;
            }
            if ($change->start < $earliest - self::EPSILON) {
                $mandatory[] = $earliest === 0.0
                    ? "{$where} chord change {$c} (\"{$change->symbol}\") start {$change->start} is negative; a change sounds from the section's first downbeat onward, and only section 0's pickup admits one before it."
                    : "{$where} chord change {$c} (\"{$change->symbol}\") start {$change->start} is before {$earliest}, the start of the pickup.";
            }
            if ($previous !== null && $change->start <= $previous + self::EPSILON) {
                $mandatory[] = "{$where} chord change {$c} (\"{$change->symbol}\") start {$change->start} is not after the previous change; changes must be in strictly increasing start order.";
                $ordered = false;
            }
            if ($bar !== null && $change->start >= count($section->chords) * $bar - self::EPSILON) {
                $mandatory[] = "{$where} chord change {$c} (\"{$change->symbol}\") start {$change->start} is past the section's last bar.";
            }
            $previous = $change->start;
        }

        if (! $ordered || $bar === null) {
            return;
        }

        // Downbeat consistency: chords[i] is the change sounding at bar i's downbeat.
        foreach (array_values($section->chords) as $b => $chord) {
            $downbeat = $b * $bar;
            $sounding = null;
            foreach ($changes as $change) {
                if ($change->start <= $downbeat + self::EPSILON) {
                    $sounding = $change->symbol;
                }
            }

            if ($sounding === null) {
                $mandatory[] = "{$where} bar {$b} has no chord change sounding at its downbeat (chords[{$b}] is \"{$chord}\").";
            } elseif ($sounding !== $chord) {
                $mandatory[] = "{$where} bar {$b}: chords[{$b}] is \"{$chord}\" but the change sounding at its downbeat is \"{$sounding}\".";
            }
        }
    }

    /**
     * Each section's bar length in beats in its effective meter — its own override, else the latest earlier one,
     * else the song meter — or null where that meter does not parse. Keyed as `$intent->sections`.
     *
     * @return array<int, ?float>
     */
    public static function sectionBars(MusicIntent $intent): array
    {
        $meter = $intent->meter;
        $bars = [];
        foreach ($intent->sections as $i => $section) {
            if ($section instanceof MusicSection && is_string($section->meter)) {
                $meter = $section->meter;
            }
            $bars[$i] = self::barBeats($meter);
        }

        return $bars;
    }

    /**
     * The earliest start, in beats from its section's start, a melody note — or a timed syllable onset (build/26,
     * ticket 05 E3) — may have in each section: `-pickup` in section 0 (0 without a pickup), and one bar of the
     * previous section's meter in a later section (a lead-in sounding in that section's last bar). An invalid
     * pickup counts as none. Keyed as `$intent->sections`.
     *
     * @return array<int, float>
     */
    public static function earliestStarts(MusicIntent $intent): array
    {
        $pickup = $intent->pickupBeats();
        $pickup = is_finite($pickup) && $pickup > 0 ? $pickup : 0.0;

        $floors = [];
        $previousBar = null;
        $first = true;
        foreach (self::sectionBars($intent) as $i => $bar) {
            $floors[$i] = $first ? -$pickup : -($previousBar ?? 0.0);
            $previousBar = $bar;
            $first = false;
        }

        return $floors;
    }

    /** A bar's length in beats (quarter notes) for an `n/d` meter, or null when the meter does not parse. */
    private static function barBeats(string $meter): ?float
    {
        if (! preg_match('/^(\d+)\/(\d+)$/', $meter, $m) || (int) $m[1] < 1 || (int) $m[2] < 1) {
            return null;
        }

        return (int) $m[1] * 4 / (int) $m[2];
    }

    /**
     * @param  array<int, string>  $mandatory
     */
    private function checkMeter(string $where, string $meter, array &$mandatory): void
    {
        if (! preg_match('/^(\d+)\/(\d+)$/', $meter, $m)) {
            $mandatory[] = "{$where} meter \"{$meter}\" is not a valid n/d time signature.";

            return;
        }

        $numerator = (int) $m[1];
        $denominator = (int) $m[2];

        if ($numerator < 1) {
            $mandatory[] = "{$where} meter \"{$meter}\" numerator must be at least 1.";
        }
        if ($denominator < 1 || ($denominator & ($denominator - 1)) !== 0) {
            $mandatory[] = "{$where} meter \"{$meter}\" denominator must be a power of two.";
        }
    }
}
