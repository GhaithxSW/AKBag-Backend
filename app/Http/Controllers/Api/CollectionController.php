<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Http\Resources\CollectionResource;
use App\Http\Resources\AlbumResource;

class CollectionController extends Controller
{
    public function index()
    {
        return CollectionResource::collection(Collection::all());
    }

    public function show($id)
    {
        $collection = Collection::with('albums')->findOrFail($id);
        return new CollectionResource($collection);
    }

    public function store(Request $request)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $collection = Collection::create($data);
        return new CollectionResource($collection);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $collection = Collection::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);
        $collection->update($data);
        return new CollectionResource($collection);
    }

    public function destroy($id)
    {
        if (!request()->user() || !request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $collection = Collection::findOrFail($id);
        $collection->delete();
        return response()->json(null, 204);
    }

    public function albums($id)
    {
        $collection = Collection::with('albums')->findOrFail($id);
        return AlbumResource::collection($collection->albums);
    }

    public function albumInCollection($collectionId, $albumId)
    {
        $collection = Collection::findOrFail($collectionId);
        $album = $collection->albums()->with('images')->findOrFail($albumId);
        return new AlbumResource($album);
    }
}
