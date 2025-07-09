<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Album;
use Illuminate\Http\Request;
use App\Http\Resources\AlbumResource;

class AlbumController extends Controller
{
    public function show($id)
    {
        $album = Album::with('images')->findOrFail($id);
        return new AlbumResource($album);
    }

    public function index()
    {
        return AlbumResource::collection(\App\Models\Album::with('images')->get());
    }

    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'collection_id' => 'required|exists:collections,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
            'slug' => 'required|string|unique:albums,slug',
        ]);
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('albums', 'public');
        }
        $album = Album::create($data);
        return new AlbumResource($album);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $album = Album::findOrFail($id);
        $data = $request->validate([
            'collection_id' => 'sometimes|required|exists:collections,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
            'slug' => 'sometimes|required|string|unique:albums,slug,' . $album->id,
        ]);
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('albums', 'public');
        }
        $album->update($data);
        return new AlbumResource($album);
    }

    public function destroy($id)
    {
        if (!request()->user() || !request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $album = Album::findOrFail($id);
        $album->delete();
        return response()->json(null, 204);
    }
}
