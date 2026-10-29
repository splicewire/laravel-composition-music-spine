<?php

use Splicewire\Composition\Music\Notation\NotationRequest;
use Splicewire\Composition\Music\Notation\NotationRequestRefused;

/**
 * The stage-2 syllable contract (music-capability-lift build/26; ticket 05 E3, Q4 amended): `syllables`, when present, is
 * the section's complete ordered list of units; a unit under a chord takes the chord's onset and the others carry
 * `start: null`. A section with melody underlays its timed syllables and refuses an untimed or unmatched one; a
 * melody-less section underlays nothing. Timed syllables follow the melody's negative-start rule. These are 05's
 * acceptance cases (i)–(iv) at the contract level; the engraving of them is issue 27's.
 */

/**
 * A request over a one-section melody-less chorus (4 bars of 4/4) whose lines and syllables are given, plus any
 * intent-level overrides.
 *
 * @param  list<array<string, mixed>>  $syllables
 * @param  list<array<string, mixed>>|null  $sections  intent sections replacing the default one
 * @param  list<array<string, mixed>>|null  $printed  printed sections replacing the default one
 */
function syllablePayload(array $syllables, ?array $sections = null, ?array $printed = null, array $intent = []): array
{
    return [
        'intent' => [
            'key' => ['tonic' => 'G', 'mode' => 'major'],
            'sections' => $sections ?? [
                ['tag' => 'CHORUS', 'chords' => ['G', 'G', 'D', 'D'], 'roles' => [],
                    'changes' => [['symbol' => 'G', 'start' => 0.0], ['symbol' => 'D', 'start' => 8.0]]],
            ],
            'tempo' => 120,
            'meter' => '4/4',
            ...$intent,
        ],
        'source' => ['chartDigest' => str_repeat('c', 64), 'takeRunId' => 'run-hsc'],
        'title' => 'Here She Comes',
        'sections' => $printed ?? [['label' => 'CHORUS', 'lines' => ['Here she comes. Here she comes.'], 'syllables' => $syllables]],
        'formats' => ['musicxml', 'chart'],
        'arrangement' => 'lead_sheet',
    ];
}

/** @return list<array<string, mixed>> */
function hereSheComes(?float $first = 0.0, ?float $second = 8.0): array
{
    return [
        ['text' => 'Here', 'start' => $first, 'line' => 0],
        ['text' => 'she', 'start' => null, 'line' => 0],
        ['text' => 'comes.', 'start' => null, 'line' => 0],
        ['text' => 'Here', 'start' => $second, 'line' => 0],
        ['text' => 'she', 'start' => null, 'line' => 0],
        ['text' => 'comes.', 'start' => null, 'line' => 0],
    ];
}

function syllableRefusalOf(array $payload): ?NotationRequestRefused
{
    try {
        NotationRequest::from($payload);
    } catch (NotationRequestRefused $refused) {
        return $refused;
    }

    return null;
}

/** @return list<array{0: string, 1: ?float, 2: bool}> */
function unitsOf(NotationRequest $request, int $section = 0): array
{
    return array_map(fn ($s) => [$s->text, $s->start, $s->hyphen], $request->sections[$section]->syllables);
}

it('(i) validates a melody-less lyric section of null-start units, with no underlay required', function () {
    $units = [
        ['text' => 'ev', 'start' => 0.0, 'line' => 0, 'hyphen' => true],
        ['text' => 'ery', 'start' => null, 'line' => 0, 'hyphen' => true],
        ['text' => 'bod', 'start' => 8.0, 'line' => 0, 'hyphen' => true],
        ['text' => 'y', 'start' => null, 'line' => 0],
        ['text' => 'knows', 'start' => null, 'line' => 0],
    ];
    $request = NotationRequest::from(syllablePayload($units, printed: [['label' => 'CHORUS', 'lines' => ['every- body knows'], 'syllables' => $units]]));

    expect(unitsOf($request))->toBe([['ev', 0.0, true], ['ery', null, true], ['bod', 8.0, true], ['y', null, false], ['knows', null, false]])
        ->and($request->toArray()['sections'][0]['syllables'][1])->toHaveKey('start', null)
        ->and(NotationRequest::from($request->toArray())->toArray())->toEqual($request->toArray());
});

it('(i) validates a melody-less section whose units are all untimed', function () {
    $units = array_map(fn (array $u) => [...$u, 'start' => null], hereSheComes());

    expect(syllableRefusalOf(syllablePayload($units)))->toBeNull();
});

it('(ii) keeps each anchor of a repeated phrase on its own unit, in order', function () {
    $request = NotationRequest::from(syllablePayload(hereSheComes()));
    $wire = NotationRequest::from(json_decode(json_encode($request->toArray()), true));

    expect(unitsOf($wire))->toBe([
        ['Here', 0.0, false], ['she', null, false], ['comes.', null, false],
        ['Here', 8.0, false], ['she', null, false], ['comes.', null, false],
    ]);
});

it('(ii) tells the occurrences apart: an anchor moved to the other "Here" is a different request', function () {
    $first = NotationRequest::from(syllablePayload(hereSheComes(0.0, null)))->digest();
    $second = NotationRequest::from(syllablePayload(hereSheComes(null, 0.0)))->digest();

    expect($first)->not->toBe($second);
});

it('(ii) refuses timed units out of order, by name', function () {
    $refused = syllableRefusalOf(syllablePayload(hereSheComes(8.0, 0.0)));

    expect($refused?->reason)->toBe(NotationRequestRefused::SYLLABLES_OUT_OF_ORDER)
        ->and($refused->getMessage())->toContain('syllables_out_of_order')->toContain('section 0')->toContain('"Here"');
});

it('(ii) refuses two timed units at one onset, by name', function () {
    expect(syllableRefusalOf(syllablePayload(hereSheComes(8.0, 8.0)))?->reason)->toBe(NotationRequestRefused::SYLLABLES_OUT_OF_ORDER);
});

/** A melody section: notes on beats 0, 1 and 2 of one 4/4 bar. */
function melodySection(): array
{
    return ['tag' => 'VERSE', 'chords' => ['G'], 'roles' => [], 'melody' => [
        ['pitch' => 67, 'start' => 0.0, 'duration' => 1.0],
        ['pitch' => 69, 'start' => 1.0, 'duration' => 1.0],
        ['pitch' => 71, 'start' => 2.0, 'duration' => 2.0],
    ]];
}

it('(iii) refuses an untimed syllable in a melody section, by name', function () {
    $units = [['text' => 'Here', 'start' => 0.0, 'line' => 0], ['text' => 'she', 'start' => null, 'line' => 0], ['text' => 'comes', 'start' => 2.0, 'line' => 0]];
    $refused = syllableRefusalOf(syllablePayload($units, sections: [melodySection()]));

    expect($refused?->reason)->toBe(NotationRequestRefused::SYLLABLE_UNTIMED)
        ->and($refused->getMessage())->toContain('syllable_untimed')->toContain('"she"')->toContain('section 0');
});

it('(iv) refuses a timed syllable no melody note starts on, in a melody section, by name (02 unchanged)', function () {
    $units = [['text' => 'Here', 'start' => 0.0, 'line' => 0], ['text' => 'she', 'start' => 1.5, 'line' => 0]];
    $refused = syllableRefusalOf(syllablePayload($units, sections: [melodySection()]));

    expect($refused?->reason)->toBe(NotationRequestRefused::SYLLABLE_WITHOUT_NOTE)
        ->and($refused->getMessage())->toContain('syllable_without_note')->toContain('"she"')->toContain('1.5');
});

it('(iv) underlays a melody section whose every syllable is on a note', function () {
    $units = [['text' => 'Here', 'start' => 0.0, 'line' => 0], ['text' => 'she', 'start' => 1.0, 'line' => 0], ['text' => 'comes', 'start' => 2.0, 'line' => 0]];

    expect(syllableRefusalOf(syllablePayload($units, sections: [melodySection()])))->toBeNull();
});

it('underlays only the section with melody: the melody-less one beside it keeps its untimed units', function () {
    $sections = [melodySection(), ['tag' => 'CHORUS', 'chords' => ['G', 'D'], 'roles' => []]];
    $printed = [
        ['label' => 'VERSE', 'lines' => ['Here she comes'], 'syllables' => [
            ['text' => 'Here', 'start' => 0.0, 'line' => 0], ['text' => 'she', 'start' => 1.0, 'line' => 0], ['text' => 'comes', 'start' => 2.0, 'line' => 0]]],
        ['label' => 'CHORUS', 'lines' => ['Here she comes'], 'syllables' => [
            ['text' => 'Here', 'start' => 0.0, 'line' => 0], ['text' => 'she', 'start' => null, 'line' => 0], ['text' => 'comes', 'start' => 4.0, 'line' => 0]]],
    ];

    expect(syllableRefusalOf(syllablePayload([], $sections, $printed)))->toBeNull();
});

it('lets a syllable in section 0 start as early as -pickup, and no earlier', function (float $start, ?string $reason) {
    $units = [['text' => 'Oh,', 'start' => $start, 'line' => 0], ['text' => 'here', 'start' => 0.0, 'line' => 0]];

    expect(syllableRefusalOf(syllablePayload($units, intent: ['pickup' => 2.0]))?->reason)->toBe($reason);
})->with([
    'at -pickup' => [-2.0, null],
    'inside the pickup' => [-1.0, null],
    'before the pickup' => [-2.5, NotationRequestRefused::SYLLABLE_TOO_EARLY],
]);

it('refuses a negative syllable start in section 0 without a pickup, by name', function () {
    $refused = syllableRefusalOf(syllablePayload([['text' => 'Oh,', 'start' => -1.0, 'line' => 0]]));

    expect($refused?->reason)->toBe(NotationRequestRefused::SYLLABLE_TOO_EARLY)
        ->and($refused->getMessage())->toContain('syllable_too_early')->toContain('"Oh,"');
});

it('lets a later section\'s syllable lead in by up to one bar of the previous section\'s meter, and no further', function (float $start, ?string $reason) {
    $sections = [['tag' => 'INTRO', 'chords' => ['G'], 'roles' => [], 'meter' => '3/4'], ['tag' => 'VERSE', 'chords' => ['G'], 'roles' => [], 'meter' => '4/4']];
    $printed = [['label' => 'INTRO'], ['label' => 'VERSE', 'lines' => ['and here'], 'syllables' => [
        ['text' => 'and', 'start' => $start, 'line' => 0], ['text' => 'here', 'start' => 0.0, 'line' => 0]]]];

    expect(syllableRefusalOf(syllablePayload([], $sections, $printed))?->reason)->toBe($reason);
})->with([
    'one bar of 3/4' => [-3.0, null],
    'past one bar' => [-3.5, NotationRequestRefused::SYLLABLE_TOO_EARLY],
]);

it('matches a melody section\'s lead-in syllable to the note sounding at that score beat, in either section', function (array $intro, array $verseNotes, ?string $reason) {
    $sections = [
        ['tag' => 'INTRO', 'chords' => ['G'], 'roles' => [], ...$intro],
        ['tag' => 'VERSE', 'chords' => ['G'], 'roles' => [], 'melody' => [...$verseNotes, ['pitch' => 67, 'start' => 0.0, 'duration' => 1.0]]],
    ];
    $printed = [['label' => 'INTRO'], ['label' => 'VERSE', 'lines' => ['and here'], 'syllables' => [
        ['text' => 'and', 'start' => -1.0, 'line' => 0], ['text' => 'here', 'start' => 0.0, 'line' => 0]]]];

    expect(syllableRefusalOf(syllablePayload([], $sections, $printed))?->reason)->toBe($reason);
})->with([
    'the lead-in note in the verse' => [[], [['pitch' => 62, 'start' => -1.0, 'duration' => 1.0]], null],
    'the same beat, authored in the intro' => [['melody' => [['pitch' => 62, 'start' => 3.0, 'duration' => 1.0]]], [], null],
    'no note at that beat' => [['melody' => [['pitch' => 62, 'start' => 2.0, 'duration' => 1.0]]], [], NotationRequestRefused::SYLLABLE_WITHOUT_NOTE],
]);
