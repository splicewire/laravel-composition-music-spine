<?php

use Spatie\LaravelData\Optional;
use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicKey;
use Splicewire\Composition\Music\MusicSection;
use Splicewire\Composition\Music\Transcription\ChartDraftData;
use Splicewire\Composition\Music\Transcription\ChartDraftSections;
use Splicewire\Composition\Music\Transcription\ChartDraftSectionsRefused;
use Splicewire\Composition\Music\Transcription\DraftBarData;
use Splicewire\Composition\Music\Transcription\DraftChangeMetaData;
use Splicewire\Composition\Music\Transcription\SectionStartData;

/**
 * `ChartDraftSections::apply` (music-capability-lift build/15; ticket 04 Q2 "The conversion after sectioning",
 * ticket 05 E1, E2): the owner's sectioning turns a chart draft's draft-coordinate changes into section-relative
 * v2 sections.
 */

/**
 * A draft whose bars have the given beats (contiguous from beat 0, one second per beat) and whose changes are
 * [symbol, draft start] pairs. Every change and bar carries confidence, so the tests can see it never leaves.
 *
 * @param  list<float|int>  $beats
 * @param  list<array{0: string, 1: float|int}>  $changes
 */
function sectionsDraft(array $beats, array $changes, float $pickup = 0.0, string $meter = '4/4'): ChartDraftData
{
    $bars = [];
    $start = 0.0;
    foreach ($beats as $i => $b) {
        $bars[] = new DraftBarData(index: $i, startS: $start, endS: $start + $b, startBeat: $start, beats: (float) $b, confidence: 0.25, reasons: ['chord_margin']);
        $start += $b;
    }

    $chordChanges = array_map(fn (array $c) => new ChordChange(symbol: $c[0], start: (float) $c[1]), $changes);

    return new ChartDraftData(
        key: new MusicKey('C'),
        tempoBpm: 96.0,
        tempoPrior: null,
        meter: $meter,
        pickupBeats: $pickup,
        downbeatMargin: 0.4,
        changes: $chordChanges,
        changeMeta: array_map(fn (int $i) => new DraftChangeMetaData(index: $i, confidence: 0.5, reasons: ['ungated: sub-bar change']), array_keys($chordChanges)),
        bars: $bars,
        reviewOrder: array_keys($bars),
    );
}

/** @param  list<array{0: string, 1: string, 2: int}>  $sections  [tag, label, start_bar] */
function draftSectioning(array $sections): array
{
    return array_map(fn (array $s) => new SectionStartData(tag: $s[0], label: $s[1], startBar: $s[2]), $sections);
}

/** @return list<array{0: string, 1: float}> */
function sectionChangePairs(MusicSection $section): array
{
    return array_map(fn (ChordChange $c) => [$c->symbol, $c->start], $section->changeList());
}

function sectionsRefusalOf(callable $apply): ChartDraftSectionsRefused
{
    try {
        $apply();
    } catch (ChartDraftSectionsRefused $refused) {
        return $refused;
    }

    throw new RuntimeException('apply() did not refuse.');
}

it('rebases a two-section draft\'s changes onto each section\'s origin', function () {
    // Four 4/4 bars: VERSE is bars 0–1 (origin 0), CHORUS bars 2–3 (origin 8).
    $draft = sectionsDraft([4, 4, 4, 4], [['C', 0], ['G', 6], ['Am', 8], ['F', 10], ['G', 12]]);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 2]]));

    expect($sectioned->origins)->toBe([0.0, 8.0])
        ->and($sectioned->sections)->toHaveCount(2)
        ->and($sectioned->sections[0]->tag)->toBe('VERSE')
        ->and(sectionChangePairs($sectioned->sections[0]))->toBe([['C', 0.0], ['G', 6.0]])
        ->and($sectioned->sections[0]->chords)->toBe(['C', 'C'])
        ->and($sectioned->sections[1]->tag)->toBe('CHORUS')
        ->and(sectionChangePairs($sectioned->sections[1]))->toBe([['Am', 0.0], ['F', 2.0], ['G', 4.0]])
        ->and($sectioned->sections[1]->chords)->toBe(['Am', 'G']);
});

it('restates at beat 0 a chord still sounding from the previous section', function () {
    // G starts on bar 1 beat 2 (draft 6) and is still sounding at the CHORUS downbeat (draft 8).
    $draft = sectionsDraft([4, 4, 4, 4], [['C', 0], ['G', 6], ['F', 10]]);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 2]]));

    expect(sectionChangePairs($sectioned->sections[1]))->toBe([['G', 0.0], ['F', 2.0]])
        ->and($sectioned->sections[1]->chords)->toBe(['G', 'F']);

    // Without the restatement, the CHORUS's bar 0 would have no change sounding at its downbeat (02's floor).
    $intent = new MusicIntent(key: $draft->key, sections: $sectioned->sections, tempo: 96, meter: $sectioned->meter);

    expect((new StructuralMusicConformance)->check($intent)->mandatory)->toBe([]);
});

it('refuses a 3/4 bar inside a section by name', function () {
    $draft = sectionsDraft([4, 4, 3, 4], [['C', 0], ['G', 4], ['Am', 8], ['F', 11]]);

    $refused = sectionsRefusalOf(fn () => ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 1]])));

    expect($refused->reason)->toBe(ChartDraftSectionsRefused::METER_CHANGE_INSIDE_SECTION)
        ->and($refused->getMessage())->toContain('meter_change_inside_section')->toContain('bar 2')->toContain('Chorus');
});

it('shifts every later origin when the meter changes at a section start', function () {
    // VERSE bars 0–1 in 4/4 (8 beats), BRIDGE bars 2–3 in 3/4 (6 beats), CHORUS bars 4–5 back in 4/4.
    $draft = sectionsDraft([4, 4, 3, 3, 4, 4], [['C', 0], ['G', 4], ['Am', 8], ['Em', 9], ['F', 11], ['C', 14], ['G', 18]]);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0], ['BRIDGE', 'Bridge', 2], ['CHORUS', 'Chorus', 4]]));

    expect($sectioned->origins)->toBe([0.0, 8.0, 14.0])
        ->and($sectioned->meter)->toBe('4/4')
        ->and($sectioned->sections[0]->meter)->toBeInstanceOf(Optional::class)
        ->and($sectioned->sections[1]->meter)->toBe('3/4')
        ->and(sectionChangePairs($sectioned->sections[1]))->toBe([['Am', 0.0], ['Em', 1.0], ['F', 3.0]])
        ->and($sectioned->sections[1]->chords)->toBe(['Am', 'F'])
        ->and($sectioned->sections[2]->meter)->toBe('4/4')
        ->and(sectionChangePairs($sectioned->sections[2]))->toBe([['C', 0.0], ['G', 4.0]])
        ->and($sectioned->sections[2]->chords)->toBe(['C', 'G']);
});

it('derives chords[i] from the change sounding at each downbeat, passing the downbeat floor', function () {
    $draft = sectionsDraft(
        [4, 4, 4, 3, 3, 4],
        [['C', 0], ['Am', 2], ['F', 4], ['G', 7], ['Em', 9], ['Dm', 12], ['G', 13.5], ['C', 15], ['Bb', 18], ['C', 20]],
        pickup: 1.0,
    );

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['INTRO', 'Intro', 0], ['VERSE', 'Verse', 1], ['BRIDGE', 'Bridge', 3], ['OUTRO', 'Outro', 5]]));

    // Each chords[i] is the last draft change at or before bar i's downbeat.
    $expected = [['C'], ['F', 'G'], ['Dm', 'C'], ['Bb']];
    foreach ($sectioned->sections as $k => $section) {
        expect($section->chords)->toBe($expected[$k]);
    }

    $intent = new MusicIntent(key: $draft->key, sections: $sectioned->sections, tempo: 96, meter: $sectioned->meter, pickup: $sectioned->pickup);
    $conformance = (new StructuralMusicConformance)->check($intent);

    expect($conformance->mandatory)->toBe([])
        ->and($sectioned->pickup)->toBe(1.0);
});

it('places a draft change in the pickup in section 0 at its negative start, never shifting it to beat 0', function () {
    $draft = sectionsDraft([4, 4], [['D', -1], ['C', 0], ['G', 4]], pickup: 2.0);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0]]));
    $intent = new MusicIntent(key: $draft->key, sections: $sectioned->sections, tempo: 96, meter: $sectioned->meter, pickup: $sectioned->pickup);

    expect(sectionChangePairs($sectioned->sections[0]))->toBe([['D', -1.0], ['C', 0.0], ['G', 4.0]])
        ->and($sectioned->sections[0]->chords)->toBe(['C', 'G'])
        ->and($sectioned->pickup)->toBe(2.0)
        ->and((new StructuralMusicConformance)->check($intent)->mandatory)->toBe([]);
});

it('places every pickup change in section 0 only, and a pickup chord still sounding at the downbeat is chords[0]', function () {
    $draft = sectionsDraft([4, 4], [['D', -2], ['A7', -1], ['G', 2], ['C', 4]], pickup: 2.0);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['INTRO', 'Intro', 0], ['VERSE', 'Verse', 1]]));
    $intent = new MusicIntent(key: $draft->key, sections: $sectioned->sections, tempo: 96, meter: $sectioned->meter, pickup: $sectioned->pickup);

    expect(sectionChangePairs($sectioned->sections[0]))->toBe([['D', -2.0], ['A7', -1.0], ['G', 2.0]])
        ->and($sectioned->sections[0]->chords)->toBe(['A7'])
        ->and(sectionChangePairs($sectioned->sections[1]))->toBe([['C', 0.0]])
        ->and((new StructuralMusicConformance)->check($intent)->mandatory)->toBe([]);
});

it('still refuses by name, and lists, a change before the pickup', function () {
    $draft = sectionsDraft([4, 4], [['D', -2], ['C', 0]], pickup: 2.0);
    // ChartDraftData refuses this at construction; apply() holds the rule on its own for a draft changed after it.
    $draft->pickupBeats = 1.0;

    $refused = sectionsRefusalOf(fn () => ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0]])));

    expect($refused->reason)->toBe(ChartDraftSectionsRefused::CHANGE_BEFORE_PICKUP)
        ->and($refused->getMessage())->toContain('change_before_pickup')->toContain('"D"')->toContain('-1')
        ->and(array_map(fn (ChordChange $c) => [$c->symbol, $c->start], $refused->changes))->toBe([0 => ['D', -2.0]]);
});

it('carries only v2 section members onto the result — no confidence, no change_meta', function () {
    $draft = sectionsDraft([4, 4], [['C', 0], ['G', 4]], pickup: 1.5);

    $sectioned = ChartDraftSections::apply($draft, draftSectioning([['VERSE', 'Verse', 0]]));
    $section = $sectioned->sections[0]->toArray();

    expect($sectioned->pickup)->toBe(1.5)
        ->and(array_keys($section))->toBe(['tag', 'chords', 'roles', 'changes'])
        ->and(json_encode($section))->not->toContain('confidence')->not->toContain('reasons');
});

it('refuses a bar with no chord sounding at its downbeat by name', function () {
    $draft = sectionsDraft([4, 4], [['G', 5]]);

    $refused = sectionsRefusalOf(fn () => ChartDraftSections::apply($draft, draftSectioning([['INTRO', 'Intro', 0]])));

    expect($refused->reason)->toBe(ChartDraftSectionsRefused::BAR_WITHOUT_CHORD)
        ->and($refused->getMessage())->toContain('bar 0')->toContain('bar 1');
});

it('refuses a sectioning that is not ascending from bar 0 inside the draft', function (array $sections, string $reason) {
    $draft = sectionsDraft([4, 4, 4], [['C', 0]]);

    expect(sectionsRefusalOf(fn () => ChartDraftSections::apply($draft, draftSectioning($sections)))->reason)->toBe($reason);
})->with([
    'empty' => [[], ChartDraftSectionsRefused::SECTIONING_EMPTY],
    'first section after bar 0' => [[['VERSE', 'Verse', 1]], ChartDraftSectionsRefused::SECTIONING_NOT_FROM_BAR_ZERO],
    'repeated start bar' => [[['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 1], ['BRIDGE', 'Bridge', 1]], ChartDraftSectionsRefused::SECTIONING_NOT_ASCENDING],
    'descending start bar' => [[['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 2], ['BRIDGE', 'Bridge', 1]], ChartDraftSectionsRefused::SECTIONING_NOT_ASCENDING],
    'start bar past the last bar' => [[['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 3]], ChartDraftSectionsRefused::SECTION_PAST_LAST_BAR],
]);

it('derives an odd section meter on the draft meter\'s denominator', function (array $beats, string $meter, string $expected) {
    $changes = [['C', 0], ['G', $beats[0]]];

    $sectioned = ChartDraftSections::apply(sectionsDraft($beats, $changes, meter: $meter), draftSectioning([['VERSE', 'Verse', 0], ['CHORUS', 'Chorus', 1]]));

    expect($sectioned->sections[1]->meter)->toBe($expected);
})->with([
    '4/4 then a 2-beat bar' => [[4, 2], '4/4', '2/4'],
    '6/8 then a 4.5-beat bar' => [[3, 4.5], '6/8', '9/8'],
    '4/4 then a 2.5-beat bar' => [[4, 2.5], '4/4', '5/8'],
]);

it('accepts the owner sectioning as plain arrays', function () {
    $draft = sectionsDraft([4, 4], [['C', 0], ['G', 4]]);

    $sectioned = ChartDraftSections::apply($draft, [['tag' => 'VERSE', 'label' => 'Verse', 'start_bar' => 0], ['tag' => 'CHORUS', 'label' => 'Chorus', 'start_bar' => 1]]);

    expect($sectioned->sections)->toHaveCount(2)
        ->and($sectioned->sections[1]->chords)->toBe(['G']);
});
