<?php

use Spatie\LaravelData\Exceptions\CannotCastEnum;
use Splicewire\Composition\Music\ChordChange;
use Splicewire\Composition\Music\Transcription\AlignedChordData;
use Splicewire\Composition\Music\Transcription\ChartDraftData;
use Splicewire\Composition\Music\Transcription\DraftBarData;
use Splicewire\Composition\Music\Transcription\DraftChangeMetaData;
use Splicewire\Composition\Music\Transcription\PlanConformanceData;
use Splicewire\Composition\Music\Transcription\PlanConformanceSectionData;
use Splicewire\Composition\Music\Transcription\SectionStartData;
use Splicewire\Composition\Music\Transcription\TempoPriorData;
use Splicewire\Composition\Music\Transcription\TempoPriorSource;
use Splicewire\Composition\Music\Transcription\TranscriptionDispatchData;
use Splicewire\Composition\Music\Transcription\TranscriptionNotesData;
use Splicewire\Composition\Music\Transcription\TranscriptionResult;
use Splicewire\Composition\Music\Transcription\TranscriptionRunData;
use Splicewire\Composition\Music\Transcription\TranscriptionRunState;
use Splicewire\Composition\Music\Transcription\TranscriptionStageData;

/**
 * The `music.transcribe` wire Data (music-capability-lift build/15; ticket 04 Q2 amended, E1, Q5, Q6; ticket 08 Q6):
 * the run answer, the dispatch acknowledgement, the result, the chart draft and its bars, the change confidence, the
 * tempo prior and plan conformance. Every two-word member is snake_case on the wire.
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php), so every snake_case round
 * trip below is measured under the host's mapper, not the package default.
 */
function draftBarPayload(int $index, float $startBeat, float $beats = 4.0, array $overrides = []): array
{
    return [
        'index' => $index,
        'start_s' => 1.25 + $startBeat * 0.625,
        'end_s' => 1.25 + ($startBeat + $beats) * 0.625,
        'start_beat' => $startBeat,
        'beats' => $beats,
        'confidence' => 0.75,
        'reasons' => [],
        ...$overrides,
    ];
}

function chartDraftPayload(array $overrides = []): array
{
    return [
        'key' => ['tonic' => 'G', 'mode' => 'major'],
        'tempo_bpm' => 96.5,
        'tempo_prior' => ['bpm' => 96, 'source' => 'owner'],
        'meter' => '4/4',
        'pickup_beats' => 1.0,
        'downbeat_margin' => 0.31,
        'changes' => [
            ['symbol' => 'D', 'start' => -1.0],
            ['symbol' => 'G', 'start' => 0.0],
            ['symbol' => 'Em', 'start' => 2.0],
            ['symbol' => 'C', 'start' => 4.0],
        ],
        'change_meta' => [
            ['index' => 0, 'confidence' => 0.2, 'reasons' => ['ungated: pickup']],
            ['index' => 2, 'confidence' => 0.4, 'reasons' => ['ungated: sub-bar change', 'chord_margin']],
        ],
        'bars' => [
            draftBarPayload(0, 0.0, overrides: ['confidence' => 0.2, 'reasons' => ['ungated: sub-bar change']]),
            draftBarPayload(1, 4.0),
        ],
        'review_order' => [0, 1],
        ...$overrides,
    ];
}

function planConformancePayload(array $overrides = []): array
{
    return [
        'plan_digest' => str_repeat('d', 64),
        'sections' => [
            [
                'label' => 'Verse',
                'plan_chords' => ['G', 'Em', 'C', 'D'],
                'aligned' => [
                    ['chord' => 'G', 'start_s' => 1.25, 'end_s' => 3.75],
                    ['chord' => 'Em', 'start_s' => 3.75, 'end_s' => 6.25],
                    ['chord' => 'C', 'start_s' => 6.25, 'end_s' => 8.75],
                    ['chord' => 'D', 'start_s' => 8.75, 'end_s' => 11.25],
                ],
                'conformance' => 0.82,
                'free_root_agreement' => 0.9,
                'reason' => null,
            ],
            [
                'label' => 'Chorus',
                'plan_chords' => ['C', 'D'],
                'aligned' => [
                    ['chord' => 'C', 'start_s' => 11.25, 'end_s' => 11.875],
                    ['chord' => 'D', 'start_s' => 11.875, 'end_s' => 12.5],
                ],
                'conformance' => null,
                'free_root_agreement' => 0.5,
                'reason' => 'compressed',
            ],
        ],
        'overall' => 0.82,
        'reason' => null,
        ...$overrides,
    ];
}

function transcriptionResultPayload(array $overrides = []): array
{
    return [
        'separation_run_key' => str_repeat('s', 64),
        'stages' => [
            ['stage' => 'beats', 'name' => 'music.transcribe.stage.beats.librosa', 'version' => '1.0.0'],
            ['stage' => 'chords', 'name' => 'music.transcribe.stage.chords.chroma', 'version' => '1.0.0'],
            ['stage' => 'notes.bass', 'name' => 'music.transcribe.stage.notes.basic-pitch', 'version' => '0.4.0'],
        ],
        'draft' => chartDraftPayload(),
        'notes' => [
            ['role' => 'bass', 'media_ref' => 'media-bass-midi', 'relative_db' => -6.5, 'gated' => false],
            ['role' => 'guitar', 'media_ref' => null, 'relative_db' => -31.0, 'gated' => true],
        ],
        'melody_ref' => null,
        'plan_conformance' => planConformancePayload(),
        'transcribed_seconds' => 237.0,
        'gated' => true,
        'melody_reason' => 'vocal leg uncalibrated',
        ...$overrides,
    ];
}

function transcriptionRunPayload(string $state, array $extra = []): array
{
    return ['run_key' => str_repeat('k', 64), 'state' => $state, ...$extra];
}

/** Every key at every associative level of a payload, dotted — the wire's whole vocabulary. */
function wireKeys(array $payload, string $prefix = ''): array
{
    $keys = [];
    foreach ($payload as $key => $value) {
        $path = is_int($key) ? $prefix : $prefix.$key;
        if (! is_int($key)) {
            $keys[] = $path;
        }
        if (is_array($value)) {
            $keys = [...$keys, ...wireKeys($value, is_int($key) ? $prefix : $path.'.')];
        }
    }

    return array_values(array_unique($keys));
}

it('round-trips a ChartDraftData in the draft coordinate, in snake_case', function () {
    $draft = ChartDraftData::from(chartDraftPayload());

    expect($draft->key->tonic)->toBe('G')
        ->and($draft->changes[0])->toBeInstanceOf(ChordChange::class)
        ->and($draft->changes[0]->start)->toBe(-1.0)
        ->and($draft->changeMeta[1])->toBeInstanceOf(DraftChangeMetaData::class)
        ->and($draft->bars[1])->toBeInstanceOf(DraftBarData::class)
        ->and($draft->bars[1]->startBeat)->toBe(4.0)
        ->and($draft->totalBeats())->toBe(8.0)
        ->and(array_keys($draft->toArray()))->toBe([
            'key', 'tempo_bpm', 'tempo_prior', 'meter', 'pickup_beats', 'downbeat_margin', 'changes', 'change_meta', 'bars', 'review_order',
        ])
        ->and(array_keys($draft->toArray()['bars'][0]))->toBe(['index', 'start_s', 'end_s', 'start_beat', 'beats', 'confidence', 'reasons'])
        ->and($draft->toArray())->toBe(chartDraftPayload())
        ->and(ChartDraftData::from($draft->toArray())->toArray())->toBe($draft->toArray());
});

it('keeps confidence out of ChordChange: it rides in change_meta by index', function () {
    $draft = ChartDraftData::from(chartDraftPayload());

    expect(array_keys($draft->toArray()['changes'][2]))->toBe(['symbol', 'start'])
        ->and($draft->changeMeta[1]->index)->toBe(2)
        ->and($draft->changes[$draft->changeMeta[1]->index]->symbol)->toBe('Em');
});

it('round-trips tempo_prior null and each source', function (?array $prior) {
    $draft = ChartDraftData::from(chartDraftPayload(['tempo_prior' => $prior]));

    expect($draft->toArray()['tempo_prior'])->toBe($prior)
        ->and(ChartDraftData::from($draft->toArray())->toArray())->toBe($draft->toArray());

    if ($prior === null) {
        expect($draft->tempoPrior)->toBeNull();
    } else {
        expect($draft->tempoPrior)->toBeInstanceOf(TempoPriorData::class)
            ->and($draft->tempoPrior->source)->toBe(TempoPriorSource::from($prior['source']))
            ->and($draft->tempoPrior->bpm)->toBe($prior['bpm']);
    }
})->with([
    'none' => [null],
    'owner' => [['bpm' => 72, 'source' => 'owner']],
    'plan' => [['bpm' => 96, 'source' => 'plan']],
    'drums' => [['bpm' => 144, 'source' => 'drums']],
]);

it('reads an absent tempo_prior as none', function () {
    $payload = chartDraftPayload();
    unset($payload['tempo_prior']);

    expect(ChartDraftData::from($payload)->tempoPrior)->toBeNull();
});

it('closes the tempo prior source set at owner, plan and drums', function () {
    expect(array_map(fn (TempoPriorSource $s) => $s->value, TempoPriorSource::cases()))->toBe(['owner', 'plan', 'drums'])
        ->and(fn () => TempoPriorData::from(['bpm' => 96, 'source' => 'listener']))->toThrow(CannotCastEnum::class, 'listener');
});

it('refuses a tempo prior that is not a positive integer BPM', function (int $bpm) {
    expect(fn () => TempoPriorData::from(['bpm' => $bpm, 'source' => 'owner']))
        ->toThrow(InvalidArgumentException::class, 'positive integer BPM');
})->with(['zero' => [0], 'negative' => [-96]]);

it('holds the draft coordinate at construction', function (array $overrides, string $message) {
    expect(fn () => ChartDraftData::from(chartDraftPayload($overrides)))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'zero tempo' => [['tempo_bpm' => 0.0], 'tempo_bpm'],
    'negative tempo' => [['tempo_bpm' => -96.0], 'tempo_bpm'],
    'negative pickup' => [['pickup_beats' => -1.0, 'changes' => [['symbol' => 'G', 'start' => 0.0]], 'change_meta' => []], 'pickup_beats'],
    'bar out of index order' => [['bars' => [draftBarPayload(1, 0.0), draftBarPayload(0, 4.0)]], 'index'],
    'bar of no beats' => [['bars' => [draftBarPayload(0, 0.0), draftBarPayload(1, 4.0, beats: 0.0)]], 'positive number of beats'],
    'bar 0 not at beat 0' => [['bars' => [draftBarPayload(0, 1.0), draftBarPayload(1, 5.0)]], 'starts at beat'],
    'gap between bars' => [['bars' => [draftBarPayload(0, 0.0), draftBarPayload(1, 5.0)]], 'starts at beat'],
    'changes not increasing' => [['changes' => [['symbol' => 'G', 'start' => 0.0], ['symbol' => 'C', 'start' => 0.0]], 'change_meta' => []], 'strictly increasing'],
    'change before the pickup' => [['changes' => [['symbol' => 'D', 'start' => -2.0], ['symbol' => 'G', 'start' => 0.0]], 'change_meta' => []], 'before the pickup'],
    'change past the last bar' => [['changes' => [['symbol' => 'G', 'start' => 0.0], ['symbol' => 'C', 'start' => 8.0]], 'change_meta' => []], 'past the last bar'],
    'change_meta naming no change' => [['change_meta' => [['index' => 4, 'confidence' => 0.1, 'reasons' => []]]], 'change_meta names change 4'],
    'change_meta naming a change twice' => [['change_meta' => [['index' => 1, 'confidence' => 0.1, 'reasons' => []], ['index' => 1, 'confidence' => 0.2, 'reasons' => []]]], 'twice'],
    'review_order naming no bar' => [['review_order' => [0, 2]], 'review_order names bar 2'],
    'review_order naming a bar twice' => [['review_order' => [1, 1]], 'twice'],
]);

it('refuses a change_meta entry that is not DraftChangeMetaData when constructed directly', function () {
    $draft = ChartDraftData::from(chartDraftPayload());

    expect(fn () => new ChartDraftData(
        key: $draft->key, tempoBpm: 96.0, tempoPrior: null, meter: '4/4', pickupBeats: 1.0, downbeatMargin: 0.3,
        changes: $draft->changes, changeMeta: [['index' => 0, 'confidence' => 0.2, 'reasons' => []]], bars: $draft->bars, reviewOrder: [],
    ))->toThrow(InvalidArgumentException::class, 'DraftChangeMetaData');
});

it('admits a corrected draft: no change_meta, an empty review_order, re-cut bars', function () {
    // 05 E2: the worksheet parser builds a corrected ChartDraftData — the owner's changes, re-cut bars, no review.
    $draft = ChartDraftData::from(chartDraftPayload([
        'pickup_beats' => 0.0,
        'changes' => [['symbol' => 'G', 'start' => 0.0], ['symbol' => 'C', 'start' => 3.0]],
        'change_meta' => [],
        'bars' => [draftBarPayload(0, 0.0, 3.0), draftBarPayload(1, 3.0, 3.0)],
        'review_order' => [],
    ]));

    expect($draft->reviewOrder)->toBe([])
        ->and($draft->totalBeats())->toBe(6.0);
});

it('round-trips a TranscriptionResult with every member snake_case', function () {
    $result = TranscriptionResult::from(transcriptionResultPayload());

    expect($result->stages[2])->toBeInstanceOf(TranscriptionStageData::class)
        ->and($result->draft)->toBeInstanceOf(ChartDraftData::class)
        ->and($result->notes[1])->toBeInstanceOf(TranscriptionNotesData::class)
        ->and($result->notes[1]->gated)->toBeTrue()
        ->and($result->planConformance)->toBeInstanceOf(PlanConformanceData::class)
        ->and($result->planConformance->sections[0]->aligned[0])->toBeInstanceOf(AlignedChordData::class)
        ->and(array_keys($result->toArray()))->toBe([
            'separation_run_key', 'stages', 'draft', 'notes', 'melody_ref', 'plan_conformance', 'transcribed_seconds', 'gated', 'melody_reason',
        ])
        ->and($result->toArray())->toBe(transcriptionResultPayload())
        ->and(TranscriptionResult::from($result->toArray())->toArray())->toBe($result->toArray());

    // No camelCase member anywhere on the wire, at any depth.
    $camel = array_filter(wireKeys($result->toArray()), fn (string $k) => preg_match('/[A-Z]/', $k) === 1);
    expect($camel)->toBe([]);
});

it('round-trips a result with a melody, without a plan, and ungated', function () {
    $result = TranscriptionResult::from(transcriptionResultPayload([
        'melody_ref' => 'media-melody-midi',
        'melody_reason' => null,
        'plan_conformance' => null,
        'gated' => false,
    ]));

    expect($result->melodyRef)->toBe('media-melody-midi')
        ->and($result->planConformance)->toBeNull()
        ->and($result->gated)->toBeFalse()
        ->and(TranscriptionResult::from($result->toArray())->toArray())->toBe($result->toArray());
});

it('names why a result has no melody, exactly when it has none', function (?string $ref, ?string $reason) {
    expect(fn () => TranscriptionResult::from(transcriptionResultPayload(['melody_ref' => $ref, 'melody_reason' => $reason])))
        ->toThrow(InvalidArgumentException::class, 'melody');
})->with([
    'neither' => [null, null],
    'an empty reason' => [null, ''],
    'both' => ['media-melody-midi', 'vocal leg uncalibrated'],
    'an empty ref' => ['', null],
]);

it('records a gated stem without media and a transcribed one with it', function (bool $gated, ?string $ref) {
    expect(fn () => TranscriptionNotesData::from(['role' => 'piano', 'media_ref' => $ref, 'relative_db' => -12.0, 'gated' => $gated]))
        ->toThrow(InvalidArgumentException::class, 'piano');
})->with([
    'gated with media' => [true, 'media-piano-midi'],
    'transcribed without media' => [false, null],
    'transcribed with an empty ref' => [false, ''],
]);

it('round-trips a TranscriptionNotesData whose separation run had no reference', function () {
    $notes = TranscriptionNotesData::from(['role' => 'other', 'media_ref' => null, 'relative_db' => null, 'gated' => true]);

    expect($notes->toArray())->toBe(['role' => 'other', 'media_ref' => null, 'relative_db' => null, 'gated' => true])
        ->and(TranscriptionNotesData::from($notes->toArray())->toArray())->toBe($notes->toArray());
});

it('round-trips each nested Data on its own', function (string $class, array $payload) {
    $data = $class::from($payload);

    expect($data->toArray())->toBe($payload)
        ->and($class::from($data->toArray())->toArray())->toBe($payload);
})->with([
    'TranscriptionStageData' => [TranscriptionStageData::class, ['stage' => 'notes.vocals', 'name' => 'music.transcribe.stage.notes.pyin', 'version' => '2']],
    'DraftBarData' => [DraftBarData::class, draftBarPayload(3, 12.0, 3.0, ['reasons' => ['grid', 'evidence']])],
    'DraftChangeMetaData' => [DraftChangeMetaData::class, ['index' => 0, 'confidence' => 0.5, 'reasons' => ['ungated: pickup']]],
    'TempoPriorData' => [TempoPriorData::class, ['bpm' => 120, 'source' => 'drums']],
    'AlignedChordData' => [AlignedChordData::class, ['chord' => 'Bb', 'start_s' => 0.5, 'end_s' => 2.75]],
    'PlanConformanceSectionData' => [PlanConformanceSectionData::class, planConformancePayload()['sections'][0]],
    'PlanConformanceData' => [PlanConformanceData::class, planConformancePayload()],
    'SectionStartData' => [SectionStartData::class, ['tag' => 'PRE-CHORUS', 'label' => 'Pre', 'start_bar' => 8]],
    'TranscriptionDispatchData' => [TranscriptionDispatchData::class, ['queued' => true]],
]);

it('carries a null plan conformance score only with a named reason', function (string $reason) {
    $unalignable = PlanConformanceData::from(planConformancePayload([
        'sections' => [[...planConformancePayload()['sections'][0], 'aligned' => [], 'conformance' => null, 'free_root_agreement' => null, 'reason' => $reason]],
        'overall' => null,
        'reason' => $reason,
    ]));

    expect($unalignable->overall)->toBeNull()
        ->and($unalignable->reason)->toBe($reason)
        ->and($unalignable->sections[0]->reason)->toBe($reason)
        ->and(PlanConformanceData::from($unalignable->toArray())->toArray())->toBe($unalignable->toArray());
})->with(['unalignable', 'compressed', 'conformance ungated']);

it('refuses a plan conformance score paired wrongly with its reason', function (array $overrides) {
    expect(fn () => PlanConformanceData::from(planConformancePayload($overrides)))
        ->toThrow(InvalidArgumentException::class, 'Plan conformance');
})->with([
    'null overall without a reason' => [['overall' => null, 'reason' => null]],
    'null overall with an unnamed reason' => [['overall' => null, 'reason' => 'too quiet']],
    'scored overall with a reason' => [['overall' => 0.4, 'reason' => 'compressed']],
    'null section without a reason' => [['sections' => [[...planConformancePayload()['sections'][0], 'conformance' => null, 'reason' => null]]]],
    'scored section with a reason' => [['sections' => [[...planConformancePayload()['sections'][0], 'reason' => 'unalignable']]]],
    'zero, not null, with a reason' => [['overall' => 0.0, 'reason' => 'unalignable']],
]);

it('acknowledges a dispatch with queued only', function () {
    expect(TranscriptionDispatchData::from(['queued' => true])->toArray())->toBe(['queued' => true])
        ->and(TranscriptionDispatchData::from(['queued' => false])->queued)->toBeFalse();
});

it('speaks snake_case on the run answer in both directions', function () {
    $run = TranscriptionRunData::from(transcriptionRunPayload('pending', ['at' => '2026-10-28T16:00:00Z']));

    expect($run->state)->toBe(TranscriptionRunState::Pending)
        ->and($run->toArray())->toBe([
            'run_key' => str_repeat('k', 64),
            'state' => 'pending',
            'result' => null,
            'error' => null,
            'at' => '2026-10-28T16:00:00Z',
        ])
        ->and(TranscriptionRunData::from($run->toArray())->toArray())->toBe($run->toArray());
});

it('round-trips each state', function (string $state, array $extra) {
    $run = TranscriptionRunData::from(transcriptionRunPayload($state, $extra));

    expect($run->state->value)->toBe($state)
        ->and(TranscriptionRunData::from($run->toArray())->toArray())->toBe($run->toArray());
})->with([
    'none' => ['none', []],
    'pending' => ['pending', ['at' => '2026-10-28T16:00:00Z']],
    'completed' => ['completed', ['result' => transcriptionResultPayload(), 'at' => '2026-10-28T16:00:00Z']],
    'failed' => ['failed', ['error' => 'grid unusable: fewer than 2 beats', 'at' => '2026-10-28T16:00:00Z']],
]);

it('holds result exactly when completed across every state × {null, present} result', function (string $state, bool $withResult, bool $accepted) {
    $extra = $withResult ? ['result' => transcriptionResultPayload()] : [];
    if ($state === 'failed') {
        $extra['error'] = 'stale';
    }

    $build = fn () => TranscriptionRunData::from(transcriptionRunPayload($state, $extra));

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
    $extra = $state === 'completed' ? ['result' => transcriptionResultPayload()] : [];
    if ($error !== null) {
        $extra['error'] = $error;
    }

    $build = fn () => TranscriptionRunData::from(transcriptionRunPayload($state, $extra));

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
