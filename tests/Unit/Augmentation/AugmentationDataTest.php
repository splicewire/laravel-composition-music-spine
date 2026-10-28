<?php

use Spatie\LaravelData\Exceptions\CannotCastEnum;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Splicewire\Composition\Music\Augmentation\AugmentationBriefData;
use Splicewire\Composition\Music\Augmentation\AugmentationDispatchData;
use Splicewire\Composition\Music\Augmentation\AugmentationErrorCode;
use Splicewire\Composition\Music\Augmentation\AugmentationErrorData;
use Splicewire\Composition\Music\Augmentation\AugmentationIterationData;
use Splicewire\Composition\Music\Augmentation\AugmentationResultData;
use Splicewire\Composition\Music\Augmentation\AugmentationRunData;
use Splicewire\Composition\Music\Augmentation\AugmentationRunState;
use Splicewire\Composition\Music\MusicIntent;

/**
 * The augmentation wire Data (music-capability-lift build/30; ticket 06 Q2 "Failure codes", Q4 amended): the brief
 * and its digest, the Task's acknowledgement, and the Read op's run answer — whose `result` is non-null exactly
 * when `completed`, whose `error` is exactly one closed-set failure exactly when `failed`.
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php), so every snake_case round
 * trip below is measured under the host's mapper, not the package default.
 */
function augmentationIntentPayload(array $overrides = []): array
{
    return [
        'key' => ['tonic' => 'F', 'mode' => 'major'],
        'sections' => [
            ['tag' => 'VERSE', 'chords' => ['F', 'Bb'], 'roles' => [],
                'melody' => [['pitch' => 65, 'start' => 0.0, 'duration' => 1.0]]],
            ['tag' => 'CHORUS', 'chords' => ['C', 'F'], 'roles' => ['drums', 'bass']],
        ],
        'tempo' => 78,
        'meter' => '4/4',
        ...$overrides,
    ];
}

function augmentationBriefPayload(array $overrides = []): array
{
    return [
        'intent' => augmentationIntentPayload(),
        'max_iterations' => 3,
        'mix' => ['gains_db' => ['drums' => -3.0, 'bass' => 1.5], 'pans' => ['keys' => -0.25]],
        'ai_listener' => true,
        ...$overrides,
    ];
}

function augmentationResultPayload(array $overrides = []): array
{
    return [
        'take' => [
            'mix_media_id' => 'media-mix',
            'stem_media_ids' => ['drums' => 'media-drums', 'bass' => 'media-bass', 'keys' => 'media-keys'],
            'midi_media_id' => 'media-midi',
        ],
        'verdict' => ['mandatory' => [], 'advisory' => ['listener degraded'], 'metrics' => ['loudness_lufs' => -14.2, 'clip_ratio' => 0.0]],
        'policy_satisfied' => false,
        'iterations' => [
            ['iteration' => 0, 'stages_run' => ['music.loop.enrich', 'music.loop.oneshot (skipped)', 'music.loop.arrange', 'music.loop.sample', 'music.loop.mix', 'music.loop.measure', 'music.loop.enrich'],
                'metrics' => ['loudness_lufs' => -18.0], 'score' => 4.0, 'best' => true],
            ['iteration' => 1, 'stages_run' => ['music.loop.arrange', 'music.loop.sample', 'music.loop.mix', 'music.loop.measure'],
                'metrics' => ['loudness_lufs' => -14.2], 'score' => 0.2, 'best' => true],
        ],
        'arranger' => ['conduit_id' => 'conduit-1', 'binding_revision' => 'rev-1', 'name' => 'audiostud', 'version' => '2.1.0'],
        ...$overrides,
    ];
}

function augmentationRunPayload(string $state, array $extra = []): array
{
    return ['run_key' => str_repeat('k', 64), 'brief_digest' => str_repeat('b', 64), 'binding_revision' => 'rev-1', 'state' => $state, ...$extra];
}

/** One valid error payload per closed-set code, each with exactly the members its code carries. */
function augmentationErrorPayloads(): array
{
    return [
        'binding_absent' => ['code' => 'binding_absent', 'detail' => 'no active Arranger binding'],
        'binding_inactive' => ['code' => 'binding_inactive', 'detail' => 'the binding is pending'],
        'budget_exceeded' => ['code' => 'budget_exceeded', 'detail' => 'worst case 2091 s exceeds the 1900 s job timeout'],
        'stale' => ['code' => 'stale', 'detail' => 'the queue lock is gone'],
        'binding_changed' => ['code' => 'binding_changed', 'detail' => 'the active revision moved while queued'],
        'binding_stale' => ['code' => 'binding_stale', 'detail' => 'arranger 2.2.0 differs from the pinned 2.1.0'],
        'arranger_refused' => ['code' => 'arranger_refused', 'detail' => 'pickup and changes are not honoured', 'refusal_reason' => 'unsupported_intent_members', 'members' => ['pickup', 'changes']],
        'arranger_rejected_signature' => ['code' => 'arranger_rejected_signature', 'detail' => '401'],
        'arranger_invalid_result' => ['code' => 'arranger_invalid_result', 'detail' => 'no MThd header'],
        'arranger_unreachable' => ['code' => 'arranger_unreachable', 'detail' => 'connect timed out', 'attempts' => 2],
        'stage_failed' => ['code' => 'stage_failed', 'detail' => 'the sampler runner exited 1', 'stage' => 'music.loop.sample'],
        'mandatory_verdict' => ['code' => 'mandatory_verdict', 'detail' => 'the take clipped', 'members' => ['clipping above -0.1 dBFS']],
        'timed_out' => ['code' => 'timed_out', 'detail' => 'the job exceeded 1900 s'],
    ];
}

it('round-trips a brief in snake_case, its mix overrides as floats', function () {
    $brief = AugmentationBriefData::from(augmentationBriefPayload());

    expect($brief->intent)->toBeInstanceOf(MusicIntent::class)
        ->and($brief->maxIterations)->toBe(3)
        ->and($brief->aiListener)->toBeTrue()
        ->and($brief->mix?->gainsDb)->toBe(['drums' => -3.0, 'bass' => 1.5])
        ->and(array_keys($brief->toArray()))->toBe(['intent', 'max_iterations', 'mix', 'ai_listener'])
        ->and($brief->toArray()['mix'])->toBe(['gains_db' => ['drums' => -3.0, 'bass' => 1.5], 'pans' => ['keys' => -0.25]])
        ->and(AugmentationBriefData::from($brief->toArray())->toArray())->toBe($brief->toArray());

    $plain = AugmentationBriefData::from(['intent' => augmentationIntentPayload(), 'max_iterations' => 1]);

    expect($plain->mix)->toBeNull()
        ->and($plain->aiListener)->toBeFalse()
        ->and(AugmentationBriefData::from(['intent' => augmentationIntentPayload(), 'max_iterations' => 1, 'mix' => ['pans' => ['bass' => 1]]])->mix?->pans)
        ->toBe(['bass' => 1.0]);
});

it('refuses max_iterations outside 1 to 3', function (int $n, bool $accepted) {
    $build = fn () => AugmentationBriefData::from(augmentationBriefPayload(['max_iterations' => $n]));

    if ($accepted) {
        expect($build()->maxIterations)->toBe($n);
    } else {
        expect($build)->toThrow(InvalidArgumentException::class, 'max_iterations');
    }
})->with([
    '0' => [0, false],
    '1' => [1, true],
    '3' => [3, true],
    '4' => [4, false],
]);

it('declares the 1 to 3 bound as a validation rule for the op', function () {
    expect(AugmentationBriefData::getValidationRules(augmentationBriefPayload())['max_iterations'])
        ->toContain('between:1,3');
});

it('digests a brief stably: key order, defaults and a ladder-migrated intent do not move it', function () {
    $digest = AugmentationBriefData::from(augmentationBriefPayload())->digest();

    $reordered = augmentationBriefPayload();
    $reordered['mix'] = ['pans' => ['keys' => -0.25], 'gains_db' => ['bass' => 1.5, 'drums' => -3]];
    $reordered = array_reverse($reordered, true);

    $plain = ['intent' => augmentationIntentPayload(), 'max_iterations' => 2];
    $explicit = [...$plain, 'mix' => null, 'ai_listener' => false];
    $migrated = ['intent' => augmentationIntentPayload(['pickup' => 0]), 'max_iterations' => 2];

    expect($digest)->toMatch('/^[0-9a-f]{64}$/')
        ->and(AugmentationBriefData::from($reordered)->digest())->toBe($digest)
        ->and(AugmentationBriefData::from($explicit)->digest())->toBe(AugmentationBriefData::from($plain)->digest())
        ->and(AugmentationBriefData::from($migrated)->digest())->toBe(AugmentationBriefData::from($plain)->digest());
});

it('changes the brief digest with any member', function (array $overrides) {
    expect(AugmentationBriefData::from(augmentationBriefPayload($overrides))->digest())
        ->not->toBe(AugmentationBriefData::from(augmentationBriefPayload())->digest());
})->with([
    'a chord' => [['intent' => augmentationIntentPayload(['tempo' => 80])]],
    'a real pickup' => [['intent' => augmentationIntentPayload(['pickup' => 1.0])]],
    'max_iterations' => [['max_iterations' => 2]],
    'a gain' => [['mix' => ['gains_db' => ['drums' => -2.0, 'bass' => 1.5], 'pans' => ['keys' => -0.25]]]],
    'no mix' => [['mix' => null]],
    'ai_listener' => [['ai_listener' => false]],
]);

it('digests the brief the same whatever output name mapper the host configures', function () {
    $digest = AugmentationBriefData::from(augmentationBriefPayload())->digest();

    config()->set('data.name_mapping_strategy.output', SnakeCaseMapper::class);

    expect(AugmentationBriefData::from(augmentationBriefPayload())->digest())->toBe($digest);
});

it('acknowledges a dispatch with queued only', function () {
    expect(AugmentationDispatchData::from(['queued' => true])->toArray())->toBe(['queued' => true])
        ->and(AugmentationDispatchData::from(['queued' => false])->queued)->toBeFalse();
});

it('round-trips a result in snake_case: take, verdict, policy_satisfied, iterations, arranger', function () {
    $result = AugmentationResultData::from(augmentationResultPayload());

    expect($result->take->stemMediaIds)->toBe(['drums' => 'media-drums', 'bass' => 'media-bass', 'keys' => 'media-keys'])
        ->and($result->iterations[1])->toBeInstanceOf(AugmentationIterationData::class)
        ->and($result->iterations[1]->stagesRun)->toHaveCount(4)
        ->and($result->policySatisfied)->toBeFalse()
        ->and($result->arranger->bindingRevision)->toBe('rev-1')
        ->and(array_keys($result->toArray()))->toBe(['take', 'verdict', 'policy_satisfied', 'iterations', 'arranger'])
        ->and(array_keys($result->toArray()['take']))->toBe(['mix_media_id', 'stem_media_ids', 'midi_media_id'])
        ->and(array_keys($result->toArray()['iterations'][0]))->toBe(['iteration', 'stages_run', 'metrics', 'score', 'best'])
        ->and(array_keys($result->toArray()['arranger']))->toBe(['conduit_id', 'binding_revision', 'name', 'version'])
        ->and($result->toArray()['verdict'])->toBe(['mandatory' => [], 'advisory' => ['listener degraded'], 'metrics' => ['loudness_lufs' => -14.2, 'clip_ratio' => 0.0]])
        ->and(AugmentationResultData::from($result->toArray())->toArray())->toBe($result->toArray());
});

it('carries an unscored pass as a null score, never infinity', function () {
    $iteration = AugmentationIterationData::from(['iteration' => 0, 'stages_run' => [], 'metrics' => ['x' => 1], 'score' => null, 'best' => false]);

    expect($iteration->score)->toBeNull()
        ->and($iteration->metrics)->toBe(['x' => 1.0])
        ->and(json_encode($iteration->toArray(), JSON_THROW_ON_ERROR))->toContain('"score":null');
});

it('refuses a kept verdict that carries a mandatory failure', function () {
    expect(fn () => AugmentationResultData::from(augmentationResultPayload(['verdict' => ['mandatory' => ['clipped'], 'advisory' => [], 'metrics' => []]])))
        ->toThrow(InvalidArgumentException::class, 'mandatory');
});

it('round-trips one error per closed-set code', function (array $payload) {
    $error = AugmentationErrorData::from($payload);

    expect($error->code->value)->toBe($payload['code'])
        ->and(array_keys($error->toArray()))->toBe(['code', 'detail', 'stage', 'attempts', 'refusal_reason', 'members'])
        ->and(AugmentationErrorData::from($error->toArray())->toArray())->toBe($error->toArray());
})->with(array_map(fn (array $payload) => [$payload], augmentationErrorPayloads()));

it('closes the code set at the thirteen codes ticket 06 names', function () {
    expect(array_map(fn (AugmentationErrorCode $c) => $c->value, AugmentationErrorCode::cases()))
        ->toEqualCanonicalizing(array_keys(augmentationErrorPayloads()))
        ->and(array_values(array_map(fn (AugmentationErrorCode $c) => $c->value, array_filter(AugmentationErrorCode::cases(), fn ($c) => $c->atOpEntry()))))
        ->toBe(['binding_absent', 'binding_inactive', 'budget_exceeded'])
        ->and(array_values(array_map(fn (AugmentationErrorCode $c) => $c->value, array_filter(AugmentationErrorCode::cases(), fn ($c) => ! $c->atRunTime()))))
        ->toBe(['budget_exceeded']);
});

it('refuses an unknown error code', function () {
    expect(fn () => AugmentationErrorData::from(['code' => 'arranger_exploded', 'detail' => 'x']))->toThrow(CannotCastEnum::class)
        ->and(fn () => AugmentationRunData::from(augmentationRunPayload('failed', ['error' => ['code' => 'arranger_exploded', 'detail' => 'x']])))
        ->toThrow(CannotCastEnum::class);
});

it('refuses a member on a code it does not belong to, or a code without its member', function (array $payload, string $message) {
    expect(fn () => AugmentationErrorData::from($payload))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'stage_failed without stage' => [['code' => 'stage_failed', 'detail' => 'x'], 'must name its stage'],
    'stage_failed naming arrange' => [['code' => 'stage_failed', 'detail' => 'x', 'stage' => 'music.loop.arrange'], 'stage must be one of'],
    'stage on timed_out' => [['code' => 'timed_out', 'detail' => 'x', 'stage' => 'music.loop.mix'], 'names no stage'],
    'arranger_refused without reason' => [['code' => 'arranger_refused', 'detail' => 'x'], 'must carry its refusal_reason'],
    'arranger_refused with an empty reason' => [['code' => 'arranger_refused', 'detail' => 'x', 'refusal_reason' => ''], 'non-empty refusal_reason'],
    'refusal_reason on stale' => [['code' => 'stale', 'detail' => 'x', 'refusal_reason' => 'r'], 'carries no refusal_reason'],
    'arranger_unreachable without attempts' => [['code' => 'arranger_unreachable', 'detail' => 'x'], 'must carry its attempts'],
    'arranger_unreachable with zero attempts' => [['code' => 'arranger_unreachable', 'detail' => 'x', 'attempts' => 0], 'at least 1'],
    'attempts on arranger_refused' => [['code' => 'arranger_refused', 'detail' => 'x', 'refusal_reason' => 'r', 'attempts' => 1], 'carries no attempts'],
    'mandatory_verdict without members' => [['code' => 'mandatory_verdict', 'detail' => 'x'], 'must list the mandatory failures'],
    'members on binding_stale' => [['code' => 'binding_stale', 'detail' => 'x', 'members' => ['pickup']], 'carries no members'],
]);

it('accepts every loop stage a stage_failed may name', function (string $stage) {
    expect(AugmentationErrorData::from(['code' => 'stage_failed', 'detail' => 'x', 'stage' => $stage])->stage)->toBe($stage);
})->with(AugmentationErrorData::STAGES);

it('accepts an arranger refusal that names no members', function () {
    expect(AugmentationErrorData::from(['code' => 'arranger_refused', 'detail' => 'x', 'refusal_reason' => 'busy'])->members)->toBe([]);
});

it('speaks snake_case on the wire in both directions, whatever the host input mapper', function () {
    $run = AugmentationRunData::from(augmentationRunPayload('pending', ['at' => '2026-10-28T16:00:00Z']));

    expect($run->state)->toBe(AugmentationRunState::Pending)
        ->and($run->toArray())->toBe([
            'run_key' => str_repeat('k', 64),
            'brief_digest' => str_repeat('b', 64),
            'binding_revision' => 'rev-1',
            'state' => 'pending',
            'result' => null,
            'error' => null,
            'at' => '2026-10-28T16:00:00Z',
        ])
        ->and(AugmentationRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('round-trips each state', function (string $state, array $extra) {
    $run = AugmentationRunData::from(augmentationRunPayload($state, $extra));

    expect($run->state->value)->toBe($state)
        ->and(AugmentationRunData::from($run->toArray())->toArray())->toBe($run->toArray());
})->with([
    'none' => ['none', []],
    'pending' => ['pending', ['at' => '2026-10-28T16:00:00Z']],
    'completed' => ['completed', ['result' => augmentationResultPayload(), 'at' => '2026-10-28T16:00:00Z']],
    'failed' => ['failed', ['error' => augmentationErrorPayloads()['arranger_refused'], 'at' => '2026-10-28T16:00:00Z']],
]);

it('holds result exactly when completed across every state × {null, present} result', function (string $state, bool $withResult, bool $accepted) {
    $extra = $withResult ? ['result' => augmentationResultPayload()] : [];
    if ($state === 'failed') {
        $extra['error'] = augmentationErrorPayloads()['stale'];
    }

    $build = fn () => AugmentationRunData::from(augmentationRunPayload($state, $extra));

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

it('holds error exactly when failed across every state × {null, empty string, empty object, present} error', function (string $state, string $error, string|bool $outcome) {
    $extra = $state === 'completed' ? ['result' => augmentationResultPayload()] : [];
    $value = ['null' => null, 'empty string' => '', 'empty object' => [], 'present' => augmentationErrorPayloads()['stale']][$error];
    if ($error !== 'null') {
        $extra['error'] = $value;
    }

    $build = fn () => AugmentationRunData::from(augmentationRunPayload($state, $extra));

    if ($outcome === true) {
        expect($build()->error?->code->value)->toBe($error === 'present' ? 'stale' : null);
    } else {
        expect($build)->toThrow($outcome, $outcome === InvalidArgumentException::class ? $state : null);
    }
})->with([
    // An empty error never hydrates, in any state: it is not "absent" and it is not a failure.
    'none, null' => ['none', 'null', true],
    'none, empty string' => ['none', 'empty string', CannotCreateData::class],
    'none, empty object' => ['none', 'empty object', CannotCreateData::class],
    'none, present' => ['none', 'present', InvalidArgumentException::class],
    'pending, null' => ['pending', 'null', true],
    'pending, empty string' => ['pending', 'empty string', CannotCreateData::class],
    'pending, empty object' => ['pending', 'empty object', CannotCreateData::class],
    'pending, present' => ['pending', 'present', InvalidArgumentException::class],
    'completed, null' => ['completed', 'null', true],
    'completed, empty string' => ['completed', 'empty string', CannotCreateData::class],
    'completed, empty object' => ['completed', 'empty object', CannotCreateData::class],
    'completed, present' => ['completed', 'present', InvalidArgumentException::class],
    'failed, null' => ['failed', 'null', InvalidArgumentException::class],
    'failed, empty string' => ['failed', 'empty string', CannotCreateData::class],
    'failed, empty object' => ['failed', 'empty object', CannotCreateData::class],
    'failed, present' => ['failed', 'present', true],
]);

it('carries every run-time code on a failed run, and refuses budget_exceeded there', function (array $payload) {
    $build = fn () => AugmentationRunData::from(augmentationRunPayload('failed', ['error' => $payload]));

    if ($payload['code'] === 'budget_exceeded') {
        expect($build)->toThrow(InvalidArgumentException::class, 'op entry');
    } else {
        expect($build()->error?->code->value)->toBe($payload['code']);
    }
})->with(array_map(fn (array $payload) => [$payload], augmentationErrorPayloads()));

it('refuses a completed result whose arranger is not the pinned binding revision', function () {
    $result = augmentationResultPayload();
    $result['arranger']['binding_revision'] = 'rev-2';

    expect(fn () => AugmentationRunData::from(augmentationRunPayload('completed', ['result' => $result])))
        ->toThrow(InvalidArgumentException::class, 'pinned binding revision');
});
