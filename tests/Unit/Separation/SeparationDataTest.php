<?php

use Splicewire\Composition\Music\Separation\SeparationDispatchData;
use Splicewire\Composition\Music\Separation\SeparationRequest;
use Splicewire\Composition\Music\Separation\SeparationResult;
use Splicewire\Composition\Music\Separation\SeparationRunData;
use Splicewire\Composition\Music\Separation\SeparationRunState;
use Splicewire\Composition\Music\Separation\StemData;

/**
 * The `music.separate` wire Data (music-capability-lift build/10; ticket 03 Q2, E1 amended): the request,
 * the result and its stems, the Task's acknowledgement and the Read op's run answer — whose `result` is
 * non-null exactly when `completed`, and whose `error` is a non-empty string exactly when `failed`.
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php), so every
 * snake_case round trip below is measured under the host's mapper, not the package default.
 */
function separationStemPayload(string $name, array $overrides = []): array
{
    return [
        'name' => $name,
        'media_ref' => "media-{$name}",
        'peak_dbfs' => -1.5,
        'rms_dbfs' => -18.25,
        'relative_db' => 0.0,
        'silent' => false,
        ...$overrides,
    ];
}

function separationResultPayload(array $overrides = []): array
{
    return [
        'stem_set' => 'two_stem',
        'separator' => 'music.separate.roformer',
        'separator_version' => '1.0.0',
        'params_digest' => str_repeat('p', 16),
        'separated_seconds' => 237.0,
        'wall_seconds' => 41.5,
        'all_silent' => false,
        'stems' => [
            separationStemPayload('vocals'),
            separationStemPayload('instrumental', ['rms_dbfs' => -21.0, 'relative_db' => -2.75]),
        ],
        ...$overrides,
    ];
}

function allSilentResultPayload(): array
{
    $silent = ['peak_dbfs' => -96.0, 'rms_dbfs' => -110.0, 'relative_db' => null, 'silent' => true];

    return separationResultPayload([
        'all_silent' => true,
        'stems' => [separationStemPayload('vocals', $silent), separationStemPayload('instrumental', $silent)],
    ]);
}

function separationRunPayload(string $state, array $extra = []): array
{
    return ['run_key' => str_repeat('k', 64), 'state' => $state, ...$extra];
}

it('round-trips a SeparationRequest in snake_case, defaulting to two_stem', function () {
    $request = SeparationRequest::from([
        'audio_ref' => 'media-uuid-1',
        'stem_set' => 'six_stem',
        'params' => ['overlap' => 0.25, 'shifts' => 1],
        'binding' => 'music.separate.roformer',
    ]);

    expect($request->stemSet)->toBe('six_stem')
        ->and($request->toArray())->toBe([
            'audio_ref' => 'media-uuid-1',
            'stem_set' => 'six_stem',
            'params' => ['overlap' => 0.25, 'shifts' => 1],
            'binding' => 'music.separate.roformer',
        ])
        ->and(SeparationRequest::from($request->toArray())->toArray())->toBe($request->toArray());

    $default = SeparationRequest::from(['audio_ref' => 'media-uuid-1']);

    expect($default->stemSet)->toBe('two_stem')
        ->and($default->params)->toBe([])
        ->and($default->binding)->toBeNull();
});

it('carries stem_set as a bare string the spine never validates', function () {
    // 03 Q2 / F10: the measurement precedent — an enum here would mean a spine release per new set.
    expect(SeparationRequest::from(['audio_ref' => 'm', 'stem_set' => 'four_stem'])->stemSet)->toBe('four_stem')
        ->and(SeparationResult::from(separationResultPayload(['stem_set' => 'four_stem']))->stemSet)->toBe('four_stem');
});

it('digests params independently of key order, and only params', function () {
    $a = new SeparationRequest(audioRef: 'm1', params: ['b' => ['y' => 2, 'x' => 1], 'a' => 1]);
    $b = new SeparationRequest(audioRef: 'm2', stemSet: 'six_stem', params: ['a' => 1, 'b' => ['x' => 1, 'y' => 2]], binding: 'pin');
    $c = new SeparationRequest(audioRef: 'm1', params: ['a' => 2, 'b' => ['x' => 1, 'y' => 2]]);

    expect($a->paramsDigest())->toBe($b->paramsDigest())
        ->and($a->paramsDigest())->not->toBe($c->paramsDigest())
        ->and($a->paramsDigest())->toBe(substr(hash('sha256', json_encode(['a' => 1, 'b' => ['x' => 1, 'y' => 2]])), 0, 16));
});

it('round-trips a StemData in snake_case', function () {
    $stem = StemData::from(separationStemPayload('bass', ['relative_db' => -31.4]));

    expect($stem->mediaRef)->toBe('media-bass')
        ->and($stem->relativeDb)->toBe(-31.4)
        ->and($stem->toArray())->toBe([
            'name' => 'bass',
            'media_ref' => 'media-bass',
            'peak_dbfs' => -1.5,
            'rms_dbfs' => -18.25,
            'relative_db' => -31.4,
            'silent' => false,
        ])
        ->and(StemData::from($stem->toArray())->toArray())->toBe($stem->toArray());
});

it('round-trips a SeparationResult with its stems in snake_case', function () {
    $result = SeparationResult::from(separationResultPayload());

    expect($result->stems)->toHaveCount(2)
        ->and($result->stems[1])->toBeInstanceOf(StemData::class)
        ->and($result->stems[1]->relativeDb)->toBe(-2.75)
        ->and(array_keys($result->toArray()))->toBe([
            'stem_set', 'separator', 'separator_version', 'params_digest', 'separated_seconds', 'all_silent', 'stems', 'wall_seconds',
        ])
        ->and($result->toArray()['stems'][1])->toBe(separationStemPayload('instrumental', ['rms_dbfs' => -21.0, 'relative_db' => -2.75]))
        ->and(SeparationResult::from($result->toArray())->toArray())->toBe($result->toArray());
});

it('carries an all-silent run as completed data: all_silent true, every relative_db null', function () {
    $result = SeparationResult::from(allSilentResultPayload());

    expect($result->allSilent)->toBeTrue()
        ->and(array_map(fn (StemData $stem) => $stem->relativeDb, $result->stems))->toBe([null, null])
        ->and(SeparationResult::from($result->toArray())->toArray())->toBe($result->toArray());

    $run = SeparationRunData::from(separationRunPayload('completed', ['result' => allSilentResultPayload(), 'at' => '2026-10-28T16:00:00Z']));

    expect($run->state)->toBe(SeparationRunState::Completed)
        ->and($run->result?->allSilent)->toBeTrue()
        ->and($run->toArray()['result']['stems'][0]['relative_db'])->toBeNull();
});

it('keeps a measured relative_db on a silent stem in a run that is not all silent', function () {
    // E1: `relative_db` is the stem's RMS against the loudest stem in its own run; only a run with no
    // reference (every stem silent) has null values. One silent stem beside signal is still measured.
    $result = SeparationResult::from(separationResultPayload(['stems' => [
        separationStemPayload('vocals'),
        separationStemPayload('instrumental', ['peak_dbfs' => -70.0, 'rms_dbfs' => -88.0, 'relative_db' => -69.75, 'silent' => true]),
    ]]));

    expect($result->allSilent)->toBeFalse()
        ->and($result->stems[1]->silent)->toBeTrue()
        ->and($result->stems[1]->relativeDb)->toBe(-69.75);
});

it('refuses all_silent that disagrees with its stems', function (array $overrides) {
    expect(fn () => SeparationResult::from(separationResultPayload($overrides)))
        ->toThrow(InvalidArgumentException::class, 'all_silent');
})->with([
    'all_silent true over a stem with signal' => [['all_silent' => true]],
    'all_silent false over every stem silent' => [['stems' => [
        separationStemPayload('vocals', ['relative_db' => null, 'silent' => true]),
        separationStemPayload('instrumental', ['relative_db' => null, 'silent' => true]),
    ]]],
    'all_silent true with a measured relative_db' => [['all_silent' => true, 'stems' => [
        separationStemPayload('vocals', ['relative_db' => null, 'silent' => true]),
        separationStemPayload('instrumental', ['relative_db' => -3.0, 'silent' => true]),
    ]]],
    'all_silent true with no stems' => [['all_silent' => true, 'stems' => []]],
]);

it('acknowledges a dispatch with queued only', function () {
    expect(SeparationDispatchData::from(['queued' => true])->toArray())->toBe(['queued' => true])
        ->and(SeparationDispatchData::from(['queued' => false])->queued)->toBeFalse();
});

it('speaks snake_case on the wire in both directions, whatever the host input mapper', function () {
    $run = SeparationRunData::from(separationRunPayload('pending', ['at' => '2026-10-28T16:00:00Z']));

    expect($run->state)->toBe(SeparationRunState::Pending)
        ->and($run->toArray())->toBe([
            'run_key' => str_repeat('k', 64),
            'state' => 'pending',
            'result' => null,
            'error' => null,
            'at' => '2026-10-28T16:00:00Z',
        ])
        ->and(SeparationRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('round-trips each state', function (string $state, array $extra) {
    $run = SeparationRunData::from(separationRunPayload($state, $extra));

    expect($run->state->value)->toBe($state)
        ->and(SeparationRunData::from($run->toArray())->toArray())->toBe($run->toArray());
})->with([
    'none' => ['none', []],
    'pending' => ['pending', ['at' => '2026-10-28T16:00:00Z']],
    'completed' => ['completed', ['result' => separationResultPayload(), 'at' => '2026-10-28T16:00:00Z']],
    'failed' => ['failed', ['error' => 'stale', 'at' => '2026-10-28T16:00:00Z']],
]);

it('holds result exactly when completed across every state × {null, present} result', function (string $state, bool $withResult, bool $accepted) {
    $extra = $withResult ? ['result' => separationResultPayload()] : [];
    if ($state === 'failed') {
        $extra['error'] = 'refused';
    }

    $build = fn () => SeparationRunData::from(separationRunPayload($state, $extra));

    if ($accepted) {
        expect($build()->result === null)->toBe(! $withResult);
    } else {
        expect($build)->toThrow(InvalidArgumentException::class, $state);
    }
})->with([
    'none, null' => ['none', false, true],
    'none, present' => ['none', true, false],
    'pending, null' => ['pending', false, true],
    'pending, present' => ['pending', true, false],
    'completed, null' => ['completed', false, false],
    'completed, present' => ['completed', true, true],
    'failed, null' => ['failed', false, true],
    'failed, present' => ['failed', true, false],
]);

it('holds error exactly when failed across every state × {null, empty, non-empty} error', function (string $state, ?string $error, bool $accepted) {
    $extra = $state === 'completed' ? ['result' => separationResultPayload()] : [];
    if ($error !== null) {
        $extra['error'] = $error;
    }

    $build = fn () => SeparationRunData::from(separationRunPayload($state, $extra));

    if ($accepted) {
        expect($build()->error)->toBe($error);
    } else {
        expect($build)->toThrow(InvalidArgumentException::class, $state);
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
