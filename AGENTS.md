> You are in **splicewire/laravel-composition-music-spine** — the shared, satellite-importable surface of the MIDI/music generation seam: the platform-coined MusicIntent wire format and the RenderInvocable contract.

The `MusicIntent` DTO is a song-agnostic generation-intent format (key, tempo, meter, ordered
sections of chords + energy + roles + optional beat-relative melody), and `RenderInvocable` is an
invocable contract over that format (render is an invocable over the format, not the kernel
`RenderContract`). The heavy transpose engine and deterministic Arranger stay private — this
package is only the format satellites and the client share without them (ADR-0125).

## Vendored family-package conventions

Any repo that vendors another family repo's code (composer `vendor/<vendor>/<pkg>/`, npm
`node_modules/<vendor>/<pkg>/`) checks that vendored repo's own `AGENTS.md` for conventions it
ships with itself before editing through into it.
