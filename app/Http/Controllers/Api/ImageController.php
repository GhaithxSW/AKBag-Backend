<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use Illuminate\Http\Request;
use App\Http\Resources\ImageResource;

class ImageController extends Controller
{
    public function show($id)
    {
        $image = Image::findOrFail($id);
        return new ImageResource($image);
    }

    public function index()
    {
        return ImageResource::collection(\App\Models\Image::all());
    }

    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'album_id' => 'required|exists:albums,id',
            'title' => 'required|string|max:255',
            'category' => 'nullable|string',
            'image_path' => 'required|image',
        ]);
        $data['image_path'] = $request->file('image_path')->store('images', 'public');
        $image = Image::create($data);
        return new ImageResource($image);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $image = Image::findOrFail($id);
        $data = $request->validate([
            'album_id' => 'sometimes|required|exists:albums,id',
            'title' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string',
            'image_path' => 'nullable|image',
        ]);
        if ($request->hasFile('image_path')) {
            $data['image_path'] = $request->file('image_path')->store('images', 'public');
        }
        $image->update($data);
        return new ImageResource($image);
    }

    public function destroy($id)
    {
        if (!request()->user() || !request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $image = Image::findOrFail($id);
        $image->delete();
        return response()->json(null, 204);
    }
}
