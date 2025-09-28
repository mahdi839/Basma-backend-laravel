<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
   public function getFirst()
    {
        $socialLink = SocialLink::first();
        return response()->json($socialLink);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'facebook' => 'nullable|url',
            'youtube' => 'nullable|url',
            'instagram' => 'nullable|url',
            'tweeter' => 'nullable|url',
            'pinterest' => 'nullable|url',
        ]);

        $socialLink = SocialLink::create($validated);
        return response()->json($socialLink, 201);
    }

    public function update(Request $request, $id)
    {
        $socialLink = SocialLink::findOrFail($id);

        $validated = $request->validate([
            'facebook' => 'nullable|url',
            'youtube' => 'nullable|url',
            'instagram' => 'nullable|url',
            'tweeter' => 'nullable|url',
            'pinterest' => 'nullable|url',
        ]);

        $socialLink->update($validated);
        return response()->json($socialLink);
    }
}
