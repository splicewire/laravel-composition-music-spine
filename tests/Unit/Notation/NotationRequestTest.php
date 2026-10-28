<?php

use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\Notation\NotationArrangement;
use Splicewire\Composition\Music\Notation\NotationFormat;
use Splicewire\Composition\Music\Notation\NotationRequest;
use Splicewire\Composition\Music\Notation\NotationRequestRefused;

/**
 * `NotationRequest` (music-capability-lift build/03; ticket 02 Q1/Q2 amended, ticket 09): the intent inline,
 * provenance, credits, per-section print matter, formats, arrangement and the lyric-block flag — and its
 * content digest, the request half of the notation run key.
 */
function notationPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'intent' => [
            'key' => ['tonic' => 'F', 'mode' => 'major'],
            'sections' => [
                ['tag' => 'VERSE', 'chords' => ['F', 'Bb'], 'roles' => [],
                    'melody' => [['pitch' => 65, 'start' => 0.0, 'duration' => 1.0], ['pitch' => 67, 'start' => 1.0, 'duration' => 1.0]],
                    'changes' => [['symbol' => 'F', 'start' => 0.0], ['symbol' => 'Bb', 'start' => 4.0]]],
                ['tag' => 'CHORUS', 'chords' => ['C', 'F'], 'roles' => []],
            ],
            'tempo' => 78,
            'meter' => '4/4',
            'pickup' => 1.0,
        ],
        'source' => ['chartDigest' => str_repeat('a', 64), 'takeRunId' => 'run-1'],
        'title' => 'On To Something Here',
        'composer' => 'Stephen Rushing',
        'lyricist' => 'Stephen Rushing',
        'sections' => [
            ['label' => 'VERSE 1', 'direction' => 'start sparse, build', 'lines' => ['every body knows'],
                'syllables' => [['text' => 'ev', 'start' => 0.0, 'line' => 0, 'hyphen' => true], ['text' => 'ery', 'start' => 1.0, 'line' => 0, 'hyphen' => false]],
                'cues' => [['start' => 0.0, 'text' => 'fill']]],
            ['label' => 'CHORUS'],
        ],
        'formats' => ['musicxml', 'pdf', 'chart'],
        'arrangement' => 'lead_sheet',
    ], $overrides);
}

function notationRequest(array $overrides = []): NotationRequest
{
    return NotationRequest::from(notationPayload($overrides));
}

/** @return array<string, mixed> the payload with every associative level's keys reversed */
function reverseKeys(array $value): array
{
    if (array_is_list($value)) {
        return array_map(fn ($v) => is_array($v) ? reverseKeys($v) : $v, $value);
    }

    $out = [];
    foreach (array_reverse($value, true) as $k => $v) {
        $out[$k] = is_array($v) ? reverseKeys($v) : $v;
    }

    return $out;
}

function refusalOf(callable $build): ?string
{
    try {
        $build();
    } catch (NotationRequestRefused $e) {
        return $e->reason;
    }

    return null;
}

it('hydrates every member, the intent inline', function () {
    $request = notationRequest();

    expect($request->intent)->toBeInstanceOf(MusicIntent::class)
        ->and($request->intent->pickup)->toBe(1.0)
        ->and($request->source->takeRunId)->toBe('run-1')
        ->and($request->formats)->toBe([NotationFormat::MusicXml, NotationFormat::Pdf, NotationFormat::Chart])
        ->and($request->arrangement)->toBe(NotationArrangement::LeadSheet)
        ->and($request->lyricBlock)->toBeFalse()
        ->and($request->sections[0]->syllables[0]->hyphen)->toBeTrue()
        ->and($request->sections[0]->cues[0]->text)->toBe('fill')
        ->and($request->sections[1]->lines)->toBe([])
        ->and(NotationRequest::from($request->toArray())->toArray())->toEqual($request->toArray());
});

it('digests to a sha256 that is stable under key reordering', function () {
    $digest = notationRequest()->digest();

    expect($digest)->toMatch('/^[0-9a-f]{64}$/')
        ->and(NotationRequest::from(reverseKeys(notationPayload()))->digest())->toBe($digest)
        ->and(notationRequest()->digest())->toBe($digest);
});

it('is the sha256 of the sorted-key JSON of every member but source', function () {
    // An independent construction of the definition (ticket 02 Q2): sort keys at every level, drop `source`,
    // encode with the two named flags, hash.
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

    $request = notationRequest();
    $members = $request->toArray();
    unset($members['source']);

    expect(array_keys($members))->toEqualCanonicalizing(['intent', 'title', 'sections', 'formats', 'arrangement', 'composer', 'lyricist', 'lyricBlock'])
        ->and($request->digest())->toBe(hash('sha256', json_encode($sort($members), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
});

it('changes the digest when one melody note changes', function () {
    $payload = notationPayload();
    $payload['intent']['sections'][0]['melody'][1]['pitch'] = 69;

    expect(NotationRequest::from($payload)->digest())->not->toBe(notationRequest()->digest());
});

it('changes the digest when one chord changes', function () {
    $payload = notationPayload();
    $payload['intent']['sections'][1]['chords'][0] = 'Gm';

    expect(NotationRequest::from($payload)->digest())->not->toBe(notationRequest()->digest());
});

it('leaves the digest unchanged by a source-only change', function () {
    $other = notationRequest(['source' => ['chartDigest' => str_repeat('b', 64), 'takeRunId' => 'run-2']]);

    expect($other->digest())->toBe(notationRequest()->digest());
});

it('changes the digest when lyricBlock flips, and an absent lyricBlock digests equal to false', function () {
    $absent = notationPayload(['formats' => ['pdf']]);
    $false = [...$absent, 'lyricBlock' => false];
    $true = [...$absent, 'lyricBlock' => true];

    expect(NotationRequest::from($absent)->digest())->toBe(NotationRequest::from($false)->digest())
        ->and(NotationRequest::from($true)->digest())->not->toBe(NotationRequest::from($false)->digest());
});

it('digests the same whatever output name mapper the host configures', function () {
    $digest = notationRequest()->digest();

    config()->set('data.name_mapping_strategy.output', SnakeCaseMapper::class);

    expect(notationRequest()->digest())->toBe($digest);
});

it('refuses a lyric block with no page format, by name', function () {
    expect(refusalOf(fn () => notationRequest(['formats' => ['musicxml', 'chart'], 'lyricBlock' => true])))
        ->toBe('lyric_block_without_pages')
        ->and(refusalOf(fn () => notationRequest(['formats' => ['svg'], 'lyricBlock' => true])))->toBeNull();
});

it('refuses a lyric block with no section lines, by name', function () {
    $payload = notationPayload(['lyricBlock' => true]);
    $payload['sections'][0]['lines'] = [];

    expect(refusalOf(fn () => NotationRequest::from($payload)))->toBe('lyric_block_empty');
});

it('refuses sections that do not match the intent\'s section count, by name', function () {
    $payload = notationPayload();
    $payload['sections'] = [$payload['sections'][0]];

    expect(refusalOf(fn () => NotationRequest::from($payload)))->toBe('sections_mismatch');
});

it('refuses an empty formats list, by name (formats has no default)', function () {
    $payload = notationPayload();
    $payload['formats'] = [];

    expect(refusalOf(fn () => NotationRequest::from($payload)))->toBe('formats_empty');
});

it('is an InvalidArgumentException carrying its reason in the message', function () {
    $payload = notationPayload();
    $payload['formats'] = [];

    expect(fn () => NotationRequest::from($payload))
        ->toThrow(InvalidArgumentException::class, 'formats_empty');
});
