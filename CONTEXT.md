# Composition — Music Spine Data

The shared, satellite-importable surface for the music-intent format: the `MusicIntent` payload and the
render/synthesis **ports** over it. Dependency-light — ports + format only; implementations (arrangement,
FluidSynth synthesis) live in their owning bindings, not here. See ADR-0125, ADR-0128.

## Language

**Render**:
The whole `MusicIntent → audio` journey. Not one operation — a **chain** of two named stages
(Arrangement then Synthesis). Never use "render" to mean just one of them; name the stage.

**Arrangement**:
Stage 1 — `MusicIntent → MidiFile`. The voice-leading, groove, and per-section role-gating that turn
intent into MIDI. This is the musical IP (the moat); it runs on the satellite (audiostud's `Arranger`).
_Avoid_: render (too broad), transpose (that's the prose→intent LLM step, a different seam).

**Synthesis**:
Stage 2 — `MidiFile → audio`. A soundfont driven through a subprocess (FluidSynth + ffmpeg master).
A secretless generic wheel — the produced file carries no arrangement IP. Platform-hosted and metered.
_Avoid_: render, mixdown, export.

**Render stage**:
One link in the render chain — a named, registry-resolved invocable with a typed artifact-in/artifact-out
contract, independently bindable (local or remote) and independently metered. Stage 1 is the
`RenderInvocable` port (`music.render.midi`); stage 2 is the `SynthesizeInvocable` port.

**Render-seconds**:
The metering unit for synthesis — seconds of output audio produced. Reproducible and hardware-independent.
Distinct from wall/CPU-seconds, which is internal cost telemetry, never the tenant-facing meter.
_Avoid_: render minutes, compute seconds (ambiguous with wall time).

**Chord change**:
A chord and the beat in its section where it starts to sound; a bar's chord is the change sounding at its downbeat.

**Syllable onset**:
The beat in its section where a lyric syllable is sung. Lyrics belong to what is printed, never to the intent.
_Avoid_: lyric timing

**Lyric block**:
The song's lyrics printed as verse text after the score's music; distinct from the syllables under the notes and from the chart.
_Avoid_: lyric sheet, lyric page

**Stem set**:
A named way of splitting a mix into stems — vocals and instrumental, or six parts — chosen by the caller.
_Avoid_: isolate, isolation

**Separation run**:
One separation of one recording into one stem set, the unit that is charged and reused; a six-part split builds on a two-part run.
