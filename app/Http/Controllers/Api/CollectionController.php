<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\CollectionResource;
use App\Models\Collection;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function index(PaginatedRequest $request)
    {
        $query = Collection::withCount('albums');

        // Only load albums if explicitly requested via ?include=albums
        if ($request->query('include') === 'albums') {
            $query->with('albums');
        }

        $collections = $query->orderBy(
            $request->getSortColumn(),
            $request->getSortOrder()
        )->paginate($request->getPerPage());

        return CollectionResource::collection($collections);
    }

    public function show(Request $request, $id)
    {
        $query = Collection::withCount('albums');

        // Only load albums if explicitly requested via ?include=albums
        if ($request->query('include') === 'albums') {
            $query->with('albums');
        }

        $collection = $query->findOrFail($id);

        return new CollectionResource($collection);
    }

    public function store(Request $request)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('collections/covers', 's3');
        }

        $collection = Collection::create($data);

        return new CollectionResource($collection);
    }

    public function update(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $collection = Collection::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($collection->cover_image) {
                \Storage::disk('s3')->delete($collection->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('collections/covers', 's3');
        }

        $collection->update($data);

        return new CollectionResource($collection);
    }

    public function destroy($id)
    {
        if (! request()->user() || ! request()->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $collection = Collection::findOrFail($id);

        if (! $collection->canBeDeleted()) {
            return response()->json([
                'message' => 'Cannot delete collection',
                'reason' => $collection->getDeletionBlockReason(),
            ], 422);
        }

        $collection->delete();

        return response()->json(null, 204);
    }

    public function albums($id, PaginatedRequest $request)
    {
        $collection = Collection::findOrFail($id);
        $query = $collection->albums()
            ->withCount('images')
            ->with('collection');

        // Only load images if explicitly requested via ?include=images
        if ($request->query('include') === 'images') {
            $query->with('images');
        }

        // Order by sort_order first (ascending, lower numbers = higher priority)
        // Then by the requested sort column
        $albums = $query->orderBy('sort_order', 'asc')
            ->orderBy(
                $request->getSortColumn(),
                $request->getSortOrder()
            )
            ->paginate($request->getPerPage());

        return AlbumResource::collection($albums);
    }

    public function albumInCollection(Request $request, $collectionId, $albumId)
    {
        $collection = Collection::findOrFail($collectionId);
        $query = $collection->albums()->withCount('images');

        // Only load images if explicitly requested via ?include=images
        if ($request->query('include') === 'images') {
            $query->with('images');
        }

        $album = $query->findOrFail($albumId);

        return new AlbumResource($album);
    }
}
