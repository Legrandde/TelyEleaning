<?php

namespace Database\Seeders;

use App\Models\Matiere;
use Illuminate\Database\Seeder;

class MatiereSeeder extends Seeder
{
    /**
     * Matières officielles du programme éducatif de la République de Guinée (MEPU-A).
     */
    public function run(): void
    {
        $matieres = [
            [
                'code'        => 'MATH',
                'nom'         => 'Mathématiques',
                'description' => 'Calcul, Algèbre, Géométrie, Trigonométrie, Analyse et Probabilités (Primaire, Collège et Lycée SM, SE, SS).',
                'image'       => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'PHYS',
                'nom'         => 'Physique',
                'description' => 'Mécanique newtonienne, Électricité, Circuits, Magnétisme, Optique géométrique et Ondes pour les filières scientifiques (Lycée SM & SE).',
                'image'       => 'https://images.unsplash.com/photo-1507668077129-56e32842fceb?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'CHIM',
                'nom'         => 'Chimie',
                'description' => 'Chimie générale, Réactions acido-basiques, Chimie organique, Électrochimie et Cinétique chimique (Lycée SM & SE).',
                'image'       => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'PC',
                'nom'         => 'Physique-Chimie',
                'description' => 'Sciences Physiques et Chimie fondamentales pour le collège (7ème à la 10ème Année / BEPC).',
                'image'       => 'https://images.unsplash.com/photo-1518152006812-edab29b069ac?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'BIOL',
                'nom'         => 'Biologie (SVT)',
                'description' => 'Sciences de la Vie et de la Terre : Génétique, Immunologie, Écologie, Géologie, Physiologie humaine et Reproduction (SE, SM, Collège).',
                'image'       => 'https://images.unsplash.com/photo-1530210124550-912dc1381cb8?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'FRAN',
                'nom'         => 'Français & Littérature',
                'description' => 'Grammaire, Vocabulaire, Compréhension de texte, Dissertation littéraire, Commentaire composé et Littérature africaine et francophone.',
                'image'       => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'PHIL',
                'nom'         => 'Philosophie',
                'description' => 'Notions fondamentales, Pensée philosophique, Épistémologie, Éthique et Politique pour les Terminales (BAC SM, SE, SS).',
                'image'       => 'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'HIST',
                'nom'         => 'Histoire',
                'description' => 'Histoire de la Guinée (Royaumes, Résistances, Indépendance de 1958), Décolonisation, Histoire de l\'Afrique et Histoire contemporaine.',
                'image'       => 'https://images.unsplash.com/photo-1461360370896-922624d12aa1?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'GEOG',
                'nom'         => 'Géographie',
                'description' => 'Géographie physique, humaine et économique de la Guinée (Basse, Moyenne, Haute-Guinée et Guinée Forestière), de l\'Afrique et du monde.',
                'image'       => 'https://images.unsplash.com/photo-1526778548025-fa2f459cd5c1?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'ECM',
                'nom'         => 'Éducation Civique et Morale (ECM)',
                'description' => 'Institutions républicaines guinéennes, Citoyenneté, Démocratie, Droits de l\'Homme, Paix et Valeurs civiques.',
                'image'       => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'ANGL',
                'nom'         => 'Anglais',
                'description' => 'Langue vivante 1 : Grammaire anglaise, Vocabulaire, Compréhension écrite et Expression pour le BEPC et le Baccalauréat.',
                'image'       => 'https://images.unsplash.com/photo-1543109740-4bdb38fda756?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'ECON',
                'nom'         => 'Économie',
                'description' => 'Principes d\'économie générale, Microéconomie, Macroéconomie, Mondialisation et Développement pour la filière Sciences Sociales (Lycée SS).',
                'image'       => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'CALC',
                'nom'         => 'Calcul & Résolution de Problèmes',
                'description' => 'Opérations mathématiques fondamentales, Système métrique, Géométrie de base et Problèmes pratiques pour le cycle Élémentaire (CEE).',
                'image'       => 'https://images.unsplash.com/photo-1596495578065-6e0763fa1178?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'SCOB',
                'nom'         => 'Sciences d\'Observation (Élémentaire)',
                'description' => 'Éveil scientifique, Hygiène, Santé, Nutrition, Botanique, Zoologie et Protection de l\'environnement (1ère à la 6ème Année).',
                'image'       => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'ARAB',
                'nom'         => 'Langue Arabe (Franco-Arabe)',
                'description' => 'Grammaire arabe (Nahw), Conjugaison (Sarf), Lecture et Rédaction pour les filières de l\'Enseignement Franco-Arabe guinéen (CEE, BEPC, BAC).',
                'image'       => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?w=600&auto=format&fit=crop&q=80',
            ],
            [
                'code'        => 'EDIS',
                'nom'         => 'Éducation Islamique & Théologie',
                'description' => 'Tawhid, Fiqh, Hadith et Morale islamique pour les épreuves de l\'Enseignement Franco-Arabe en République de Guinée.',
                'image'       => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=600&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($matieres as $item) {
            Matiere::updateOrCreate(
                ['code' => $item['code']],
                [
                    'nom'         => $item['nom'],
                    'description' => $item['description'],
                    'image'       => $item['image'],
                ]
            );
        }
    }
}
