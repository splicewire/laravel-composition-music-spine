<?php

use Splicewire\Composition\Music\Notation\NotationDispatchData;
use Splicewire\Composition\Music\Notation\NotationFormat;
use Splicewire\Composition\Music\Notation\NotationResult;
use Splicewire\Composition\Music\Notation\NotationRunData;
use Splicewire\Composition\Music\Notation\NotationRunState;

/**
 * The Task + Read pair's wire Data for notation (music-capability-lift build/03; ticket 02 Q2/Q6 amended,
 * ticket 03's `SeparationRunData` semantics): `NotationDispatchData {queued}` and `NotationRunData`, whose
 * `result` is non-null exactly when `completed` and `error` exactly when `failed`.
 */
function notationResultPayload(): array
{
    return [
        'artifacts' => [
            ['format' => 'musicxml', 'artifactRef' => 'media-1', 'contentType' => 'application/vnd.recordare.musicxml+xml', 'bytes' => 375000, 'sha256' => str_repeat('1', 64)],
            ['format' => 'svg', 'artifactRef' => 'media-2', 'page' => 1, 'contentType' => 'image/svg+xml', 'bytes' => 190000, 'sha256' => str_repeat('2', 64)],
        ],
        'engraver' => ['music21' => '10.5.1', 'verovio' => '6.3.0'],
        'engraverVersion' => '1.0.0',
        'requestDigest' => str_repeat('d', 64),
        'runKey' => str_repeat('k', 64),
        'source' => ['chartDigest' => str_repeat('a', 64), 'takeRunId' => 'run-1'],
        'summary' => ['bars' => 24, 'sections' => 6, 'melodyNotes' => 96, 'chordChanges' => 24, 'pages' => 1],
        'wallSeconds' => 3.5,
    ];
}

function runPayload(string $state, array $extra = []): array
{
    return ['run_key' => str_repeat('k', 64), 'request_digest' => str_repeat('d', 64), 'state' => $state, ...$extra];
}

it('round-trips a NotationResult', function () {
    $result = NotationResult::from(notationResultPayload());

    expect($result->artifacts[0]->format)->toBe(NotationFormat::MusicXml)
        ->and($result->artifacts[0]->page)->toBeNull()
        ->and($result->artifacts[1]->page)->toBe(1)
        ->and($result->summary->pages)->toBe(1)
        ->and($result->source->chartDigest)->toBe(str_repeat('a', 64))
        ->and(NotationResult::from($result->toArray())->toArray())->toEqual($result->toArray());
});

it('acknowledges a dispatch with queued only', function () {
    expect(NotationDispatchData::from(['queued' => true])->toArray())->toBe(['queued' => true]);
});

it('speaks snake_case on the wire in both directions, whatever the host input mapper', function () {
    $run = NotationRunData::from(runPayload('pending', ['at' => '2026-10-28T15:00:00Z']));

    expect($run->state)->toBe(NotationRunState::Pending)
        ->and($run->toArray())->toBe([
            'run_key' => str_repeat('k', 64),
            'request_digest' => str_repeat('d', 64),
            'state' => 'pending',
            'result' => null,
            'error' => null,
            'at' => '2026-10-28T15:00:00Z',
        ])
        ->and(NotationRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('carries the result when completed and the error when failed', function () {
    $completed = NotationRunData::from(runPayload('completed', ['result' => notationResultPayload()]));
    $failed = NotationRunData::from(runPayload('failed', ['error' => 'stale']));
    $none = NotationRunData::from(runPayload('none'));

    expect($completed->result)->toBeInstanceOf(NotationResult::class)
        ->and(NotationRunData::from($completed->toArray())->result?->runKey)->toBe(str_repeat('k', 64))
        ->and($failed->error)->toBe('stale')
        ->and($none->result)->toBeNull()->and($none->error)->toBeNull();
});

it('refuses completed without a result', function () {
    expect(fn () => NotationRunData::from(runPayload('completed')))
        ->toThrow(InvalidArgumentException::class, 'completed');
});

it('refuses failed without an error', function () {
    expect(fn () => NotationRunData::from(runPayload('failed')))
        ->toThrow(InvalidArgumentException::class, 'failed');
    expect(fn () => NotationRunData::from(runPayload('failed', ['error' => ''])))
        ->toThrow(InvalidArgumentException::class, 'failed');
});

it('refuses a result outside completed and an error outside failed', function () {
    expect(fn () => NotationRunData::from(runPayload('pending', ['result' => notationResultPayload()])))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => NotationRunData::from(runPayload('completed', ['result' => notationResultPayload(), 'error' => 'x'])))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => NotationRunData::from(runPayload('none', ['error' => 'x'])))
        ->toThrow(InvalidArgumentException::class);
});

it('holds error exactly when failed across every state × {null, empty, non-empty} error', function (string $state, ?string $error, bool $accepted) {
    $extra = $state === 'completed' ? ['result' => notationResultPayload()] : [];
    if ($error !== null) {
        $extra['error'] = $error;
    }

    $build = fn () => NotationRunData::from(runPayload($state, $extra));

    if ($accepted) {
        expect($build()->error)->toBe($error);
    } else {
        expect($build)->toThrow(InvalidArgumentException::class);
    }
})->with([
    'none, null' => ['none', null, true],
    'none, empty' => ['none', '', false],
    'none, non-empty' => ['none', 'x', false],
    'pending, null' => ['pending', null, true],
    'pending, empty' => ['pending', '', false],
    'pending, non-empty' => ['pending', 'x', false],
    'completed, null' => ['completed', null, true],
    'completed, empty' => ['completed', '', false],
    'completed, non-empty' => ['completed', 'x', false],
    'failed, null' => ['failed', null, false],
    'failed, empty' => ['failed', '', false],
    'failed, non-empty' => ['failed', 'stale', true],
]);
