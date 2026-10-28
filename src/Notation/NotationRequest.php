<?php

namespace Splicewire\Composition\Music\Notation;

use BackedEnum;
use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\MusicIntent;

/**
 * Everything a notation run engraves (music-capability-lift ticket 02 Q1, Q2 amended; ticket 09). The rule
 * that splits it from the intent: **a fact about the music goes in {@see MusicIntent}, a fact about the page
 * goes here** — credits, section labels and directions, lyric lines, syllable onsets, cues, the formats and
 * the arrangement. The intent crosses **inline**, as the chart the caller projected at export time; it is
 * never read off the composition.
 *
 * Refuses at construction, by name ({@see NotationRequestRefused}): an empty `formats`, `sections` that do not
 * align with the intent's, and a lyric block that has no paged format or no lines.
 *
 * {@see self::digest()} is the request half of the notation run key, computed identically by every caller
 * and by the platform because it lives here.
 */
#[TypeScript]
#[Title('Notation request')]
class NotationRequest extends Data
{
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
     * The request's content digest: sha256 of the canonical JSON — keys sorted at every level,
     * `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE` — of every member **except `source`** (ticket 02 Q2
     * amended). It is taken over the hydrated Data, so an absent member digests as its default (an absent
     * `lyricBlock` equals `false`), and over property names, so a host's output name mapper cannot move it.
     *
     * Any change to a note, chord, syllable, label, credit, format, the arrangement or the lyric block is a
     * new digest; a change to `source` alone is not.
     */
    public function digest(): string
    {
        $payload = $this->transform(
            TransformationContextFactory::create()->withoutPropertyNameMapping()->withoutWrapping(),
        );
        unset($payload['source']);

        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            $json = json_encode(self::canonical($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } finally {
            ini_set('serialize_precision', $precision === false ? '-1' : $precision);
        }

        return hash('sha256', $json);
    }

    /** Sort every associative level by key; lists keep their order (order is meaning there). */
    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
