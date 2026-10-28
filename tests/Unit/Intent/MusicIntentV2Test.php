<?php

use Schemastud\DataSchemas\Generators\Generator;
use Schemastud\DataSchemas\Migration\MigrationLadder;
use Spatie\LaravelData\Optional;
use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;

/**
 * `music/intent` v2 (music-capability-lift build/03; ticket 02 Q1): `MusicIntent.pickup` and
 * `MusicSection.changes`, additive and `Optional`, so the v1 → v2 step is the identity on a stored payload.
 *
 * `tests/Fixtures/music-intent-v1.schema.json` is the v1 schema exactly as this package's generator projected
 * it at spine `9dffe06` (the cut), with base URI `https://app.splicewire.com/schemas`. It is the `from` side
 * of the migration below, so the identity claim is proved through schemastud's real migration ladder rather
 * than asserted.
 */
function v1IntentPayload(): array
{
    return [
        'key' => ['tonic' => 'F', 'mode' => 'major'],
        'sections' => [
            ['tag' => 'VERSE', 'chords' => ['F', 'Bb', 'C', 'F'], 'roles' => ['keys'],
                'melody' => [['pitch' => 65, 'start' => 0.0, 'duration' => 1.0], ['pitch' => 67, 'start' => 1.5, 'duration' => 0.5]]],
            ['tag' => 'BRIDGE', 'chords' => ['Gm', 'C'], 'roles' => [], 'meter' => '3/4', 'tempo' => 84],
        ],
        'tempo' => 78,
        'meter' => '4/4',
        'seed' => 7,
    ];
}

function v1Schema(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/music-intent-v1.schema.json'), true, flags: JSON_THROW_ON_ERROR);
}

function v2Schema(): array
{
    config()->set('data-schemas.base_uri', 'https://app.splicewire.com/schemas');

    return app(Generator::class)->generate(new ReflectionClass(MusicIntent::class));
}

it('is music/intent v2', function () {
    expect(MusicIntent::schemaName())->toBe('music/intent')
        ->and(MusicIntent::schemaVersion())->toBe(2)
        ->and(v2Schema()['$id'])->toBe('https://app.splicewire.com/schemas/music/intent/2')
        ->and(v1Schema()['$id'])->toBe('https://app.splicewire.com/schemas/music/intent/1');
});

it('hydrates a v1 payload unchanged: no pickup, no changes, and it serialises back byte for byte', function () {
    $intent = MusicIntent::from(v1IntentPayload());

    expect($intent->pickup)->toBeInstanceOf(Optional::class)
        ->and($intent->pickupBeats())->toBe(0.0)
        ->and($intent->sections[0]->changes)->toBeInstanceOf(Optional::class)
        ->and($intent->sections[1]->changes)->toBeInstanceOf(Optional::class)
        ->and($intent->toArray())->toEqual(v1IntentPayload());
});

it('migrates a v1 payload to v2 through the schema ladder with its meaning unchanged', function () {
    $from = v1Schema();
    $to = v2Schema();

    $result = MigrationLadder::default()->migrate(v1IntentPayload(), $from, $to);

    // The structural rung alone carries it, at full confidence: the only difference it writes is the added
    // `pickup` filled with 0 — which means "no pickup", the same as absent.
    expect($result->wasMigrated())->toBeTrue()
        ->and($result->rung)->toBe('structural')
        ->and($result->confidence)->toBe(1.0)
        ->and($result->original)->toEqual(v1IntentPayload())
        ->and(array_diff_key($result->migrated, v1IntentPayload()))->toBe(['pickup' => 0])
        ->and(array_intersect_key($result->migrated, v1IntentPayload()))->toEqual(v1IntentPayload());

    $migrated = MusicIntent::from($result->migrated);
    $direct = MusicIntent::from(v1IntentPayload());

    // Meaning: the same harmony per bar, the same melody, the same tempo/meter map, and no pickup.
    expect($migrated->pickupBeats())->toBe(0.0)
        ->and($migrated->key->toArray())->toEqual($direct->key->toArray())
        ->and($migrated->tempo)->toBe($direct->tempo)
        ->and($migrated->meter)->toBe($direct->meter)
        ->and($migrated->seed)->toBe($direct->seed);

    foreach ($direct->sections as $i => $section) {
        $after = $migrated->sections[$i];
        expect($after->chords)->toBe($section->chords)
            ->and($after->tag)->toBe($section->tag)
            ->and($after->tempo)->toEqual($section->tempo)
            ->and($after->meter)->toEqual($section->meter)
            ->and($after->changeList())->toBe([])
            ->and(is_array($after->melody) ? array_map(fn ($n) => $n->toArray(), $after->melody) : null)
            ->toEqual(is_array($section->melody) ? array_map(fn ($n) => $n->toArray(), $section->melody) : null);
    }
});

it('round-trips a v2 intent with a pickup and sub-bar changes', function () {
    $payload = [
        'key' => ['tonic' => 'F', 'mode' => 'major'],
        'sections' => [
            [
                'tag' => 'VERSE',
                'chords' => ['F', 'Bb'],
                'roles' => [],
                'melody' => [['pitch' => 60, 'start' => -1.0, 'duration' => 1.0], ['pitch' => 65, 'start' => 0.0, 'duration' => 2.0]],
                'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'C7', 'start' => 2.0], ['symbol' => 'Bb', 'start' => 4.0]],
            ],
        ],
        'tempo' => 78,
        'meter' => '4/4',
        'pickup' => 1.0,
    ];

    $intent = MusicIntent::from($payload);

    expect($intent->pickup)->toBe(1.0)
        ->and($intent->sections[0]->changes)->toHaveCount(3)
        ->and($intent->sections[0]->changes[1])->toBeInstanceOf(ChordChange::class)
        ->and($intent->sections[0]->changes[1]->symbol)->toBe('C7')
        ->and($intent->sections[0]->changes[1]->start)->toBe(2.0)
        ->and($intent->toArray())->toEqual($payload)
        ->and(MusicIntent::from($intent->toArray())->toArray())->toEqual($payload);
});

it('coerces an integer pickup and change start to beats as floats, so one meaning has one serialisation', function () {
    $intent = MusicIntent::from([
        'key' => ['tonic' => 'C'],
        'sections' => [['tag' => 'VERSE', 'chords' => ['C'], 'changes' => [['symbol' => 'C', 'start' => 0]]]],
        'pickup' => 2,
    ]);

    expect($intent->pickup)->toBe(2.0)
        ->and($intent->sections[0]->changes[0]->start)->toBe(0.0);
});

it('constructs positionally as v1 did — the new members are appended and defaulted', function () {
    $section = new MusicSection('VERSE', ['C', 'G']);

    expect($section->changes)->toBeInstanceOf(Optional::class)
        ->and($section->changeList())->toBe([]);
});
