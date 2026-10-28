<?php

use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MusicIntent;

/**
 * The v2 mandatory floor (music-capability-lift build/03; ticket 02 Q1): the downbeat-consistency check
 * between `chords` and `changes`, the pickup's melody lower bound, the lead-in's one-bar lower bound, and the
 * refusal of a negative `ChordChange.start` until build/26 admits the pickup chord.
 */
function v2Intent(array $sections, array $top = []): MusicIntent
{
    return MusicIntent::from([
        'key' => ['tonic' => 'F', 'mode' => 'major'],
        'sections' => $sections,
        'tempo' => 78,
        'meter' => '4/4',
        ...$top,
    ]);
}

function mandatoryOf(MusicIntent $intent): array
{
    return (new StructuralMusicConformance)->check($intent)->mandatory;
}

it('passes a v1-shaped intent exactly as before', function () {
    $intent = v2Intent([
        ['tag' => 'VERSE', 'chords' => ['F', 'Bb', 'C', 'F'], 'melody' => [['pitch' => 65, 'start' => 0.0, 'duration' => 1.0]]],
    ]);

    expect(mandatoryOf($intent))->toBe([]);
});

it('passes changes whose downbeat chords agree with chords[]', function () {
    $intent = v2Intent([[
        'tag' => 'VERSE',
        'chords' => ['F', 'Bb', 'Bb', 'C'],
        // bar 0: F then Dm mid-bar; bar 1: Bb (held through bar 2); bar 3: C, with F on beat 3.
        'changes' => [
            ['symbol' => 'F', 'start' => 0.0], ['symbol' => 'Dm', 'start' => 2.0],
            ['symbol' => 'Bb', 'start' => 4.0], ['symbol' => 'C', 'start' => 12.0], ['symbol' => 'F', 'start' => 14.0],
        ],
    ]]);

    expect(mandatoryOf($intent))->toBe([]);
});

it('fails a chords[i] that is not the change sounding at bar i\'s downbeat', function () {
    $intent = v2Intent([[
        'tag' => 'VERSE',
        'chords' => ['F', 'Dm'],   // bar 1's downbeat (beat 4) still sounds the F change
        'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'Dm', 'start' => 6.0]],
    ]]);

    expect(mandatoryOf($intent))->toHaveCount(1)
        ->and(mandatoryOf($intent)[0])->toContain('bar 1')->toContain('"Dm"')->toContain('"F"');
});

it('measures bars in the section\'s effective meter (an override from an earlier section carries forward)', function () {
    $intent = v2Intent([
        ['tag' => 'VERSE', 'chords' => ['F'], 'meter' => '3/4'],
        // 3/4 carries into this section: bar 1 starts at beat 3, not 4.
        ['tag' => 'CHORUS', 'chords' => ['Bb', 'C'], 'changes' => [['symbol' => 'Bb', 'start' => 0.0], ['symbol' => 'C', 'start' => 3.0]]],
    ]);

    expect(mandatoryOf($intent))->toBe([]);

    $sixEight = v2Intent([
        // 6/8 is three quarter-note beats a bar.
        ['tag' => 'VERSE', 'chords' => ['F', 'C'], 'meter' => '6/8', 'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'C', 'start' => 3.0]]],
    ]);

    expect(mandatoryOf($sixEight))->toBe([]);
});

it('fails changes that leave bar 0 without a sounding chord', function () {
    $intent = v2Intent([[
        'tag' => 'VERSE',
        'chords' => ['F'],
        'changes' => [['symbol' => 'F', 'start' => 1.0]],
    ]]);

    expect(implode(' ', mandatoryOf($intent)))->toContain('bar 0');
});

it('fails changes out of order, and a change past the section\'s last bar', function () {
    $unordered = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F', 'C'],
        'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'C', 'start' => 4.0], ['symbol' => 'Dm', 'start' => 2.0]],
    ]]);
    $past = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F'],
        'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'C', 'start' => 4.0]],
    ]]);

    expect(implode(' ', mandatoryOf($unordered)))->toContain('increasing')
        ->and(implode(' ', mandatoryOf($past)))->toContain('past the section');
});

it('refuses a ChordChange with a negative start (until build/26 admits the pickup chord)', function () {
    $intent = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F'],
        'changes' => [['symbol' => 'C', 'start' => -1.0], ['symbol' => 'F', 'start' => 0.0]],
    ]], ['pickup' => 1.0]);

    expect(implode(' ', mandatoryOf($intent)))->toContain('negative');
});

it('passes a section-0 melody note at -pickup and refuses one below it', function () {
    $at = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F'],
        'melody' => [['pitch' => 60, 'start' => -1.5, 'duration' => 0.5], ['pitch' => 65, 'start' => 0.0, 'duration' => 1.0]],
    ]], ['pickup' => 1.5]);

    $below = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F'],
        'melody' => [['pitch' => 60, 'start' => -1.75, 'duration' => 0.25]],
    ]], ['pickup' => 1.5]);

    expect(mandatoryOf($at))->toBe([])
        ->and(mandatoryOf($below))->toHaveCount(1)
        ->and(mandatoryOf($below)[0])->toContain('Section 0')->toContain('-1.75');
});

it('keeps v1\'s floor in section 0 when there is no pickup', function () {
    $intent = v2Intent([[
        'tag' => 'VERSE', 'chords' => ['F'],
        'melody' => [['pitch' => 60, 'start' => -0.5, 'duration' => 0.5]],
    ]]);

    expect(mandatoryOf($intent))->toHaveCount(1);
});

it('lets a later section\'s melody lead in by up to one bar of the previous section, and no further', function () {
    $sections = fn (float $start) => [
        ['tag' => 'VERSE', 'chords' => ['F'], 'meter' => '3/4'],
        ['tag' => 'CHORUS', 'chords' => ['C'], 'meter' => '4/4', 'melody' => [['pitch' => 67, 'start' => $start, 'duration' => 0.5]]],
    ];

    // The lead-in sounds in the previous section's last bar: 3/4, three beats.
    expect(mandatoryOf(v2Intent($sections(-3.0))))->toBe([])
        ->and(mandatoryOf(v2Intent($sections(-3.5))))->toHaveCount(1);
});

it('refuses a negative pickup', function () {
    $intent = v2Intent([['tag' => 'VERSE', 'chords' => ['F']]], ['pickup' => -1.0]);

    expect(implode(' ', mandatoryOf($intent)))->toContain('pickup');
});
