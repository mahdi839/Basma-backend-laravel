<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bannersData = Banner::with(['banner_images','category','slot'])->paginate(20);
        return response()->json($bannersData);
    }

    public function frontendIndex()
    {
        $bannersData = Cache::remember('frontend_banners',60*60, function(){
            return Banner::with(['banner_images:id,banner_id,path','category:id,name','slot:id,slot_name'])
        ->select('id','link','type','category_id','products_slots_id')
        ->get();
        });
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
            'category_id' => 'required_if:type,category|exists:categories,id',
            'products_slots_id' => 'required_if:type,slot|exists:products_slots,id',
            'images' => 'required|array',
            'images.*' => 'required|image|mimes:jpg,jpeg,png,gif|max:5120'
        ],
        [
            'category_id.required_if' => 'Category Field Is Required',
             'products_slots_id.required_if' => 'Slot Field Is Required'
        ]
    
    );

   


        $banner = DB::transaction(function () use ($request) {
            $banner =  Banner::create([
                'link' => $request->link,
                'type' => $request->type,
                'category_id' => $request->category_id,
                'products_slots_id' => $request->products_slots_id,
            ]);

          foreach ($request->file('images') as $image) {
                $path_name = $image->store('banner/images', 'public');
                $banner->banner_images()->create([
                    'path' => $path_name
                ]);
            }

           return $banner->load('banner_images'); 
        });

        Cache::forget('frontend_banners');

        return response()->json($banner);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $banner = Banner::with('banner_images','slot')->findOrFail($id);
        return response()->json($banner);
    }
    

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,  $id)
    {
      
        $request->validate([
        'link' => 'nullable',
        'type' => 'required|in:hero,slot,category',
        'category_id' => 'nullable|required_if:type,category|exists:categories,id',
        'products_slots_id' => 'nullable|required_if:type,slot|exists:products_slots,id',
        'images' => 'sometimes|array',
        'images.*' => 'image|mimes:jpg,jpeg,png,gif',
        'delete_images' => 'sometimes|array',
        'delete_images.*' => 'exists:banner_images,id'
        ]);

        $singleBanner = Banner::findOrFail($id);

        $banner =  DB::transaction(function () use ($request, $singleBanner) {
            $singleBanner->update([
                'link' => $request->link,
                'type' => $request->type,
                'category_id' => $request->category_id,
                'products_slots_id' => $request->products_slots_id,
            ]);

            if ($request->has('delete_images')) {
                $imagesToDelete =  $singleBanner->banner_images()->whereIn('id', $request->delete_images)->get();
                foreach ($imagesToDelete as $image) {
                    Storage::disk('public')->delete($image->path);
                    $image->delete();
                }
            }

            if ($request->has('images')) {
                foreach ($request->images as $img) {
                    $path_name = $img->store('banner/images', 'public');
                    $singleBanner->banner_images()->create([
                        'path' => $path_name
                    ]);
                }
            }
            Cache::forget('frontend_banners');
            return $singleBanner->load('banner_images');
        });

        return response()->json($banner);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Banner $banner)
    {
        DB::transaction(function() use ($banner){
           
            foreach($banner->banner_images as $image){
                Storage::disk('public')->delete($image->path);
            }
            $banner->banner_images()->delete();
            $banner->delete();
        });

        Cache::forget('frontend_banners');
       
        return response()->json([
            'message' => 'Successfully Deleted'
        ]);
    }
}
