<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\CandidatureProfesseur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur connecté avec nombre de non lues
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRole = $user?->role ?? 'eleve';
        $userId = $user?->id;

        // Si aucune notification n'existe encore pour ce rôle, en générer automatiquement
        $countTotal = AppNotification::where(function ($q) use ($userId, $userRole) {
            $q->where('user_id', $userId)
              ->orWhere(function ($sub) use ($userRole) {
                  $sub->whereNull('user_id')
                      ->where(function ($roleQ) use ($userRole) {
                          $roleQ->whereNull('role_target')
                                ->orWhere('role_target', $userRole);
                      });
              });
        })->count();

        if ($countTotal === 0) {
            $this->seedInitialNotifications($userRole, $userId);
        }

        $notifications = AppNotification::where(function ($q) use ($userId, $userRole) {
            $q->where('user_id', $userId)
              ->orWhere(function ($sub) use ($userRole) {
                  $sub->whereNull('user_id')
                      ->where(function ($roleQ) use ($userRole) {
                          $roleQ->whereNull('role_target')
                                ->orWhere('role_target', $userRole);
                      });
              });
        })
        ->orderByDesc('created_at')
        ->limit(30)
        ->get();

        $unreadCount = $notifications->where('is_read', false)->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $notification = AppNotification::find($id);

        if ($notification) {
            $isAuthorized = ($notification->user_id && $notification->user_id === $user?->id) ||
                           (!$notification->user_id && (!$notification->role_target || $notification->role_target === $user?->role));

            if (!$isAuthorized) {
                return response()->json(['message' => 'Action non autorisée.'], 403);
            }

            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $userRole = $user?->role ?? 'eleve';
        $userId = $user?->id;

        AppNotification::where(function ($q) use ($userId, $userRole) {
            $q->where('user_id', $userId)
              ->orWhere(function ($sub) use ($userRole) {
                  $sub->whereNull('user_id')
                      ->where(function ($roleQ) use ($userRole) {
                          $roleQ->whereNull('role_target')
                                ->orWhere('role_target', $userRole);
                      });
              });
        })
        ->whereNull('read_at')
        ->update(['read_at' => now()]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Toutes les notifications ont été marquées comme lues',
        ]);
    }

    /**
     * Génère des notifications contextuelles initiales
     */
    private function seedInitialNotifications(string $role, ?int $userId): void
    {
        if ($role === 'administrateur') {
            $pendingCand = CandidatureProfesseur::where('statut', 'en_attente')->count();

            AppNotification::create([
                'role_target' => 'administrateur',
                'titre'       => 'Candidatures Enseignants',
                'message'     => $pendingCand > 0 
                    ? "{$pendingCand} professeur(s) attendent la validation de leur dossier."
                    : "De nouveaux candidats postulent régulièrement depuis la page d'accueil.",
                'type'        => 'candidature',
                'lien'        => '/Dashbord-admin/candidatures',
                'created_at'  => now()->subMinutes(15),
            ]);

            AppNotification::create([
                'role_target' => 'administrateur',
                'titre'       => 'Traceur d\'audience en direct',
                'message'     => 'Le module statistique analyse en temps réel les visiteurs, IP et navigateurs.',
                'type'        => 'systeme',
                'lien'        => '/Dashbord-admin/statistiques',
                'created_at'  => now()->subHours(2),
            ]);

            AppNotification::create([
                'role_target' => 'administrateur',
                'titre'       => 'Base d\'exercices & sujets',
                'message'     => 'Les banques d\'épreuves et travaux dirigés sont synchronisées avec les corrigés.',
                'type'        => 'exercice',
                'lien'        => '/Dashbord-admin/exercices',
                'created_at'  => now()->subDay(),
            ]);
        } elseif ($role === 'professeur') {
            AppNotification::create([
                'user_id'    => $userId,
                'titre'      => 'Compte Enseignant validé',
                'message'    => 'Bienvenue sur Tely ! Votre compte professeur est pleinement opérationnel.',
                'type'       => 'systeme',
                'lien'       => '/Dashboard-professeur',
                'created_at' => now()->subMinutes(30),
            ]);

            AppNotification::create([
                'user_id'    => $userId,
                'titre'      => 'Publication d\'exercices',
                'message'    => 'Vous pouvez soumettre de nouveaux TD avec énoncés et corrigés officiels.',
                'type'       => 'exercice',
                'lien'       => '/Dashboard-professeur/exercices',
                'created_at' => now()->subHours(3),
            ]);

            AppNotification::create([
                'user_id'    => $userId,
                'titre'      => 'Ressources & fiches PDF',
                'message'    => 'Partagez vos polycopiés et résumés de cours au format PDF avec vos élèves.',
                'type'       => 'ressource',
                'lien'       => '/Dashboard-professeur/ressources',
                'created_at' => now()->subDay(),
            ]);
        }
    }
}
