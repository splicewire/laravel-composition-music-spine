<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Conformance;

use Splicewire\CompositionMusicSpineData\Contracts\MusicConformance;
use Splicewire\CompositionMusicSpineData\MelodyNote;
use Splicewire\CompositionMusicSpineData\MusicIntent;
use Splicewire\CompositionMusicSpineData\MusicSection;

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
 *  - melody timing: start ≥ 0, duration > 0.
 *
 * **Advisory checks (surfaced, never block):**
 *  - in-key coherence: a thin diatonic membership test of melody pitch-classes against the key. This is
 *    the pure-PHP floor the PRD flags Tonal/`music21` would deepen (spelling, borrowed chords, modes).
 *
 * The mandatory-vs-advisory split is the build-effort call recorded in issue 07.
 */
final class StructuralMusicConformance implements MusicConformance
{
    /** Semitone offsets of the major and (natural) minor scales from the tonic. */
    private const MAJOR = [0, 2, 4, 5, 7, 9, 11];

    private const MINOR = [0, 2, 3, 5, 7, 8, 10];

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

        foreach ($intent->sections as $i => $section) {
            if (! $section instanceof MusicSection) {
                continue;
            }
            $where = "Section {$i} ({$section->tag})";

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
                    if ($note->start < 0) {
                        $mandatory[] = "{$where} melody note {$n} start {$note->start} is negative.";
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
