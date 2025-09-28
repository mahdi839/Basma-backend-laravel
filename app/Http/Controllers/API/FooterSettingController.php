<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\FooterSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FooterSettingController extends Controller
{
    public function index()
    {
        $data = FooterSetting::first();

        if ($data) {
            return response()->json($data);
        } else {
            return response()->json([
                'message' => 'No Data Found',
            ], 200);
        }
    }

    public function show($id)
    {
        $footer = FooterSetting::find($id); // <- use find() instead of findOrFail()

        if ($footer) {
            return response()->json($footer);
        } else {
            return response()->json(null, 200); // or return an empty object {}
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'logo_path' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'company_description' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string',
        ]);

        // Handle logo file upload
        if ($request->hasFile('logo_path')) {
            $path = $request->file('logo_path')->store('logos', 'public');
            $validated['logo_path'] = '/storage/'.$path;
        }
        $footer = FooterSetting::create($validated);

        return response()->json($footer, 201);
    }

    public function update(Request $request, $id)
    {
        $footer = FooterSetting::findOrFail($id);

        $validated = $request->validate([
            'logo_path' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'company_description' => 'nullable|string',
            'company_address' => 'nullable|string',
            'company_email' => 'nullable|email',
            'company_phone' => 'nullable|string',
        ]);

        // Handle image replacement
        if ($request->hasFile('logo_path')) {
            if ($footer->logo_path) {
                $existingPath = str_replace('/storage/', '', $footer->logo_path);
                Storage::disk('public')->delete($existingPath);
            }
            $path = $request->file('logo_path')->store('logos', 'public');
            $validated['logo_path'] = '/storage/'.$path;
        }
        $footer->update($validated);

        return response()->json($footer);
    }
}
