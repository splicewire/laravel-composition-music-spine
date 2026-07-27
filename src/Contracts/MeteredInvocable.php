<?php

declare(strict_types=1);

namespace Splicewire\CompositionMusicSpineData\Contracts;

/**
 * The per-invocable metering declaration (ADR-0128 §5). A capability the platform runs on a tenant's
 * behalf declares *what* it meters ({@see self::meterUnit()}) and *how much* a given completed result
 * cost in that unit ({@see self::meterQuantity()}). One seam serves every metered platform capability —
 * synthesis meters output render-seconds today; a future platform conformance run would declare
 * per-invocation — with no special-casing in the reconcile path.
 *
 * Deliberately NOT a synchronous `invoke()` decorator: metering fires at render-completion / reconcile
 * time (webhook + poll), when the completed artifact's quantity is actually known — the reconcile path
 * reads these two methods off the resolved binding. See PRD `synthesis-metering` Testing Decisions.
 *
 * The free/local rule is a *binding* property, not a contract one (apocryphon
 * `compute-metering-is-a-platform-binding-property`): a satellite-local (`Binding::Local`) invocable
 * running on the satellite's own CPU is never metered even if it implements this contract — the
 * reconcile path only meters platform (`Binding::Webhook`) bindings.
 */
interface MeteredInvocable
{
    /**
     * The ledger meter this capability's usage is recorded under — the value written to
     * `CostEvent.meter` (e.g. `music.synthesis`). Its human-facing native unit (render-seconds) is
     * declared alongside the meter in `config/app/meters.php`; this returns the meter *key*, not the
     * unit label, because the reconcile path writes it straight onto the ledger row.
     */
    public function meterUnit(): string;

    /**
     * The metered quantity read from a completed result, in the meter's native unit. Synthesis reads
     * the output audio duration (render-seconds) from the completed synthesis payload — never wall/CPU
     * seconds (that is internal telemetry, never the tenant-facing quantity), and never an estimate at
     * submission time.
     *
     * @param  array<string, mixed>  $result  the completed invocable result (e.g. the terminal render payload)
     */
    public function meterQuantity(array $result): float;
}
