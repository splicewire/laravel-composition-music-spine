<?php

use Splicewire\Composition\Music\Arrange\ArrangerData;
use Splicewire\Composition\Music\Arrange\ArrangeRefusal;
use Splicewire\Composition\Music\Arrange\ArrangeRequest;
use Splicewire\Composition\Music\Arrange\ArrangeResult;
use Splicewire\Composition\Music\Conformance\StructuralMusicConformance;
use Splicewire\Composition\Music\MusicIntent;

/**
 * The platform → satellite Arranger call (music-capability-lift build/30; ticket 06 Q1, Q2, I02): `ArrangeRequest`
 * with its intent digest and the activation probe, and the two answers, `ArrangeResult` (200) and `ArrangeRefusal`
 * (422).
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php).
 */
function arrangeIntentPayload(array $overrides = []): array
{
    return [
        'key' => ['tonic' => 'D', 'mode' => 'minor'],
        'sections' => [
            ['tag' => 'VERSE', 'chords' => ['Dm', 'Bb'], 'roles' => ['keys'],
                'changes' => [['symbol' => 'Dm', 'start' => 0.0], ['symbol' => 'Bb', 'start' => 4.0]]],
        ],
        'tempo' => 90,
        'meter' => '4/4',
        'pickup' => 1.0,
        ...$overrides,
    ];
}

/** The definition, built independently: keys sorted at every associative level, two flags, sha256. */
function canonicalIntentSha256(array $payload): string
{
    $sort = function (mixed $v) use (&$sort): mixed {
        if (! is_array($v)) {
            return $v;
        }
        $v = array_map($sort, $v);
        if (! array_is_list($v)) {
            ksort($v);
        }

        return $v;
    };

    return hash('sha256', json_encode($sort($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

it('round-trips an ArrangeRequest in snake_case, the intent inline', function () {
    $request = ArrangeRequest::for(MusicIntent::from(arrangeIntentPayload()), str_repeat('k', 64), 1);

    expect(array_keys($request->toArray()))->toBe(['intent', 'intent_digest', 'run_key', 'iteration'])
        ->and($request->toArray()['intent']['pickup'])->toBe(1.0)
        ->and(ArrangeRequest::from($request->toArray())->toArray())->toBe($request->toArray());
});

it('digests the intent as the sha256 of its canonical JSON', function () {
    $intent = MusicIntent::from(arrangeIntentPayload());
    $request = ArrangeRequest::for($intent, 'run', 0);

    expect($request->intentDigest)->toBe(canonicalIntentSha256($intent->toArray()))
        ->and($request->intentDigest)->toBe($intent->digest())
        ->and(MusicIntent::from(array_reverse(arrangeIntentPayload(), true))->digest())->toBe($intent->digest())
        ->and(MusicIntent::from(arrangeIntentPayload(['tempo' => 91]))->digest())->not->toBe($intent->digest());
});

it('digests a zero pickup and empty changes as their absence', function () {
    $absent = arrangeIntentPayload();
    unset($absent['pickup'], $absent['sections'][0]['changes']);

    $zero = [...$absent, 'pickup' => 0];
    $empty = $absent;
    $empty['sections'][0]['changes'] = [];

    $digest = MusicIntent::from($absent)->digest();

    expect(MusicIntent::from($zero)->digest())->toBe($digest)
        ->and(MusicIntent::from($empty)->digest())->toBe($digest)
        ->and(MusicIntent::from(arrangeIntentPayload())->digest())->not->toBe($digest);
});

it('refuses a negative iteration', function () {
    expect(fn () => ArrangeRequest::for(MusicIntent::from(arrangeIntentPayload()), 'run', -1))
        ->toThrow(InvalidArgumentException::class, 'iteration');
});

it('builds the probe deterministically, its intent_digest the sha256 of the canonical intent JSON', function () {
    $probe = ArrangeRequest::probe();

    expect($probe->toArray())->toBe(ArrangeRequest::probe()->toArray())
        ->and($probe->intentDigest)->toBe(canonicalIntentSha256($probe->intent->toArray()))
        ->and($probe->intentDigest)->toBe($probe->intent->digest())
        ->and($probe->runKey)->toBe(ArrangeRequest::PROBE_RUN_KEY)
        ->and($probe->iteration)->toBe(0)
        ->and(ArrangeRequest::from($probe->toArray())->toArray())->toBe($probe->toArray());
});

it('pins the probe intent, so a change to the fixture is a deliberate edit here', function () {
    expect(ArrangeRequest::probe()->intent->toArray())->toBe([
        'key' => ['tonic' => 'C', 'mode' => 'major'],
        'sections' => [['tag' => 'VERSE', 'chords' => ['C', 'Am', 'F', 'G'], 'roles' => []]],
        'tempo' => 96,
        'meter' => '4/4',
    ]);
});

it('keeps the probe v1-plain and conformant, so an Arranger refusing v2 members can still answer it', function () {
    $intent = ArrangeRequest::probe()->intent;

    expect($intent->pickupBeats())->toBe(0.0)
        ->and(array_map(fn ($section) => $section->changeList(), $intent->sections))->toBe([[]])
        ->and((new StructuralMusicConformance)->check($intent)->passes())->toBeTrue();
});

it('round-trips an ArrangeResult in snake_case', function () {
    $result = ArrangeResult::from([
        'midi_base64' => base64_encode("MThd\x00\x00\x00\x06"),
        'midi_sha256' => hash('sha256', "MThd\x00\x00\x00\x06"),
        'bytes' => 8,
        'arranger' => ['name' => 'audiostud', 'version' => '2.1.0'],
    ]);

    expect($result->arranger)->toBeInstanceOf(ArrangerData::class)
        ->and(array_keys($result->toArray()))->toBe(['midi_base64', 'midi_sha256', 'bytes', 'arranger'])
        ->and(ArrangeResult::from($result->toArray())->toArray())->toBe($result->toArray());
});

it('states a MIDI file\'s encoding, sha256 and length when built for it', function () {
    $midi = "MThd\x00\x00\x00\x06\x00\x01\x00\x02\x01\xe0";
    $result = ArrangeResult::forMidi($midi, new ArrangerData('audiostud', '2.1.0'));

    expect(base64_decode($result->midiBase64, true))->toBe($midi)
        ->and($result->midiSha256)->toBe(hash('sha256', $midi))
        ->and($result->bytes)->toBe(14);
});

it('round-trips an ArrangeRefusal naming the v2 members it cannot honour', function () {
    $refusal = ArrangeRefusal::from([
        'reason' => ArrangeRefusal::UNSUPPORTED_INTENT_MEMBERS,
        'members' => ['pickup', 'changes'],
        'detail' => 'this Arranger does not honour pickup or changes yet',
    ]);

    expect($refusal->reason)->toBe('unsupported_intent_members')
        ->and($refusal->toArray())->toBe([
            'reason' => 'unsupported_intent_members',
            'members' => ['pickup', 'changes'],
            'detail' => 'this Arranger does not honour pickup or changes yet',
        ])
        ->and(ArrangeRefusal::from($refusal->toArray())->toArray())->toBe($refusal->toArray());
});
