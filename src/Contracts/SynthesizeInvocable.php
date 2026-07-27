<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Contracts;

use Splicewire\CompositionMusicSpineData\Synthesis\SynthesisRequest;
use Splicewire\CompositionMusicSpineData\Synthesis\SynthesisResult;

/**
 * The stage-2 **Synthesis** port (ADR-0128 §1, §2): `MidiFile → audio`. A **distinct** seam from the
 * stage-1 {@see RenderInvocable} (`music.render.midi`, intent→artifact) — the artifact→audio transform
 * must not be overloaded onto the intent→artifact contract (PRD user story 20). Where arrangement is the
 * moat and stays audiostud-local, synthesis is a secretless generic wheel (a soundfont pointed at a
 * subprocess) the platform hosts.
 *
 * Lives in the shared, satellite-importable spine-data surface (next to {@see RenderInvocable};
 * dependency-light — no popcorn, no kernel) so any satellite can *reference* the port to invoke platform
 * audio, while the one FluidSynth *binding* lives in the private `laravel-composition-engine-music`
 * engine (satellite-unreachable — Composer-enforced), reached over the async `music.render` seam.
 *
 * It is a {@see MeteredInvocable}: the binding declares render-seconds as its meter and reads the output
 * duration from the completed result. A *bare* port carries no metering behavior itself — metering
 * attaches to the platform binding (ADR-0128 §4).
 */
interface SynthesizeInvocable extends MeteredInvocable
{
    /**
     * Stable registry name of this synthesis target (e.g. `music.synthesize`), so the invocable registry
     * can resolve the platform binding by name.
     */
    public function name(): string;

    /**
     * Synthesize the referenced `MidiFile` artifact into audio, returning the audio artifact by
     * reference plus the output render-seconds (the metered quantity).
     */
    public function synthesize(SynthesisRequest $request): SynthesisResult;
}
