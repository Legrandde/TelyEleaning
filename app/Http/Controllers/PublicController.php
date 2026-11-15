<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Correction;
use App\Models\Eleve;
use App\Models\Exercice;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Page;
use App\Models\Professeur;
use App\Models\SujetExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Données publiques agrégées servant de preuve sociale sur le site vitrine.
 */
class PublicController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('stats_publiques', 600, fn () => [
            'exercices' => Exercice::actif()->count(),
            'sujets' => SujetExamen::count(),
            'corrections' => Correction::whereHas('exercice', fn ($q) => $q->actif())->count(),
            'matieres' => Matiere::count(),
            'niveaux' => Niveau::count(),
            'professeurs' => Professeur::count(),
            'eleves' => Eleve::count(),
        ]);

        return response()->json(['stats' => $stats]);
    }

    /**
     * Plan du site XML pour les moteurs de recherche (URLs du frontend).
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap_xml', 3600, function () {
            $front = rtrim((string) config('app.frontend_url'), '/');
            $url = fn (string $chemin, $date = null, string $priorite = '0.6') => '<url><loc>'
                .htmlspecialchars($front.$chemin, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'
                .($date ? '<lastmod>'.$date->toAtomString().'</lastmod>' : '')
                ."<priority>{$priorite}</priority></url>";

            $urls = [$url('/', null, '1.0'), $url('/exercices', null, '0.9'), $url('/sujets', null, '0.9'), $url('/blog', null, '0.8')];

            foreach (Page::publiee()->where('est_accueil', false)->get(['slug', 'updated_at']) as $p) {
                $urls[] = $url("/p/{$p->slug}", $p->updated_at, '0.5');
            }
            foreach (Article::visible()->get(['slug', 'updated_at']) as $a) {
                $urls[] = $url("/blog/{$a->slug}", $a->updated_at, '0.7');
            }
            foreach (Exercice::actif()->latest()->limit(5000)->get(['id', 'updated_at']) as $e) {
                $urls[] = $url("/exercices/{$e->id}", $e->updated_at, '0.6');
            }

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .implode('', $urls).'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
