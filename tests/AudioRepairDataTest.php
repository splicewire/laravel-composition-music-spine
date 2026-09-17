<?php

namespace Splicewire\Composition\Music\Tests;

use InvalidArgumentException;
use Rushing\TimelineSchema\Objects\RationalTime;
use Rushing\TimelineSchema\Objects\TimeRange;
use Splicewire\Composition\Music\Repair\AudioRepairData;

class AudioRepairDataTest extends TestCase
{
    public function test_sample_units_round_trip_without_the_host_mapper_renaming_otio_fields(): void
    {
        $range = new TimeRange(new RationalTime(2899134, 44100), new RationalTime(45864, 44100));
        $repair = new AudioRepairData('base-media', $range, $range, new RationalTime(1764, 44100));
        $roundtrip = AudioRepairData::fromWire($repair->toArray());
        $this->assertSame($repair->toArray(), $roundtrip->toArray());
        $this->assertEqualsWithDelta(65.74, AudioRepairData::seconds($roundtrip->sourceRange->start_time), 0.00001);
    }

    public function test_replacement_cannot_implicitly_stretch_audio_or_fade_outside_the_disclosed_range(): void
    {
        $range = new TimeRange(new RationalTime(1, 1), new RationalTime(2, 1));
        foreach ([new RationalTime(0, 0), new RationalTime(2, 1), new RationalTime(-1, 1)] as $fade) {
            try {
                new AudioRepairData('base', $range, $range, $fade);
                $this->fail('Expected invalid repair to be refused.');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('Audio repair', $e->getMessage());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        new AudioRepairData('base', $range, new TimeRange(new RationalTime(1, 1), new RationalTime(3, 1)), new RationalTime(0, 1));
    }
}
