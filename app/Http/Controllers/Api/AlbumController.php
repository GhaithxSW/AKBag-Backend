<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\AlbumResource;
use App\Models\Album;
use Illuminate\Http\Request;

class AlbumController extends Controller
{
    public function show(Request $request, $id)
    {
        $query = Album::withCount('images')->with('collection');

        // Only load images if explicitly requested via ?include=images
        if ($request->query('include') === 'images') {
            $query->with('images');
        }

        $album = $query->findOrFail($id);

        return new AlbumResource($album);
    }

    public function index(PaginatedRequest $request)
    {
        $query = Album::withCount('images')->with('collection');

        // Only load images if explicitly requested via ?include=images
        if ($request->query('include') === 'images') {
            $query->with('images');
        }

        $albums = $query->orderBy(
            $request->getSortColumn(),
            $request->getSortOrder()
        )->paginate($request->getPerPage());

        return AlbumResource::collection($albums);
    }

    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'collection_id' => 'required|exists:collections,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
        ]);
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('albums', 's3');
        }
        $album = Album::create($data);

        return new AlbumResource($album);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $album = Album::findOrFail($id);
        $data = $request->validate([
            'collection_id' => 'sometimes|required|exists:collections,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
        ]);
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('albums', 's3');
        }
        $album->update($data);

        return new AlbumResource($album);
    }

    public function destroy($id)
    {
        if (! request()->user() || ! request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $album = Album::findOrFail($id);
        $album->delete();

        return response()->json(null, 204);
    }

    public function images($albumId, PaginatedRequest $request)
    {
        $album = Album::findOrFail($albumId);
        $images = $album->images()
            ->orderBy(
                $request->getSortColumn(),
                $request->getSortOrder()
            )
            ->paginate($request->getPerPage());

        return \App\Http\Resources\ImageResource::collection($images);
    }
}
