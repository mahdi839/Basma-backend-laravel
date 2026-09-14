<?php

namespace App\Support;

/**
 * Turns messy product colour labels ("0666 Black 8cm", "black", "Off-White")
 * into one shop-style name plus a swatch hex, so a category filter can show
 * "Black" once instead of hundreds of shoe photos.
 */
class ColorName
{
    /** Longer keys first so "off white" wins over "white". */
    private const CANON = [
        'off-white' => ['Off White', '#f4efe6'],
        'off white' => ['Off White', '#f4efe6'],
        'rose red' => ['Rose Red', '#c43b4b'],
        'dark grey' => ['Grey', '#8a8f98'],
        'dark gray' => ['Grey', '#8a8f98'],
        'light grey' => ['Grey', '#c5c8ce'],
        'light gray' => ['Grey', '#c5c8ce'],
        'apricot' => ['Apricot', '#fbcea6'],
        'beige' => ['Beige', '#d8c3a5'],
        'khaki' => ['Khaki', '#c3b091'],
        'coffee' => ['Coffee', '#6f4e37'],
        'maroon' => ['Maroon', '#7b1e3a'],
        'navy' => ['Navy', '#1b365d'],
        'silver' => ['Silver', '#c0c4cc'],
        'gold' => ['Gold', '#c9a227'],
        'brown' => ['Brown', '#8b5a2b'],
        'green' => ['Green', '#2f6b4f'],
        'black' => ['Black', '#1a1a1a'],
        'white' => ['White', '#f5f5f5'],
        'grey' => ['Grey', '#8a8f98'],
        'gray' => ['Grey', '#8a8f98'],
        'pink' => ['Pink', '#e89bb4'],
        'blue' => ['Blue', '#3b6fb6'],
        'red' => ['Red', '#c62828'],
        'yellow' => ['Yellow', '#e2b203'],
        'orange' => ['Orange', '#e67a2e'],
        'purple' => ['Purple', '#6d4aa6'],
        'cream' => ['Cream', '#f3ead2'],
        'nude' => ['Nude', '#e0c3b0'],
        'camel' => ['Camel', '#c19a6b'],
        'wine' => ['Wine', '#722f37'],
        'teal' => ['Teal', '#2a9d8f'],
        'mint' => ['Mint', '#98d7c7'],
        'lilac' => ['Lilac', '#c8a2c8'],
        'ivory' => ['Ivory', '#fffff0'],
        'tan' => ['Tan', '#d2b48c'],
        'olive' => ['Olive', '#6b8e23'],
        'coral' => ['Coral', '#e07070'],
        'burgundy' => ['Burgundy', '#6d1c32'],
        'mustard' => ['Mustard', '#d4a017'],
        'lavender' => ['Lavender', '#b57edc'],
        'turquoise' => ['Turquoise', '#40e0d0'],
        'magenta' => ['Magenta', '#c2185b'],
        'peach' => ['Peach', '#f5c6a0'],
        'chocolate' => ['Chocolate', '#5d3a1a'],
        'charcoal' => ['Charcoal', '#36454f'],
        'sky' => ['Blue', '#7eb6d9'],
        'army' => ['Olive', '#4b5320'],
    ];

    public static function canonical(?string $name): string
    {
        $raw = trim((string) $name);
        if ($raw === '') {
            return '';
        }

        $lower = mb_strtolower($raw);

        if (isset(self::CANON[$lower])) {
            return self::CANON[$lower][0];
        }

        foreach (self::CANON as $needle => [$label]) {
            if (preg_match('/(^|[\s\-_\/])'.preg_quote($needle, '/').'($|[\s\-_\/0-9])/i', $lower)) {
                return $label;
            }
        }

        return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
    }

    public static function hex(?string $name, ?string $storedCode = null): string
    {
        $code = trim((string) $storedCode);
        if ($code !== '' && ! in_array(strtolower($code), ['#000', '#000000', 'black'], true)) {
            return $code;
        }

        $canonical = self::canonical($name);
        $lookup = mb_strtolower($canonical);

        return self::CANON[$lookup][1] ?? '#d0d5dd';
    }

    /** Original labels in a list that belong to one canonical colour. */
    public static function matchingOriginals(iterable $names, string $canonical): array
    {
        $want = mb_strtolower($canonical);
        $matched = [];

        foreach ($names as $name) {
            if (mb_strtolower(self::canonical((string) $name)) === $want) {
                $matched[] = $name;
            }
        }

        return array_values(array_unique($matched));
    }
}
