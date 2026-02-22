<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\BannerImage;
use App\Traits\ClearsHomeCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    use ClearsHomeCache;

    /* =========================================================
        GET HERO BANNER
    ========================================================= */

    public function index()
    {
        $banner = Banner::with('banner_images')
            ->where('type', 'hero')
            ->first();

        if (!$banner) {
            return response()->json([
                'data' => [],
                'message' => 'No banner created yet'
            ]);
        }

        return response()->json([
            'data' => [$banner],
            'banner_images' => $banner->banner_images
        ]);
    }

    /* =========================================================
        STORE (CREATE HERO)
    ========================================================= */

    public function store(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.image' => 'required|image|max:2048',
            'banners.*.link' => 'nullable|string'
        ]);

        $banner = DB::transaction(function () use ($request) {

            $banner = Banner::firstOrCreate([
                'type' => 'hero'
            ]);

            $uploadedBanners = $request->file('banners');

            foreach ($uploadedBanners as $index => $bannerFile) {

                $imageFile = $bannerFile['image'];
                $link = $request->input("banners.$index.link");

                $path = $imageFile->store('banner/images', 'public');

                $banner->banner_images()->create([
                    'path' => $path,
                    'link' => $link
                ]);
            }

            return $banner->load('banner_images');
        });

        Cache::forget('frontend_banners');
        $this->clearHomeCategoryCach();

        return response()->json($banner, 201);
    }

    /* =========================================================
        UPDATE (EDIT HERO)
    ========================================================= */

    public function update(Request $request, $id)
    {
        $request->validate([
            'banners' => 'nullable|array',
            'banners.*.image' => 'nullable|image|max:2048',
            'banners.*.link' => 'nullable|string',
            'image_links' => 'nullable|array',
            'image_links.*.id' => 'required|exists:banner_images,id',
            'image_links.*.link' => 'nullable|string'
        ]);

        $banner = Banner::findOrFail($id);

        DB::transaction(function () use ($request, $banner) {

            /* ---------- UPDATE OLD IMAGE LINKS ---------- */

            if ($request->has('image_links')) {
                foreach ($request->image_links as $img) {
                    BannerImage::where('id', $img['id'])
                        ->where('banner_id', $banner->id)
                        ->update([
                            'link' => $img['link']
                        ]);
                }
            }

            /* ---------- ADD NEW IMAGES ---------- */

            $uploadedBanners = $request->file('banners');

            if (!empty($uploadedBanners)) {
                foreach ($uploadedBanners as $index => $bannerFile) {

                    if (!isset($bannerFile['image'])) continue;

                    $imageFile = $bannerFile['image'];
                    $link = $request->input("banners.$index.link");

                    $path = $imageFile->store('banner/images', 'public');

                    $banner->banner_images()->create([
                        'path' => $path,
                        'link' => $link
                    ]);
                }
            }
        });

        Cache::forget('frontend_banners');
        $this->clearHomeCategoryCach();

        return response()->json($banner->load('banner_images'));
    }

    /* =========================================================
        DELETE SINGLE IMAGE
    ========================================================= */

    public function destroy($id)
    {
        $image = BannerImage::findOrFail($id);

        if ($image->banner->banner_images()->count() <= 1) {
            return response()->json([
                'message' => 'At least one banner image is required'
            ], 422);
        }

        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        Cache::forget('frontend_banners');
        $this->clearHomeCategoryCach();

        return response()->json(['message' => 'Deleted']);
    }
}