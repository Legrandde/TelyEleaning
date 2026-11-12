<?php

namespace Database\Seeders;

use App\Models\Niveau;
use Illuminate\Database\Seeder;

class NiveauSeeder extends Seeder
{
    /**
     * Niveaux scolaires officiels de la République de Guinée (MEPU-A).
     */
    public function run(): void
    {
        $niveaux = [
            // ─── Enseignement Élémentaire (Primaire - 6 ans) ───
            [
                'libelle' => '1ère Année (CP1)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 1,
            ],
            [
                'libelle' => '2ème Année (CP2)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 2,
            ],
            [
                'libelle' => '3ème Année (CE1)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 3,
            ],
            [
                'libelle' => '4ème Année (CE2)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 4,
            ],
            [
                'libelle' => '5ème Année (CM1)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 5,
            ],
            [
                'libelle' => '6ème Année (CEE)',
                'cycle'   => 'Élémentaire',
                'ordre'   => 6,
            ],

            // ─── Enseignement Secondaire - 1er Cycle (Collège - 4 ans) ───
            [
                'libelle' => '7ème Année',
                'cycle'   => 'Collège',
                'ordre'   => 7,
            ],
            [
                'libelle' => '8ème Année',
                'cycle'   => 'Collège',
                'ordre'   => 8,
            ],
            [
                'libelle' => '9ème Année',
                'cycle'   => 'Collège',
                'ordre'   => 9,
            ],
            [
                'libelle' => '10ème Année (BEPC)',
                'cycle'   => 'Collège',
                'ordre'   => 10,
            ],

            // ─── Enseignement Secondaire - 2nd Cycle (Lycée - 3 ans) ───
            [
                'libelle' => '11ème Année Sciences',
                'cycle'   => 'Lycée',
                'ordre'   => 11,
            ],
            [
                'libelle' => '11ème Année Littéraire',
                'cycle'   => 'Lycée',
                'ordre'   => 12,
            ],
            [
                'libelle' => '12ème Année Sciences Mathématiques (12ème SM)',
                'cycle'   => 'Lycée',
                'ordre'   => 13,
            ],
            [
                'libelle' => '12ème Année Sciences Expérimentales (12ème SE)',
                'cycle'   => 'Lycée',
                'ordre'   => 14,
            ],
            [
                'libelle' => '12ème Année Sciences Sociales (12ème SS)',
                'cycle'   => 'Lycée',
                'ordre'   => 15,
            ],
            [
                'libelle' => 'Terminale Sciences Mathématiques (Terminale SM / BAC)',
                'cycle'   => 'Lycée',
                'ordre'   => 16,
            ],
            [
                'libelle' => 'Terminale Sciences Expérimentales (Terminale SE / BAC)',
                'cycle'   => 'Lycée',
                'ordre'   => 17,
            ],
            [
                'libelle' => 'Terminale Sciences Sociales (Terminale SS / BAC)',
                'cycle'   => 'Lycée',
                'ordre'   => 18,
            ],
        ];

        foreach ($niveaux as $item) {
            Niveau::updateOrCreate(
                ['libelle' => $item['libelle']],
                [
                    'cycle' => $item['cycle'],
                    'ordre' => $item['ordre'],
                ]
            );
        }
    }
}
