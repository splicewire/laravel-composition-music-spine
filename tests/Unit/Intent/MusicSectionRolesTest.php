<?php

use Illuminate\Validation\ValidationException;
use Splicewire\Composition\Music\Arrange\ArrangeRequest;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;

/**
 * `MusicSection::roles` legitimately round-trips empty — an empty (or absent) `roles` is "Arranger default,
 * all roles" (its `#[Description]` and the ADR-0125 model/Arranger split). The field is non-`Optional` on
 * purpose: it is always serialised as `[]` so the probe/digest wire shape stays stable. The bug: a
 * non-`Optional`, non-nullable array infers a Laravel `required` rule, and `required` REJECTS an empty
 * array, so every validated body carrying `roles: []` 422s ("The …roles field is required.").
 *
 * The harness mirrors the flagship's CamelCaseMapper input mapper (tests/TestCase.php), so wire keys are
 * snake_case, exactly as the platform validates them.
 */
function sectionWithRoles(array $roles): array
{
    return ['tag' => 'VERSE', 'chords' => ['C', 'Am', 'F', 'G'], 'roles' => $roles];
}

function intentWithSectionRoles(array $roles): array
{
    return [
        'key' => ['tonic' => 'C', 'mode' => 'major'],
        'sections' => [sectionWithRoles($roles)],
        'tempo' => 96,
        'meter' => '4/4',
    ];
}

it('validates a MusicSection whose roles are empty (the Arranger default), the bug', function () {
    $section = MusicSection::validateAndCreate(sectionWithRoles([]));

    expect($section->roles)->toBe([]);
});

it('validates a MusicIntent whose section carries empty roles (the real ingestion path)', function () {
    $intent = MusicIntent::validateAndCreate(intentWithSectionRoles([]));

    expect($intent->sections[0]->roles)->toBe([]);
});

it('validates an ArrangeRequest whose inline intent section carries empty roles', function () {
    $intent = MusicIntent::from(intentWithSectionRoles([]));

    $request = ArrangeRequest::validateAndCreate([
        'intent' => $intent->toArray(),
        'intent_digest' => $intent->digest(),
        'run_key' => 'run',
        'iteration' => 0,
    ]);

    expect($request->intent->sections[0]->roles)->toBe([]);
});

it('still validates a populated roles array', function () {
    $section = MusicSection::validateAndCreate(sectionWithRoles(['keys', 'drums']));

    expect($section->roles)->toBe(['keys', 'drums'])
        ->and(MusicIntent::validateAndCreate(intentWithSectionRoles(['bass']))->sections[0]->roles)
        ->toBe(['bass']);
});

it('still rejects a non-array roles, so the rule is relaxed, not removed', function () {
    expect(fn () => MusicSection::validateAndCreate(['tag' => 'VERSE', 'chords' => ['C'], 'roles' => 'keys']))
        ->toThrow(ValidationException::class);
});

it('defaults an absent roles key to [] and validates — the = [] default fills it before validation', function () {
    // #[Present] would require the key, but the `= []` default fills an omitted key with [] BEFORE
    // validation runs, so an absent `roles` validates and round-trips as []. (A reviewer expected an
    // absent key to reject; measurement shows the default makes absent legitimate — the real contract.)
    $section = MusicSection::validateAndCreate(['tag' => 'VERSE', 'chords' => ['C', 'Am', 'F', 'G']]);

    expect($section->roles)->toBe([]);
});
