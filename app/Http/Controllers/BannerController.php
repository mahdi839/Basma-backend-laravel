<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
           'link'=> 'nullable',
            'type'=> 'required',
            'category_id' => 'nullable|exists:categories,id',
            'images' => 'required|array',
            'images.*'=> 'required|image|mimes:jpg,jpeg,png,gif'
        ]);

    $banner = DB::transaction(function() use ($request){
        $banner =  Banner::create([
            'link'=> $request->link,
            'type'=> $request->type,
            'category_id' => $request->category_id,
        ]);

      foreach($request->images as $image){
        
       $path_name = $image->store('banner/images','public');
        $banner->images()->create([
            'path'=>$path_name
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Banner $banner)
    {
        //
    }
}
