<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeaturedImageResource;
use App\Models\FeaturedImage;
use Illuminate\Http\Request;

class FeaturedImageController extends Controller
{
    public function index()
    {
        $featuredImages = FeaturedImage::getActiveImages();

        return FeaturedImageResource::collection($featuredImages);
    }

    public function show($id)
    {
        $featuredImage = FeaturedImage::findOrFail($id);

        return new FeaturedImageResource($featuredImage);
    }

    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image_path' => 'required|image',
            'description' => 'nullable|string',
            'position' => 'required|integer|between:1,4|unique:featured_images,position',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image_path')) {
            $data['image_path'] = $request->file('image_path')->store('featured', 'public');
        }

        $featuredImage = FeaturedImage::create($data);

        return new FeaturedImageResource($featuredImage);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $featuredImage = FeaturedImage::findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'image_path' => 'nullable|image',
            'description' => 'nullable|string',
            'position' => 'sometimes|required|integer|between:1,4|unique:featured_images,position,'.$id,
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('image_path')) {
            if ($featuredImage->image_path) {
                \Storage::disk('public')->delete($featuredImage->image_path);
            }
            $data['image_path'] = $request->file('image_path')->store('featured', 'public');
        }

        $featuredImage->update($data);

        return new FeaturedImageResource($featuredImage);
    }

    public function destroy($id)
    {
        if (! request()->user() || ! request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $featuredImage = FeaturedImage::findOrFail($id);

        if ($featuredImage->image_path) {
            \Storage::disk('public')->delete($featuredImage->image_path);
        }

        $featuredImage->delete();

        return response()->json(null, 204);
    }
}
