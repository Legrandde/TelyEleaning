<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Médiathèque du page builder (images uniquement).
 */
class MediaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['medias' => Media::latest()->paginate(48)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'alt' => ['nullable', 'string', 'max:255'],
        ]);

        $fichier = $request->file('fichier');
        $chemin = $fichier->store('medias', 'public');

        $media = Media::create([
            'chemin' => $chemin,
            'nom' => mb_substr($fichier->getClientOriginalName(), 0, 255),
            'mime' => $fichier->getMimeType() ?? 'image/*',
            'taille' => $fichier->getSize(),
            'alt' => $request->input('alt'),
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Image importée.', 'media' => $media], 201);
    }

    public function destroy(Media $media): JsonResponse
    {
        Storage::disk('public')->delete($media->chemin);
        $media->delete();
        JournalService::log('media.supprime', "Média « {$media->nom} » supprimé", $media);

        return response()->json(['message' => 'Image supprimée.']);
    }
}
