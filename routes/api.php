<?php

// routes/api.php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\AppParametreController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidatureProfesseurController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\EleveController;
use App\Http\Controllers\ExerciceController;
use App\Http\Controllers\HistoriqueController;
use App\Http\Controllers\MatiereController;
use App\Http\Controllers\NiveauController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\ProfesseurDashboardController;
use App\Http\Controllers\RessourceController;
use App\Http\Controllers\SujetExamenController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordResetController;

// ─── Auth (sécurisé avec rate limiting contre les attaques brute-force) ────────
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login',    [AuthController::class, 'login'])->middleware('throttle:6,1');

// ─── Analytics Tracker (public avec rate limiting raisonnable) ───────────────
Route::post('/analytics/track', [AnalyticsController::class, 'track'])->middleware('throttle:120,1');

// ─── Candidature Enseignant (public avec anti-spam) ───────────
Route::post('/candidatures-professeurs', [CandidatureProfesseurController::class, 'store'])->middleware('throttle:5,1');

// ─── Public (lecture seule) ──────────────────────────────────
Route::apiResource('niveaux',       NiveauController::class)->only(['index', 'show']);
Route::apiResource('matieres',      MatiereController::class)->only(['index', 'show']);
Route::apiResource('categories',    CategorieController::class)->only(['index', 'show']);
Route::apiResource('exercices',     ExerciceController::class)->only(['index', 'show']);
Route::apiResource('sujets',        SujetExamenController::class)->only(['index', 'show']);
Route::apiResource('ressources',    RessourceController::class)->only(['index', 'show']);

// ─── Authentifié ─────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::get('exercices/{exercice}/correction', [CorrectionController::class, 'show']);
    Route::post('/auth/logout',   [AuthController::class, 'logout']);
    Route::get('/auth/me',        [AuthController::class, 'me']);
    Route::match(['post', 'put'], '/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password',  [AuthController::class, 'updatePassword']);

    Route::apiResource('historique', HistoriqueController::class)->only(['index', 'store', 'destroy']);

    // ─── Notifications ───
    Route::get('notifications',             [AppNotificationController::class, 'index']);
    Route::post('notifications/{id}/read',  [AppNotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all',   [AppNotificationController::class, 'markAllAsRead']);

    // ─── Paramètres ───
    Route::get('parametres',                [AppParametreController::class, 'index']);

    // ─── Professeur + Admin ──────────────────────────────────
    Route::middleware('role:professeur,administrateur')->group(function () {
        Route::post('pdf/extract-text',      [PdfController::class, 'extractText']);
        Route::apiResource('exercices',     ExerciceController::class)->except(['index', 'show']);
        Route::apiResource('corrections',   CorrectionController::class)->except(['show']);
        Route::apiResource('sujets-examen', SujetExamenController::class)->parameters(['sujets-examen' => 'sujetExamen'])->except(['index', 'show']);
        Route::apiResource('sujets',        SujetExamenController::class)->parameters(['sujets' => 'sujetExamen'])->except(['index', 'show']);
        Route::get('professeur/stats',      [ProfesseurDashboardController::class, 'stats']);
        Route::apiResource('ressources',    RessourceController::class)->except(['index', 'show']);
    });

    // ─── Admin uniquement ────────────────────────────────────
    Route::middleware('role:administrateur')->group(function () {
        Route::get('admin/stats',        [AdminDashboardController::class, 'stats']);
        Route::get('admin/analytics',    [AnalyticsController::class, 'getStats']);
        Route::apiResource('niveaux',    NiveauController::class)->except(['index', 'show']);
        Route::apiResource('matieres',   MatiereController::class)->except(['index', 'show']);
        Route::apiResource('categories', CategorieController::class)->except(['index', 'show']);
        Route::apiResource('eleves',     EleveController::class);
        Route::apiResource('professeurs',ProfesseurController::class);

        // Gestion des candidatures enseignants
        Route::get('admin/candidatures-professeurs',                    [CandidatureProfesseurController::class, 'index']);
        Route::get('admin/candidatures-professeurs/{id}',               [CandidatureProfesseurController::class, 'show']);
        Route::post('admin/candidatures-professeurs/{id}/approuver',    [CandidatureProfesseurController::class, 'approuver']);
        Route::post('admin/candidatures-professeurs/{id}/rejeter',      [CandidatureProfesseurController::class, 'rejeter']);
        // Paramètres de configuration plateforme
        Route::put('admin/parametres',                                  [AppParametreController::class, 'update']);
        Route::post('admin/parametres',                                 [AppParametreController::class, 'update']);
    });
});

Route::get('documentation', function () {
    return view('api-docs.index');
});

// ----- Route pour l'authentification via Google
Route::get('/auth/google', [GoogleController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleController::class, 'callback']);
Route::post('/auth/google/one-tap', [GoogleController::class, 'oneTap'])->middleware('throttle:15,1');

// ------------ Route pour le reset password (limité contre le spam/bruteforce)
Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
