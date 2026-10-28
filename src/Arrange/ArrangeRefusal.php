<?php

namespace Splicewire\Composition\Music\Arrange;

use Schemastud\DataSchemas\Attributes\Description;
use Schemastud\DataSchemas\Attributes\Title;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The Arranger's 422 answer (music-capability-lift ticket 06 Q2, I02): it will not arrange this intent, by name. A
 * refusal is a mandatory failure the platform never retries; the run fails `arranger_refused`, carrying `reason`
 * as its `refusal_reason` and `members` as its members.
 *
 * {@see self::UNSUPPORTED_INTENT_MEMBERS} is the refusal an Arranger that cannot yet honour music/intent v2 gives
 * for an intent carrying `pickup` or `changes` (06 I02): it refuses by name and never drops them.
 */
#[TypeScript]
#[Title('Arrange refusal')]
class ArrangeRefusal extends Data
{
    public const UNSUPPORTED_INTENT_MEMBERS = 'unsupported_intent_members';

    /**
     * @param  list<string>  $members
     */
    public function __construct(
        #[Title('Reason')]
        #[Description('The machine name of the refusal, e.g. unsupported_intent_members.')]
        public string $reason,
        #[Title('Members')]
        #[Description('The intent members the refusal is about, e.g. ["pickup", "changes"]; may be empty.')]
        public array $members,
        #[Title('Detail')]
        #[Description('What was refused, for a person.')]
        public string $detail,
    ) {}
}
