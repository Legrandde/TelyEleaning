<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\JournalActivite;
use App\Models\Page;
use App\Models\SujetExamen;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outils transverses du back-office : journal d'audit et recherche globale.
 */
class AdminBackofficeController extends Controller
{
    public function journal(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'user_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'du' => ['nullable', 'date'],
            'au' => ['nullable', 'date'],
        ]);

        $journal = JournalActivite::query()
            ->with('user:id,nom,prenom,email,role,deleted_at')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', str_replace('%', '', $a).'%'))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->when($request->query('du'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('au'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')
            ->paginate(30);

        return response()->json([
            'journal' => $journal,
            'actions' => JournalActivite::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function recherche(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $q = trim((string) $request->query('q'));
        $like = "%{$q}%";

        return response()->json([
            'utilisateurs' => User::query()
                ->where(fn ($w) => $w->where('nom', 'like', $like)->orWhere('prenom', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(5)->get(['id', 'nom', 'prenom', 'email', 'role', 'avatar']),
            'exercices' => Exercice::where('titre', 'like', $like)->limit(5)->get(['id', 'titre', 'est_actif']),
            'sujets' => SujetExamen::where('titre', 'like', $like)->limit(5)->get(['id', 'titre']),
            'pages' => Page::where(fn ($w) => $w->where('titre', 'like', $like)->orWhere('slug', 'like', $like))
                ->limit(5)->get(['id', 'titre', 'slug', 'statut']),
        ]);
    }
}
