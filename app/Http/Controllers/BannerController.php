<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bannersData = Banner::with('images')->paginate(20);
        return response()->json($bannersData);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'link' => 'nullable',
            'type' => 'required',
            'category_id' => 'nullable|exists:categories,id',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

        $banner = DB::transaction(function () use ($request) {
            $banner =  Banner::create([
                'link' => $request->link,
                'type' => $request->type,
                'category_id' => $request->category_id,
            ]);

            foreach ($request->images as $image) {

                $path_name = $image->store('banner/images', 'public');
                $banner->images()->create([
                    'path' => $path_name
                ]);
            }

            return $banner;
        });

        return response()->json($banner);
    }

    /**
     * Display the specified resource.
     */
    public function show(Banner $banner)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'link' => 'nullable',
            'type' => 'required|in:hero,slot,category',
            'category_id' => 'nullable|exists:categories,id',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,gif',
            'delete_images' => 'sometimes|array',
            'delete_images.*' => 'exists:banner_images,id'
        ]);

        $banner =  DB::transaction(function () use ($request, $banner) {
            $banner->update([
                'link' => $request->link,
                'type' => $request->type,
                'category_id' => $request->category_id,
            ]);

            if ($request->has('delete_images')) {
                $imagesToDelete =  $banner->images()->whereIn('id', $request->delete_images)->get();
                foreach ($imagesToDelete as $image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }

            if ($request->has('images')) {
                foreach ($request->images as $img) {
                    $path_name = $img->store('banner/images', 'public');
                    $banner->images()->create([
                        'path' => $path_name
                    ]);
                }
            }

            return $banner->load('images');
        });

        return response()->json($banner);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Banner $banner)
    {
        DB::transaction(function() use ($banner){
           
            foreach($banner->images as $image){
                Storage::disk('public')->delete($image->path);
            }
            $banner->images()->delete();
            $banner->delete();
        });
       
        return response()->json([
            'message' => 'Successfully Deleted'
        ]);
    }
}
