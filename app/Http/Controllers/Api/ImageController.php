<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\ImageResource;
use App\Models\Image;
use Illuminate\Http\Request;

class ImageController extends Controller
{
    public function show($id)
    {
        $image = Image::findOrFail($id);

        return new ImageResource($image);
    }

    public function index(PaginatedRequest $request)
    {
        $images = Image::orderBy(
            $request->getSortColumn(),
            $request->getSortOrder()
        )->paginate($request->getPerPage());

        return ImageResource::collection($images);
    }

    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'album_id' => 'required|exists:albums,id',
            'title' => 'required|string|max:255',
            'image_path' => 'required|image',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image_path')) {
            $data['image_path'] = $request->file('image_path')->store('images', 'public');
        }

        $image = Image::create($data);

        // Load the relationship for the response
        $image->load('album');

        return new ImageResource($image);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $image = Image::findOrFail($id);

        $data = $request->validate([
            'album_id' => 'sometimes|required|exists:albums,id',
            'title' => 'sometimes|required|string|max:255',
            'image_path' => 'nullable|image',
            'description' => 'nullable|string',
        ]);

        if ($request->hasFile('image_path')) {
            // Delete old image if exists
            if ($image->image_path) {
                \Storage::disk('public')->delete($image->image_path);
            }
            $data['image_path'] = $request->file('image_path')->store('images', 'public');
        }

        $image->update($data);

        // Refresh the relationship for the response
        $image->load('album');

        return new ImageResource($image);
    }

    public function destroy($id)
    {
        if (! request()->user() || ! request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $image = Image::findOrFail($id);
        $image->delete();

        return response()->json(null, 204);
    }
}
