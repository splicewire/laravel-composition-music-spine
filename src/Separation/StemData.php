<?php

namespace Splicewire\Composition\Music\Separation;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One stored stem of a separation run (music-capability-lift ticket 03 Q2, E1): a lossless media row on the
 * source media's owner, by reference, with the levels measured over the bytes the runner wrote.
 *
 * `relative_db` is the stem's RMS against the loudest stem **in its own physical run** (the two-stem run, or
 * the instrument leg), with no cross-run normalisation. It is null only when that run has no reference — every
 * stem in it silent (03 E1). Whether a stem is *signal* is the consumer's gate, never this tier's.
 *
 * The wire names are snake_case, mapped per property (see {@see SeparationRequest}).
 */
#[TypeScript]
#[Title('Stem')]
class StemData extends Data
{
    public function __construct(
        #[Title('Name')]
        #[Description('The stem: vocals, instrumental, drums, bass, guitar, piano or other.')]
        public string $name,
        #[Title('Media ref')]
        #[Description('The stem media uuid the caller fetches the bytes by.')]
        #[MapName('media_ref')]
        public string $mediaRef,
        #[Title('Peak dBFS')]
        #[Description('Sample peak of the stored bytes, in dBFS.')]
        #[MapName('peak_dbfs')]
        public float $peakDbfs,
        #[Title('RMS dBFS')]
        #[Description('RMS level of the stored bytes, in dBFS.')]
        #[MapName('rms_dbfs')]
        public float $rmsDbfs,
        #[Title('Relative dB')]
        #[Description('RMS against the loudest stem in its own physical run; null only when every stem in that run is silent.')]
        #[MapName('relative_db')]
        public ?float $relativeDb,
        #[Title('Silent')]
        #[Description('True only at the digital-silence precedent (peak at or below -60 dBFS). Not a signal gate.')]
        public bool $silent,
    ) {}
}
