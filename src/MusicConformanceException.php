<?php

declare(strict_types=1);

namespace Splicewire\Composition\Music;

use RuntimeException;
use Splicewire\Composition\Music\Conformance\ConformanceResult;

/**
 * Thrown when a drafted {@see MusicIntent} fails the conformance guardrail's *mandatory* checks
 * (ADR-0125 §9 / PRD §11.2, issue 07 follow-up). This is a pre-render gate firing — from the platform's
 * `TransposeService` on the transpose/persist path, or a satellite's local render entry: an intent with
 * a mandatory violation (a note out of MIDI range, an un-parseable meter, a bar/chord mismatch) never
 * reaches the Arranger and is never persisted.
 *
 * Advisories do NOT throw — they ride back on the {@see ConformanceResult}. The blocking verdict (the
 * failing {@see ConformanceResult}) is carried on {@see MusicConformanceException::$result} so a caller
 * can surface the exact violations.
 */
final class MusicConformanceException extends RuntimeException
{
    public function __construct(public readonly ConformanceResult $result)
    {
        $violations = implode(' ', $result->mandatory);

        parent::__construct(
            'The drafted MusicIntent failed the conformance guardrail: '.$violations
        );
    }
}
