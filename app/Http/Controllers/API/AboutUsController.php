<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\AboutUs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class AboutUsController extends Controller
{
     // Show all (for frontend)
    public function index()
    {
        return response()->json(AboutUs::first());
    }

    // Store new entry
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('about', 'public');
        }

        $about = AboutUs::create([
            'title' => $request->title,
            'content' => $request->content,
            'image' => $imagePath,
        ]);

        return response()->json($about, 201);
    }

    // Update
    public function update(Request $request, $id)
    {
        $about = AboutUs::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048'
        ]);

        if ($request->hasFile('image')) {
            if ($about->image) {
                Storage::disk('public')->delete($about->image);
            }
            $about->image = $request->file('image')->store('about', 'public');
        }

        $about->update([
            'title' => $request->title,
            'content' => $request->content,
        ]);

        return response()->json($about);
    }

    // Delete
    public function destroy($id)
    {
        $about = AboutUs::findOrFail($id);
        if ($about->image) {
            Storage::disk('public')->delete($about->image);
        }
        $about->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
