<?php

namespace Splicewire\Composition\Music\Notation;

use Schemastud\DataSchemas\Attributes\ArrayItems;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MelodyNote;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;
use Splicewire\Composition\Music\Support\CanonicalJson;

/**
 * Everything a notation run engraves (music-capability-lift ticket 02 Q1, Q2 amended; ticket 09). The rule
 * that splits it from the intent: **a fact about the music goes in {@see MusicIntent}, a fact about the page
 * goes here** — credits, section labels and directions, lyric lines, syllable onsets, cues, the formats and
 * the arrangement. The intent crosses **inline**, as the chart the caller projected at export time; it is
 * never read off the composition.
 *
 * Refuses at construction, by name ({@see NotationRequestRefused}): an empty `formats`, `sections` that do not
 * align with the intent's, syllables that break the stage-2 contract ({@see NotationSyllable}; build/26), and a lyric
 * block that has no paged format or no lines.
 *
 * **Underlay per section** (build/26, ticket 05 Q4 amended). A section whose intent section has melody underlays its
 * syllables: each is timed and a melody note starts at its onset — in the score's coordinate, so a lead-in syllable
 * may sit on a note its previous section carries, as the engraving reads it. A section without melody underlays
 * nothing; its syllables serve the chart, and only those under a chord change carry a `start`: each timed one starts
 * where a change of its own section does or, as a lead-in, one of the previous section (orchestrator reading R4). A
 * section without `changes` has its v1 changes, one per bar at each downbeat.
 *
 * {@see self::digest()} is the request half of the notation run key, computed identically by every caller
 * and by the platform because it lives here.
 */
#[TypeScript]
#[Title('Notation request')]
class NotationRequest extends Data
{
    /** A syllable sits on a note when their onsets agree within a millionth of a beat, as the engraving matches them. */
    private const ONSET_EPSILON = 1e-6;

    /**
     * @param  array<int, NotationSection>  $sections
     * @param  array<int, NotationFormat>  $formats
     */
    public function __construct(
        #[Title('Intent')]
        #[Description('The music to engrave (music/intent v2), inline: the owner-confirmed chart as projected at export time.')]
        public MusicIntent $intent,

        #[Title('Source')]
        #[Description('Provenance only; recorded on the produced artifacts and excluded from the digest.')]
        public NotationSource $source,

        #[Title('Title')]
        public string $title,

        #[Title('Sections')]
        #[Description('What is printed per intent section, aligned by index; the count must equal the intent section count.')]
        #[DataCollectionOf(NotationSection::class)]
        public array $sections,

        #[Title('Formats')]
        #[Description('The outputs to produce: musicxml | svg | pdf | chart. Required and non-empty; no default.')]
        #[ArrayItems(NotationFormat::class)]
        public array $formats,

        #[Title('Arrangement')]
        #[Description('lead_sheet (one staff) or piano (grand staff).')]
        public NotationArrangement $arrangement,

        #[Title('Composer')]
        public ?string $composer = null,

        #[Title('Lyricist')]
        public ?string $lyricist = null,

        #[Title('Lyric block')]
        #[Description('Print the section lyric lines as verse text after the music (svg/pdf only). Absent means false.')]
        public bool $lyricBlock = false,
    ) {
        $this->formats = array_values(array_map(
            static fn (NotationFormat|string $format): NotationFormat => $format instanceof NotationFormat ? $format : NotationFormat::from($format),
            $formats,
        ));

        if ($this->formats === []) {
            throw new NotationRequestRefused(NotationRequestRefused::FORMATS_EMPTY, 'formats must name at least one of musicxml, svg, pdf, chart.');
        }

        $expected = count($intent->sections);
        if (count($sections) !== $expected) {
            throw new NotationRequestRefused(NotationRequestRefused::SECTIONS_MISMATCH, 'sections has '.count($sections)." entries; the intent has {$expected}.");
        }

        self::checkSyllables($intent, $sections);

        if ($lyricBlock) {
            $paged = array_filter($this->formats, static fn (NotationFormat $format): bool => $format->isPaged());
            if ($paged === []) {
                throw new NotationRequestRefused(NotationRequestRefused::LYRIC_BLOCK_WITHOUT_PAGES, 'a lyric block prints only on svg or pdf pages.');
            }

            $withLines = array_filter($sections, static fn (NotationSection $section): bool => $section->lines !== []);
            if ($withLines === []) {
                throw new NotationRequestRefused(NotationRequestRefused::LYRIC_BLOCK_EMPTY, 'no section has lyric lines to print.');
            }
        }
    }

    /**
     * The stage-2 syllable contract, section by section (build/26): order, then each timed syllable's floor, then its
     * anchor — a melody note in a section with melody, a chord change in a section without (R4).
     *
     * @param  array<int, NotationSection>  $sections  aligned with the intent's sections
     */
    private static function checkSyllables(MusicIntent $intent, array $sections): void
    {
        $intentSections = array_values($intent->sections);
        $floors = array_values(StructuralMusicConformance::earliestStarts($intent));
        $onsets = self::onsets($intentSections, array_values(StructuralMusicConformance::sectionBars($intent)));

        foreach (array_values($sections) as $k => $section) {
            if (! $section instanceof NotationSection || $section->syllables === []) {
                continue;
            }
            $intentSection = $intentSections[$k];
            $withMelody = $intentSection instanceof MusicSection && is_array($intentSection->melody) && $intentSection->melody !== [];

            $previous = null;
            foreach (array_values($section->syllables) as $s => $syllable) {
                if ($syllable->start === null) {
                    if ($withMelody) {
                        throw new NotationRequestRefused(NotationRequestRefused::SYLLABLE_UNTIMED,
                            "section {$k} syllable {$s} (\"{$syllable->text}\") has no start; a section with melody underlays every syllable on a note.");
                    }

                    continue;
                }

                if ($previous !== null && $syllable->start <= $previous + self::ONSET_EPSILON) {
                    throw new NotationRequestRefused(NotationRequestRefused::SYLLABLES_OUT_OF_ORDER,
                        "section {$k} syllable {$s} (\"{$syllable->text}\") starts at {$syllable->start}, not after the previous timed syllable at {$previous}; syllables are the section's units in order.");
                }
                $previous = $syllable->start;

                if ($syllable->start < $floors[$k] - self::ONSET_EPSILON) {
                    throw new NotationRequestRefused(NotationRequestRefused::SYLLABLE_TOO_EARLY,
                        "section {$k} syllable {$s} (\"{$syllable->text}\") starts at {$syllable->start}, before {$floors[$k]}, the earliest this section allows.");
                }

                if ($withMelody && ! self::startsAt($onsets['notes'], $onsets['origins'], $k, $syllable->start)) {
                    throw new NotationRequestRefused(NotationRequestRefused::SYLLABLE_WITHOUT_NOTE,
                        "section {$k} syllable {$s} (\"{$syllable->text}\") at beat {$syllable->start} has no melody note starting there.");
                }

                if (! $withMelody && ! self::startsAt($onsets['changes'], $onsets['origins'], $k, $syllable->start, [$k, $k - 1])) {
                    throw new NotationRequestRefused(NotationRequestRefused::SYLLABLE_OFF_CHORD,
                        "section {$k} syllable {$s} (\"{$syllable->text}\") at beat {$syllable->start} is under no chord change; in a section without melody only a unit under a change (of this section, or of the previous one for a lead-in) has a start.");
                }
            }
        }
    }

    /**
     * Every melody note's and every chord change's onset, by section, and each section's origin in score beats (section
     * 0's origin is its first downbeat). A section without `changes` has its v1 changes: one per bar, at each downbeat.
     * An origin after a section whose bar length is unknown (its meter does not parse) is null; an onset there is matched
     * in its own section only.
     *
     * @param  list<mixed>  $sections
     * @param  list<?float>  $bars
     * @return array{notes: list<array{section: int, start: float}>, changes: list<array{section: int, start: float}>, origins: list<?float>}
     */
    private static function onsets(array $sections, array $bars): array
    {
        $origins = [];
        $origin = 0.0;
        $notes = [];
        $changes = [];

        foreach ($sections as $k => $section) {
            $origins[$k] = $origin;
            if (! $section instanceof MusicSection) {
                $origin = null;

                continue;
            }
            $origin = $origin !== null && $bars[$k] !== null ? $origin + count($section->chords) * $bars[$k] : null;

            foreach (is_array($section->melody) ? $section->melody : [] as $note) {
                if ($note instanceof MelodyNote) {
                    $notes[] = ['section' => $k, 'start' => $note->start];
                }
            }

            $declared = $section->changeList();
            if ($declared !== []) {
                foreach ($declared as $change) {
                    $changes[] = ['section' => $k, 'start' => $change->start];
                }
            } elseif ($bars[$k] !== null) {
                foreach (array_keys(array_values($section->chords)) as $b) {
                    $changes[] = ['section' => $k, 'start' => $b * $bars[$k]];
                }
            }
        }

        return ['notes' => $notes, 'changes' => $changes, 'origins' => $origins];
    }

    /**
     * Whether one of `$onsets` starts at a section's beat: in that section at the same start, or in score beats at the
     * same beat. `$from` limits the sections an onset may come from; null admits every section.
     *
     * @param  list<array{section: int, start: float}>  $onsets
     * @param  list<?float>  $origins
     * @param  list<int>|null  $from
     */
    private static function startsAt(array $onsets, array $origins, int $section, float $start, ?array $from = null): bool
    {
        $origin = $origins[$section];

        foreach ($onsets as $onset) {
            if ($from !== null && ! in_array($onset['section'], $from, true)) {
                continue;
            }
            if ($onset['section'] === $section && abs($onset['start'] - $start) < self::ONSET_EPSILON) {
                return true;
            }
            $onsetOrigin = $origins[$onset['section']];
            if ($origin !== null && $onsetOrigin !== null && abs($onsetOrigin + $onset['start'] - ($origin + $start)) < self::ONSET_EPSILON) {
                return true;
            }
        }

        return false;
    }

    /**
     * The request's content digest: sha256 of the canonical JSON — keys sorted at every level,
     * `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE` — of every member **except `source`** (ticket 02 Q2
     * amended). It is taken over the hydrated Data, so an absent member with a default digests as that
     * default (an absent `lyricBlock` equals `false`), and over property names, so a host's output name mapper
     * cannot move it. The intent's two v2 `Optional` members are normalised to their v1 meaning first: a
     * `pickup` of 0 is no pickup and an empty `changes` is no changes, so a v1 intent migrated to v2 (the
     * schema ladder writes `pickup: 0`) digests as the same intent hydrated directly.
     *
     * Any change to a note, chord, syllable, label, credit, format, the arrangement or the lyric block is a
     * new digest; a change to `source` alone is not.
     */
    public function digest(): string
    {
        $payload = CanonicalJson::propertiesOf($this);
        unset($payload['source']);
        $payload['intent'] = CanonicalJson::withoutEmptyIntentV2Members($payload['intent']);

        return CanonicalJson::sha256($payload);
    }
}
