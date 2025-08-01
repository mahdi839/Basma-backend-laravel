<?php

namespace App\Http\Controllers;

use App\Models\FooterSetting;
use Illuminate\Http\Request;

class FooterSettingController extends Controller
{
     public function index()
    {
        return FooterSetting::all();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'logo_path' => 'nullable|string',
            'company_description' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string',
        ]);

        $footer = FooterSetting::create($validated);
        return response()->json($footer, 201);
    }

    public function show($id)
    {
        $footer = FooterSetting::findOrFail($id);
        return response()->json($footer);
    }

    public function update(Request $request, $id)
    {
        $footer = FooterSetting::findOrFail($id);

        $validated = $request->validate([
            'logo_path' => 'nullable|string',
            'company_description' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string',
        ]);

        $footer->update($validated);
        return response()->json($footer);
    }

    public function destroy($id)
    {
        $footer = FooterSetting::findOrFail($id);
        $footer->delete();
        return response()->json(null, 204);
    }
}
