<?php

namespace App\Services;

use App\Models\AbonneNewsletter;
use App\Models\AnalyticsVisit;
use App\Models\AppNotification;
use App\Models\CandidatureProfesseur;
use App\Models\Historique;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Suppression d'un compte conforme au RGPD : les données personnelles sont effacées
 * ou dissociées, les contenus pédagogiques publiés (exercices d'un professeur) sont conservés
 * sous un auteur anonyme.
 */
class AnonymisationService
{
    public static function anonymiser(User $user, string $action, string $description): void
    {
        $email = $user->email;
        $cvs = CandidatureProfesseur::where('user_id', $user->id)->orWhere('email', $email)->pluck('cv_path')->filter();

        DB::transaction(function () use ($user, $email, $action, $description) {
            // Le journal ne conserve que l'identifiant, jamais l'e-mail
            JournalService::log($action, $description, $user);

            $user->tokens()->delete();

            if ($user->eleve) {
                Historique::where('eleve_id', $user->eleve->id)->delete();
                $user->eleve->delete();
            }

            AppNotification::where('user_id', $user->id)->delete();
            AbonneNewsletter::where('email', $email)->delete();
            CandidatureProfesseur::where('user_id', $user->id)->orWhere('email', $email)->delete();

            AnalyticsVisit::where('user_id', $user->id)->update([
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
                'city' => null,
            ]);

            $user->forceFill([
                'email' => "supprime-{$user->id}-".now()->timestamp.'@supprime.invalid',
                'nom' => 'Compte',
                'prenom' => 'supprimé',
                'avatar' => null,
                'google_id' => null,
                'motif_suspension' => null,
            ])->save();
            $user->delete();
        });

        foreach ($cvs as $chemin) {
            Storage::disk('local')->delete($chemin);
            Storage::disk('public')->delete($chemin);
        }
    }
}
