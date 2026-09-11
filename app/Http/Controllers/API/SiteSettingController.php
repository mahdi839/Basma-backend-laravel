<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        $settings = SiteSetting::current();

        return response()->json([
            'data' => [
                'primary_color' => $settings?->primary_color ?: SiteSetting::DEFAULT_PRIMARY_COLOR,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ]);

        $settings = SiteSetting::current() ?? new SiteSetting();
        $settings->primary_color = $this->normalizeHex($validated['primary_color']);
        $settings->save();

        return response()->json([
            'message' => 'Website color saved successfully.',
            'data' => [
                'primary_color' => $settings->primary_color,
            ],
        ]);
    }

    private function normalizeHex(string $color): string
    {
        $color = strtolower(trim($color));

        if (preg_match('/^#([a-f0-9]{3})$/', $color, $matches)) {
            $hex = $matches[1];

            return '#'.$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return $color;
    }
}
