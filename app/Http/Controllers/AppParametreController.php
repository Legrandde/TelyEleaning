<?php

namespace App\Http\Controllers;

use App\Models\AppParametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppParametreController extends Controller
{
    /**
     * Récupère tous les paramètres organisés par groupes
     */
    public function index(): JsonResponse
    {
        $defaults = [
            // Général
            'site_nom'               => 'Tely Elearning',
            'site_description'       => 'Plateforme d\'exercices, de sujets d\'examen et de ressources éducatives.',
            'contact_email'          => 'contact@tely.com',
            'mode_maintenance'       => '0',
            // Pédagogie
            'inscriptions_ouvertes'  => '1',
            'candidatures_ouvertes'  => '1',
            'correction_obligatoire' => '0',
            'taille_max_fichier_mo'  => '25',
            // Notifications
            'notif_email_candidature'=> '1',
            'notif_email_exercice'   => '0',
            'alertes_navigateur'     => '1',
            // Sécurité
            'longueur_min_mdp'       => '8',
            'expiration_session_min' => '120',
        ];

        $params = AppParametre::all()->pluck('valeur', 'cle')->toArray();
        $merged = array_merge($defaults, $params);

        return response()->json([
            'status'     => 'success',
            'parametres' => $merged,
        ]);
    }

    /**
     * Met à jour les paramètres
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->input('parametres', []);

        if (!is_array($data)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Format de paramètres invalide.',
            ], 422);
        }

        foreach ($data as $cle => $valeur) {
            $groupe = 'general';
            if (str_starts_with($cle, 'notif_') || str_contains($cle, 'alerte')) {
                $groupe = 'notifications';
            } elseif (str_contains($cle, 'mdp') || str_contains($cle, 'session') || str_contains($cle, 'securite')) {
                $groupe = 'securite';
            } elseif (str_contains($cle, 'inscriptions') || str_contains($cle, 'candidatures') || str_contains($cle, 'exercice') || str_contains($cle, 'fichier')) {
                $groupe = 'pedagogie';
            }

            AppParametre::set($cle, $valeur, $groupe);
        }

        return response()->json([
            'status'     => 'success',
            'message'    => 'Paramètres enregistrés avec succès !',
            'parametres' => AppParametre::all()->pluck('valeur', 'cle'),
        ]);
    }
}
