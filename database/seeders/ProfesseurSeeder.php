<?php

namespace Database\Seeders;

use App\Models\Exercice;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Professeur;
use App\Models\Ressource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProfesseurSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Création ou mise à jour de l'utilisateur Professeur Principal
        $user = User::updateOrCreate(
            ['email' => 'professeur@tely.com'],
            [
                'nom'      => 'Diop',
                'prenom'   => 'Cheikh',
                'password' => Hash::make('password123'),
                'role'     => 'professeur',
                'avatar'   => 'https://images.unsplash.com/photo-1522529599102-193c0d76b5b6?w=400&auto=format&fit=crop&q=80',
            ]
        );

        // 2. Profil Professeur
        $professeur = Professeur::updateOrCreate(
            ['user_id' => $user->id],
            [
                'specialite' => 'Mathématiques',
            ]
        );

        // 3. Optionnel : S'assurer qu'au moins une Matière et un Niveau existent pour le test
        $matiere = Matiere::firstOrCreate(
            ['code' => 'MATH'],
            [
                'nom'         => 'Mathématiques',
                'description' => 'Algèbre, Géométrie, Analyse et Probabilités pour le secondaire et supérieur.',
                'image'       => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&auto=format&fit=crop&q=80',
            ]
        );

        $niveau = Niveau::firstOrCreate(
            ['libelle' => 'Terminale S'],
            [
                'cycle' => 'Secondaire',
                'ordre' => 1,
            ]
        );

        // 4. Créer quelques exercices de test pour ce professeur
        Exercice::firstOrCreate(
            [
                'titre'         => 'Étude complète d\'une fonction exponentielle et limites',
                'professeur_id' => $professeur->id,
            ],
            [
                'enonce'     => "Soit f la fonction définie sur R par f(x) = (2x - 1)e^(-x) + 1.\n1. Déterminer les limites de f en -∞ et en +∞.\n2. Calculer la dérivée f'(x) et dresser le tableau de variations.\n3. Démontrer que l'équation f(x) = 0 admet une unique solution alpha.",
                'type'       => 'classique',
                'difficulte' => 'moyen',
                'est_actif'  => true,
                'matiere_id' => $matiere->id,
                'niveau_id'  => $niveau->id,
            ]
        );

        Exercice::firstOrCreate(
            [
                'titre'         => 'Nombres complexes et transformations géométriques',
                'professeur_id' => $professeur->id,
            ],
            [
                'enonce'     => "Dans le plan complexe rapporté à un repère orthonormé direct (O; u, v), on considère les points A(1+i) et B(-2+3i).\n1. Déterminer la forme trigonométrique de z_A.\n2. Caractériser la rotation de centre O qui transforme A en B.",
                'type'       => 'probleme',
                'difficulte' => 'difficile',
                'est_actif'  => true,
                'matiere_id' => $matiere->id,
                'niveau_id'  => $niveau->id,
            ]
        );

        // 5. Créer une ressource PDF de test pour ce professeur
        Ressource::firstOrCreate(
            [
                'titre'         => 'Fiche de Révision : Formulaire d\'Analyse & Dérivation',
                'professeur_id' => $professeur->id,
            ],
            [
                'description'  => 'Synthèse méthodologique complète pour préparer le Baccalauréat.',
                'type'         => 'fiche_methode',
                'fichier_path' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'fichier_nom'  => 'Formulaire_Analyse_TerminaleS.pdf',
                'format'       => 'pdf',
                'taille'       => 245000,
                'est_public'   => true,
                'matiere_id'   => $matiere->id,
                'niveau_id'    => $niveau->id,
            ]
        );

        // 6. Création d'un second professeur (SVT / Sciences) pour tester les listes & filtres
        $user2 = User::updateOrCreate(
            ['email' => 'prof.mariama@tely.com'],
            [
                'nom'      => 'Sow',
                'prenom'   => 'Mariama',
                'password' => Hash::make('password123'),
                'role'     => 'professeur',
                'avatar'   => 'https://images.unsplash.com/photo-1567532939604-b6b5b0db2604?w=400&auto=format&fit=crop&q=80',
            ]
        );

        Professeur::updateOrCreate(
            ['user_id' => $user2->id],
            [
                'specialite' => 'Sciences de la Vie et de la Terre (SVT)',
            ]
        );

        $this->command->info('[OK] Compte professeur créé avec succès :');
        $this->command->info('   - Email : professeur@tely.com');
        $this->command->info('   - Mot de passe : password123');
        $this->command->info('   - Rôle : professeur');
        $this->command->info('   - Nom : Cheikh Diop (Mathématiques)');
        $this->command->info('   - Exercices et ressources de test associés');
    }
}
