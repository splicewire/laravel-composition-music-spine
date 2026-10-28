<?php

namespace Splicewire\Composition\Music\Support;

use BackedEnum;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Splicewire\Composition\Music\MusicIntent;
use Splicewire\Composition\Music\MusicSection;

/**
 * The one canonical-JSON rule every content digest in this package uses (the notation request, the intent, the
 * augmentation brief): keys sorted at every associative level, lists kept in order, enums as their values,
 * `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`, floats at round-trip precision, then sha256.
 *
 * A digest is taken over the hydrated Data's **property names** ({@see self::propertiesOf()}), so a host's
 * output name mapper cannot move it, and an absent member with a default digests as that default.
 *
 * @internal shared by the spine's digesters; not a wire shape.
 */
class CanonicalJson
{
    /**
     * The Data's members by property name, unwrapped, with nested Data transformed the same way.
     *
     * @return array<string, mixed>
     */
    public static function propertiesOf(Data $data): array
    {
        return $data->transform(
            TransformationContextFactory::create()->withoutPropertyNameMapping()->withoutWrapping(),
        );
    }

    /**
     * Drop an intent's `pickup` when it is 0 and a section's `changes` when empty: each means exactly what its
     * absence means ({@see MusicIntent::pickupBeats()}, {@see MusicSection::changeList()}), and the v1 → v2
     * schema ladder writes `pickup: 0`, so a migrated v1 intent digests as the same intent hydrated directly.
     *
     * @param  array<string, mixed>  $intent  an intent's {@see self::propertiesOf()} payload
     * @return array<string, mixed>
     */
    public static function withoutEmptyIntentV2Members(array $intent): array
    {
        if (array_key_exists('pickup', $intent) && (float) $intent['pickup'] === 0.0) {
            unset($intent['pickup']);
        }

        foreach ($intent['sections'] ?? [] as $i => $section) {
            if (is_array($section) && array_key_exists('changes', $section) && $section['changes'] === []) {
                unset($intent['sections'][$i]['changes']);
            }
        }

        return $intent;
    }

    /** sha256 of the canonical JSON of the payload. */
    public static function sha256(mixed $payload): string
    {
        $precision = ini_get('serialize_precision');
        ini_set('serialize_precision', '-1');

        try {
            $json = json_encode(self::canonical($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } finally {
            ini_set('serialize_precision', $precision === false ? '-1' : $precision);
        }

        return hash('sha256', $json);
    }

    /** Sort every associative level by key; lists keep their order (order is meaning there). */
    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::canonical(...), $value);

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
