<?php

namespace Splicewire\Composition\Music\Repair;

use InvalidArgumentException;
use Rushing\TimelineSchema\Objects\RationalTime;
use Rushing\TimelineSchema\Objects\TimeRange;
use Spatie\LaravelData\Data;

/** A replacement interval, with fades wholly inside the disclosed source range. */
class AudioRepairData extends Data
{
    public function __construct(
        public string $baseMediaId,
        public TimeRange $sourceRange,
        public TimeRange $donorRange,
        public RationalTime $crossfade,
    ) {
        $source = self::seconds($sourceRange->duration);
        $donor = self::seconds($donorRange->duration);
        $fade = self::seconds($crossfade);
        if ($baseMediaId === '' || self::seconds($sourceRange->start_time) < 0
            || self::seconds($donorRange->start_time) < 0 || $source <= 0
            || abs($source - $donor) > 0.000001 || $fade < 0 || 2 * $fade > $source) {
            throw new InvalidArgumentException('Audio repair requires matching positive ranges and in-range crossfades.');
        }
    }

    public static function seconds(RationalTime $time): float
    {
        if (! is_finite($time->value) || ! is_finite($time->rate) || $time->rate <= 0) {
            throw new InvalidArgumentException('Audio repair time requires a finite value and positive rate.');
        }

        $seconds = $time->value / $time->rate;
        if (! is_finite($seconds)) {
            throw new InvalidArgumentException('Audio repair time must represent finite seconds.');
        }

        return $seconds;
    }

    /** Hydrate OTIO field names independently of the host Data input-name mapper. */
    public static function fromWire(array $wire): self
    {
        $time = static function (array $value): RationalTime {
            if (! is_numeric($value['value'] ?? null) || ! is_numeric($value['rate'] ?? null)) {
                throw new InvalidArgumentException('Audio repair time requires value and rate.');
            }

            return new RationalTime((float) $value['value'], (float) $value['rate']);
        };
        $range = static fn (array $value): TimeRange => new TimeRange($time($value['start_time'] ?? []), $time($value['duration'] ?? []));

        return new self((string) ($wire['baseMediaId'] ?? ''), $range($wire['sourceRange'] ?? []), $range($wire['donorRange'] ?? []), $time($wire['crossfade'] ?? []));
    }

    public function toArray(): array
    {
        return ['baseMediaId' => $this->baseMediaId, 'sourceRange' => $this->sourceRange->toArray(),
            'donorRange' => $this->donorRange->toArray(), 'crossfade' => $this->crossfade->toArray()];
    }
}
