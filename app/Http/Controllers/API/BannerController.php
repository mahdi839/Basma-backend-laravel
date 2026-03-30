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

    private const CACHE_KEY = 'frontend_banners';
    private const CACHE_TTL = 36000; // 10 hours in seconds

    /* =========================================================
        GET HERO BANNER
    ========================================================= */

    public function index()
    {
        $banner = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Banner::with('banner_images:id,banner_id,path,link')
                ->where('type', 'hero')
                ->select('id', 'type')
                ->first();
        });

        if (!$banner) {
            return response()->json([
                'data' => [],
                'message' => 'No banner created yet'
            ], 404);
        }

        return response()->json([
            'data' => [$banner],
        ]);
    }

    /* =========================================================
        STORE (CREATE HERO)
    ========================================================= */

    public function store(Request $request)
    {
        $request->validate([
            'banners'         => 'required|array',
            'banners.*.image' => 'required|image|max:2048',
            'banners.*.link'  => 'nullable|string',
        ]);

        $banner = DB::transaction(function () use ($request) {

            $banner = Banner::firstOrCreate(['type' => 'hero']);

            // $request->file('banners') returns an array like:
            // [ 0 => ['image' => UploadedFile], 1 => ['image' => UploadedFile] ]
            foreach ($request->file('banners') as $index => $bannerFile) {
                if (empty($bannerFile['image'])) continue;

                $path = $bannerFile['image']->store('banner/images', 'public');

                $banner->banner_images()->create([
                    'path' => $path,
                    'link' => $request->input("banners.{$index}.link"),
                ]);
            }

            return $banner->load('banner_images');
        });

        Cache::forget(self::CACHE_KEY);
        $this->clearHomeCategoryCach();

        return response()->json($banner, 201);
    }

    /* =========================================================
        UPDATE (EDIT HERO)
    ========================================================= */

    public function update(Request $request, $id)
    {
        $request->validate([
            'banners'              => 'nullable|array',
            'banners.*.image'      => 'nullable|image|max:2048',
            'banners.*.link'       => 'nullable|string',
            'image_links'          => 'nullable|array',
            'image_links.*.id'     => 'required_with:image_links|exists:banner_images,id',
            'image_links.*.link'   => 'nullable|string',
        ]);

        $banner = Banner::findOrFail($id);

        DB::transaction(function () use ($request, $banner) {

            // Update existing image links
            if ($request->has('image_links')) {
                foreach ($request->input('image_links') as $img) {
                    BannerImage::where('id', $img['id'])
                        ->where('banner_id', $banner->id)
                        ->update(['link' => $img['link'] ?? null]);
                }
            }

            // Add new images
            // $request->file('banners') returns array like:
            // [ 0 => ['image' => UploadedFile], ... ]
            if ($request->hasFile('banners')) {
                foreach ($request->file('banners') as $index => $bannerFile) {
                    if (empty($bannerFile['image'])) continue;

                    $path = $bannerFile['image']->store('banner/images', 'public');

                    $banner->banner_images()->create([
                        'path' => $path,
                        'link' => $request->input("banners.{$index}.link"),
                    ]);
                }
            }
        });

        Cache::forget(self::CACHE_KEY);
        $this->clearHomeCategoryCach();

        return response()->json($banner->load('banner_images'));
    }

    /* =========================================================
        DELETE SINGLE IMAGE
    ========================================================= */

    public function destroy($id)
    {
        $image = BannerImage::findOrFail($id);

        if (Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        Cache::forget(self::CACHE_KEY);
        $this->clearHomeCategoryCach();

        return response()->json(['message' => 'Deleted']);
    }
}