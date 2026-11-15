<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\CategorieBlog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Catégories de blog par défaut et un premier article de bienvenue.
 */
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['nom' => 'Méthodologie', 'slug' => 'methodologie', 'couleur' => 'amber', 'description' => 'Bien s\'organiser et apprendre efficacement.'],
            ['nom' => 'Examens', 'slug' => 'examens', 'couleur' => 'sky', 'description' => 'Calendriers, conseils et actualités des examens.'],
            ['nom' => 'Orientation', 'slug' => 'orientation', 'couleur' => 'emerald', 'description' => 'Choisir sa filière et préparer son avenir.'],
            ['nom' => 'Vie de Tely', 'slug' => 'vie-de-tely', 'couleur' => 'violet', 'description' => 'Nouveautés et coulisses de la plateforme.'],
        ];

        foreach ($categories as $i => $categorie) {
            CategorieBlog::firstOrCreate(['slug' => $categorie['slug']], $categorie + ['ordre' => $i]);
        }

        $blocs = array_map(fn (array $b) => ['id' => (string) Str::uuid(), 'type' => $b[0], 'props' => $b[1], 'masque' => false], [
            ['texte', ['titre' => '', 'alignement' => 'gauche', 'contenu' => "Réviser n'est pas une question de quantité mais de méthode. Voici cinq habitudes simples, adoptées par les élèves qui progressent le plus vite sur Tely."]],
            ['etapes', ['titre' => 'La méthode en 5 étapes', 'sous_titre' => '', 'elements' => [
                ['titre' => 'Planifier', 'texte' => 'Répartissez vos matières sur la semaine plutôt que de tout réviser la veille.'],
                ['titre' => 'S\'entraîner', 'texte' => 'Faites l\'exercice sans regarder le corrigé, en conditions réelles.'],
                ['titre' => 'Corriger', 'texte' => 'Comparez avec le corrigé détaillé et notez vos erreurs.'],
                ['titre' => 'Revenir', 'texte' => 'Refaites les exercices ratés quelques jours plus tard.'],
                ['titre' => 'Simuler', 'texte' => 'Terminez par un ancien sujet d\'examen chronométré.'],
            ]]],
            ['cta', ['titre' => 'Prêt à vous entraîner ?', 'texte' => 'Des centaines d\'exercices corrigés vous attendent.', 'bouton_texte' => 'Explorer les exercices', 'bouton_lien' => '/exercices', 'fond' => 'ambre']],
        ]);

        Article::firstOrCreate(['slug' => '5-habitudes-pour-reviser-efficacement'], [
            'titre' => '5 habitudes pour réviser efficacement',
            'extrait' => 'Réviser n\'est pas une question de quantité mais de méthode : la routine des élèves qui progressent le plus vite.',
            'image_couverture' => '/banner1.png',
            'blocs' => $blocs,
            'tags' => ['révisions', 'méthode'],
            'statut' => 'publie',
            'publie_le' => now(),
            'mis_en_avant' => true,
            'temps_lecture' => Article::estimerTempsLecture($blocs),
            'meta_description' => 'Cinq habitudes simples pour réviser efficacement et réussir vos examens.',
            'categorie_blog_id' => CategorieBlog::where('slug', 'methodologie')->value('id'),
        ]);
    }
}
