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
                'inventory_enforcement_enabled' => (bool) ($settings?->inventory_enforcement_enabled ?? false),
                'low_stock_threshold' => (int) ($settings?->low_stock_threshold ?? SiteSetting::DEFAULT_LOW_STOCK_THRESHOLD),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'inventory_enforcement_enabled' => ['nullable', 'boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        $settings = SiteSetting::current() ?? new SiteSetting();
        $settings->primary_color = $this->normalizeHex($validated['primary_color']);

        // Global stock kill switch. Left alone unless the request says otherwise,
        // so saving a colour cannot silently start blocking sales.
        if ($request->has('inventory_enforcement_enabled')) {
            $settings->inventory_enforcement_enabled = $request->boolean('inventory_enforcement_enabled');
        }

        if ($request->filled('low_stock_threshold')) {
            $settings->low_stock_threshold = (int) $validated['low_stock_threshold'];
        }

        $settings->save();

        return response()->json([
            'message' => 'Settings saved successfully.',
            'data' => [
                'primary_color' => $settings->primary_color,
                'inventory_enforcement_enabled' => (bool) $settings->inventory_enforcement_enabled,
                'low_stock_threshold' => (int) $settings->low_stock_threshold,
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
