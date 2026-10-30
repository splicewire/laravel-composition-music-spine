<?php

use Spatie\LaravelData\Exceptions\CannotCreateData;
use Splicewire\Composition\Music\Synthesis\SynthesisDispatchData;
use Splicewire\Composition\Music\Synthesis\SynthesisRequest;
use Splicewire\Composition\Music\Synthesis\SynthesisResult;
use Splicewire\Composition\Music\Synthesis\SynthesisRunData;
use Splicewire\Composition\Music\Synthesis\SynthesisRunState;

/**
 * The synthesis Task + Read pair's wire Data (music-capability-lift build/49; ticket 16 Q5, Q17 amended): the Task's
 * acknowledgement `SynthesisDispatchData {queued, run_key}`, whose `run_key` is never empty, and the Read op's
 * `SynthesisRunData`, whose `result` and `audio_ref` are present exactly when `completed` and whose `error` is a
 * non-empty string exactly when `failed`. `SynthesisRequest` and `SynthesisResult` stay as they were (ticket 10).
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php), so every snake_case round
 * trip below is measured under the host's mapper, not the package default. The nested result keeps its own
 * unmapped wire names, as notation's nested result does.
 */
function synthesisRunKey(): string
{
    return 'b3f1c2d4-5e6f-4a7b-8c9d-0e1f2a3b4c5d|music.synthesize|'.str_repeat('v', 16);
}

function synthesisResultPayload(array $overrides = []): array
{
    return [
        'targetUrl' => 'https://media.example.test/synthesis/composite.mp3?signature=abc',
        'renderSeconds' => 184.25,
        'wallSeconds' => null,
        ...$overrides,
    ];
}

function synthesisRunPayload(string $state, array $extra = []): array
{
    return ['run_key' => synthesisRunKey(), 'state' => $state, ...$extra];
}

function completedSynthesisExtra(array $overrides = []): array
{
    return [
        'result' => synthesisResultPayload(),
        'audio_ref' => '7c0d9e8f-1a2b-4c3d-9e4f-5a6b7c8d9e0f',
        'at' => '2026-10-30T12:00:00Z',
        ...$overrides,
    ];
}

it('acknowledges a dispatch with queued and the admitted run_key, snake_case in both directions', function () {
    $dispatch = SynthesisDispatchData::from(['queued' => true, 'run_key' => synthesisRunKey()]);

    expect($dispatch->queued)->toBeTrue()
        ->and($dispatch->runKey)->toBe(synthesisRunKey())
        ->and($dispatch->toArray())->toBe(['queued' => true, 'run_key' => synthesisRunKey()])
        ->and(SynthesisDispatchData::from($dispatch->toArray())->toArray())->toBe($dispatch->toArray());
});

it('carries the run_key it found on a replay that queued nothing', function () {
    // Q17: `handle` records the key it admitted OR found pending or completed; a replay still names its run.
    $dispatch = new SynthesisDispatchData(queued: false, runKey: synthesisRunKey());

    expect($dispatch->toArray())->toBe(['queued' => false, 'run_key' => synthesisRunKey()]);
});

it('refuses a dispatch with an empty run_key', function () {
    expect(fn () => SynthesisDispatchData::from(['queued' => true, 'run_key' => '']))
        ->toThrow(InvalidArgumentException::class, 'run_key')
        ->and(fn () => new SynthesisDispatchData(queued: false, runKey: ''))
        ->toThrow(InvalidArgumentException::class, 'run_key');
});

it('refuses a dispatch with no run_key: the acknowledgement is never {queued} alone', function () {
    expect(fn () => SynthesisDispatchData::from(['queued' => true]))
        ->toThrow(CannotCreateData::class, 'runKey');
});

it('closes the run state set at none, pending, completed and failed', function () {
    expect(array_map(fn (SynthesisRunState $state) => $state->value, SynthesisRunState::cases()))
        ->toBe(['none', 'pending', 'completed', 'failed']);
});

it('speaks snake_case on the run answer in both directions, whatever the host input mapper', function () {
    $run = SynthesisRunData::from(synthesisRunPayload('pending', ['at' => '2026-10-30T12:00:00Z']));

    expect($run->state)->toBe(SynthesisRunState::Pending)
        ->and($run->toArray())->toBe([
            'run_key' => synthesisRunKey(),
            'state' => 'pending',
            'result' => null,
            'audio_ref' => null,
            'error' => null,
            'at' => '2026-10-30T12:00:00Z',
        ])
        ->and(SynthesisRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('carries a completed run\'s SynthesisResult and the composite\'s audio_ref, wall_seconds null', function () {
    $run = SynthesisRunData::from(synthesisRunPayload('completed', completedSynthesisExtra()));

    expect($run->result)->toBeInstanceOf(SynthesisResult::class)
        ->and($run->result?->renderSeconds)->toBe(184.25)
        ->and($run->result?->wallSeconds)->toBeNull()
        ->and($run->audioRef)->toBe('7c0d9e8f-1a2b-4c3d-9e4f-5a6b7c8d9e0f')
        ->and($run->toArray())->toBe([
            'run_key' => synthesisRunKey(),
            'state' => 'completed',
            'result' => synthesisResultPayload(),
            'audio_ref' => '7c0d9e8f-1a2b-4c3d-9e4f-5a6b7c8d9e0f',
            'error' => null,
            'at' => '2026-10-30T12:00:00Z',
        ])
        ->and(SynthesisRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('round-trips each state', function (string $state, array $extra) {
    $run = SynthesisRunData::from(synthesisRunPayload($state, $extra));

    expect($run->state->value)->toBe($state)
        ->and(SynthesisRunData::from($run->toArray())->toArray())->toBe($run->toArray());
})->with([
    'none' => ['none', []],
    'pending' => ['pending', ['at' => '2026-10-30T12:00:00Z']],
    'completed' => ['completed', completedSynthesisExtra()],
    'failed' => ['failed', ['error' => 'stale', 'at' => '2026-10-30T12:00:00Z']],
]);

it('holds result exactly when completed across every state × {null, present} result', function (string $state, bool $withResult, bool $accepted) {
    $extra = $withResult ? ['result' => synthesisResultPayload()] : [];
    if ($state === 'completed') {
        $extra['audio_ref'] = 'composite-uuid';
    }
    if ($state === 'failed') {
        $extra['error'] = 'refused';
    }

    $build = fn () => SynthesisRunData::from(synthesisRunPayload($state, $extra));

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

it('holds audio_ref exactly when completed across every state × {null, empty, present} audio_ref', function (string $state, ?string $audioRef, bool $accepted) {
    $extra = $state === 'completed' ? ['result' => synthesisResultPayload()] : [];
    if ($audioRef !== null) {
        $extra['audio_ref'] = $audioRef;
    }
    if ($state === 'failed') {
        $extra['error'] = 'refused';
    }

    $build = fn () => SynthesisRunData::from(synthesisRunPayload($state, $extra));

    if ($accepted) {
        expect($build()->audioRef)->toBe($audioRef);
    } else {
        expect($build)->toThrow(InvalidArgumentException::class, 'audio_ref');
    }
})->with([
    'none, null' => ['none', null, true],
    'none, empty' => ['none', '', false],
    'none, present' => ['none', 'composite-uuid', false],
    'pending, null' => ['pending', null, true],
    'pending, empty' => ['pending', '', false],
    'pending, present' => ['pending', 'composite-uuid', false],
    'completed, null' => ['completed', null, false],
    'completed, present' => ['completed', 'composite-uuid', true],
    'failed, null' => ['failed', null, true],
    'failed, empty' => ['failed', '', false],
    'failed, present' => ['failed', 'composite-uuid', false],
]);

it('holds error exactly when failed across every state × {null, empty, non-empty} error', function (string $state, ?string $error, bool $accepted) {
    $extra = $state === 'completed' ? ['result' => synthesisResultPayload(), 'audio_ref' => 'composite-uuid'] : [];
    if ($error !== null) {
        $extra['error'] = $error;
    }

    $build = fn () => SynthesisRunData::from(synthesisRunPayload($state, $extra));

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

it('refuses the same pairings when constructed directly, not only through from()', function () {
    $result = SynthesisResult::from(synthesisResultPayload());

    expect(fn () => new SynthesisRunData(runKey: synthesisRunKey(), state: SynthesisRunState::Completed, result: $result))
        ->toThrow(InvalidArgumentException::class, 'audio_ref')
        ->and(fn () => new SynthesisRunData(runKey: synthesisRunKey(), state: SynthesisRunState::Completed, audioRef: 'composite-uuid'))
        ->toThrow(InvalidArgumentException::class, 'result')
        ->and(fn () => new SynthesisRunData(runKey: synthesisRunKey(), state: SynthesisRunState::Pending, audioRef: 'composite-uuid'))
        ->toThrow(InvalidArgumentException::class, 'audio_ref')
        ->and(fn () => new SynthesisRunData(runKey: synthesisRunKey(), state: SynthesisRunState::Failed))
        ->toThrow(InvalidArgumentException::class, 'error');
});

it('leaves SynthesisRequest and SynthesisResult as they were: members, types, defaults and wire names (tickets 10, 16)', function () {
    $members = function (string $class): array {
        $shape = [];

        foreach ((new ReflectionClass($class))->getConstructor()->getParameters() as $parameter) {
            $shape[$parameter->getName()] = [
                (string) $parameter->getType(),
                $parameter->isDefaultValueAvailable() ? ['default' => $parameter->getDefaultValue()] : 'required',
            ];
        }

        return $shape;
    };

    expect($members(SynthesisRequest::class))->toBe([
        'midiRef' => ['string', 'required'],
        'soundfont' => ['?string', ['default' => null]],
    ])
        ->and($members(SynthesisResult::class))->toBe([
            'targetUrl' => ['string', 'required'],
            'renderSeconds' => ['float', 'required'],
            'wallSeconds' => ['?float', ['default' => null]],
        ]);

    $request = SynthesisRequest::from(['midiRef' => 'media-uuid-1', 'soundfont' => 'GeneralUser']);
    $result = SynthesisResult::from(synthesisResultPayload(['wallSeconds' => 3.5]));

    expect($request->toArray())->toBe(['midiRef' => 'media-uuid-1', 'soundfont' => 'GeneralUser'])
        ->and(SynthesisRequest::from($request->toArray())->toArray())->toBe($request->toArray())
        ->and($result->toArray())->toBe(synthesisResultPayload(['wallSeconds' => 3.5]))
        ->and(SynthesisResult::from($result->toArray())->toArray())->toBe($result->toArray());
});
