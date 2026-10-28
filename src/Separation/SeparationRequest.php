<?php

namespace Splicewire\Composition\Music\Separation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Splicewire\Composition\Music\Analysis\AnalysisRequest;

/**
 * The `music.separate` input (music-capability-lift ticket 03 Q2), on the analyze shape it applies
 * ({@see AnalysisRequest}, ADR-0211): audio crosses by reference, never as inline binary; the caller names
 * the **stem set** it wants, never the separator; `params` are opaque; `binding` pins by name and fails
 * rather than falls back (03 Q1).
 *
 * The wire names are snake_case as ticket 03 writes them, mapped **per property**: laravel-data 4.23 lets
 * the host's configured input mapper (the flagship's CamelCaseMapper) override a class-level `#[MapName]`
 * (measured in build issue 03).
 */
#[TypeScript]
#[Title('Separation request')]
class SeparationRequest extends Data
{
    public function __construct(
        #[Title('Audio artifact reference')]
        #[Description('The audio to separate, by reference — a Media uuid or target_url; never inline binary.')]
        #[MapName('audio_ref')]
        public string $audioRef,
        #[Title('Stem set')]
        #[Description('The stem set wanted: two_stem (vocals, instrumental; the default) or six_stem (vocals, drums, bass, guitar, piano, other, instrumental). A bare string the spine never validates, for the reason measurement is one: an enum would mean a spine release per new set.')]
        #[MapName('stem_set')]
        public string $stemSet = 'two_stem',
        #[Title('Binding params')]
        #[Description('Opaque per-binding parameters. Carried and digested, never interpreted by this tier.')]
        /** @var array<string, mixed> */
        public array $params = [],
        #[Title('Explicit binding')]
        #[Description('Optional binding name that MUST serve this request; a pin fails rather than falls back.')]
        public ?string $binding = null,
    ) {}

    /**
     * The stable digest of {@see self::$params}, the `params digest` member of the five-field separation run
     * key `(media, stem_set, binding name, binding version, params digest)` (03 Q2). The same rule as
     * {@see AnalysisRequest::paramsDigest()}: key order must not change it, so keys are sorted, deeply.
     */
    public function paramsDigest(): string
    {
        return substr(hash('sha256', json_encode(self::sortDeep($this->params)) ?: '[]'), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private static function sortDeep(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sortDeep($item);
            }
        }

        return $value;
    }
}
