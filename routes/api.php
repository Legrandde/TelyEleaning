<?php

// routes/api.php

use App\Http\Controllers\AdminBackofficeController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\AppParametreController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CandidatureProfesseurController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\CorrectionController;
use App\Http\Controllers\EspaceEleveController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\EleveController;
use App\Http\Controllers\ExerciceController;
use App\Http\Controllers\HistoriqueController;
use App\Http\Controllers\MatiereController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NiveauController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\ProfesseurController;
use App\Http\Controllers\UserController;
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

// ─── Newsletter (public + liens signés confirmation / désabonnement) ──────────
Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:10,1')->name('newsletter.store');
Route::match(['get', 'post'], '/newsletter/confirmation/{abonne}', [NewsletterController::class, 'confirmer'])
    ->middleware(['signed', 'throttle:10,1'])
    ->name('newsletter.confirmer');
Route::match(['get', 'post'], '/newsletter/desabonnement/{abonne}', [NewsletterController::class, 'desabonner'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('newsletter.desabonner');

// ─── Site public : CMS, paramètres et preuve sociale ────────
Route::middleware('throttle:120,1')->group(function () {
    Route::get('/site/parametres',  [AppParametreController::class, 'publics']);
    Route::get('/site/stats',       [PublicController::class, 'stats']);
    Route::get('/site/navigation',  [PageController::class, 'navigation']);
    Route::get('/site/accueil',     [PageController::class, 'accueil']);
    Route::get('/site/pages/{slug}', [PageController::class, 'afficher'])->where('slug', '[a-z0-9-]+');
    Route::get('/site/sitemap.xml', [PublicController::class, 'sitemap']);

    // Blog
    Route::get('/blog/articles',        [BlogController::class, 'index']);
    Route::get('/blog/articles/{slug}', [BlogController::class, 'afficher'])->where('slug', '[a-z0-9-]+');
    Route::get('/blog/categories',      [BlogController::class, 'categories']);
    Route::get('/blog/rss.xml',         [BlogController::class, 'rss']);
});

// ─── Public (lecture seule) ──────────────────────────────────
Route::apiResource('niveaux',       NiveauController::class)->only(['index', 'show']);
Route::apiResource('matieres',      MatiereController::class)->only(['index', 'show']);
Route::apiResource('categories',    CategorieController::class)->only(['index', 'show']);
Route::apiResource('exercices',     ExerciceController::class)->only(['index', 'show']);
Route::apiResource('sujets',        SujetExamenController::class)->only(['index', 'show']);
Route::apiResource('ressources',    RessourceController::class)->only(['index', 'show']);

// ─── Authentifié ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'compte.actif'])->group(function () {

    Route::get('exercices/{exercice}/correction', [CorrectionController::class, 'show']);
    Route::post('/auth/logout',   [AuthController::class, 'logout']);
    Route::get('/auth/me',        [AuthController::class, 'me']);
    Route::match(['post', 'put'], '/auth/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:20,1');
    Route::put('/auth/password',  [AuthController::class, 'updatePassword'])->middleware('throttle:5,1');

    Route::apiResource('historique', HistoriqueController::class)->only(['index', 'store', 'destroy']);

    // ─── Compte : droits RGPD ───
    Route::get('/auth/export',    [CompteController::class, 'exporter'])->middleware('throttle:5,1');
    Route::delete('/auth/compte', [CompteController::class, 'supprimer'])->middleware('throttle:5,1');

    // ─── Espace élève ───
    Route::prefix('eleve')->group(function () {
        Route::get('tableau-de-bord',        [EspaceEleveController::class, 'tableauDeBord']);
        Route::get('historique',             [EspaceEleveController::class, 'historique']);
        Route::post('activite',              [EspaceEleveController::class, 'enregistrerActivite'])->middleware('throttle:60,1');
        Route::get('favoris',                [EspaceEleveController::class, 'favoris']);
        Route::get('favoris/ids',            [EspaceEleveController::class, 'favorisIds']);
        Route::post('favoris/{exercice}',    [EspaceEleveController::class, 'basculerFavori'])->middleware('throttle:60,1');
        Route::post('onboarding',            [EspaceEleveController::class, 'onboarding']);
    });

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

        // Gestion des comptes : création avec rôle et changement de rôle
        Route::get('admin/users',               [UserController::class, 'index']);
        Route::post('admin/users',              [UserController::class, 'store']);
        Route::patch('admin/users/{user}/role', [UserController::class, 'updateRole']);
        Route::get('admin/users/export',        [UserController::class, 'export']);
        Route::patch('admin/users/{user}/statut', [UserController::class, 'updateStatut']);
        Route::delete('admin/users/{user}',     [UserController::class, 'destroy']);

        // Back-office : journal d'audit et recherche globale
        Route::get('admin/journal',             [AdminBackofficeController::class, 'journal']);
        Route::get('admin/recherche',           [AdminBackofficeController::class, 'recherche'])->middleware('throttle:60,1');

        // Page builder (CMS) et médiathèque
        Route::get('admin/pages',               [PageController::class, 'index']);
        Route::post('admin/pages',              [PageController::class, 'store']);
        Route::get('admin/pages/{page}',        [PageController::class, 'show']);
        Route::put('admin/pages/{page}',        [PageController::class, 'update']);
        Route::post('admin/pages/{page}/dupliquer', [PageController::class, 'dupliquer']);
        Route::delete('admin/pages/{page}',     [PageController::class, 'destroy']);
        Route::get('admin/medias',              [MediaController::class, 'index']);
        Route::post('admin/medias',             [MediaController::class, 'store'])->middleware('throttle:30,1');
        Route::delete('admin/medias/{media}',   [MediaController::class, 'destroy']);

        // Blog
        Route::get('admin/articles',                 [BlogController::class, 'adminIndex']);
        Route::post('admin/articles',                [BlogController::class, 'store']);
        Route::get('admin/articles/{article}',       [BlogController::class, 'adminShow']);
        Route::put('admin/articles/{article}',       [BlogController::class, 'update']);
        Route::post('admin/articles/{article}/dupliquer', [BlogController::class, 'dupliquer']);
        Route::delete('admin/articles/{article}',    [BlogController::class, 'destroy']);
        Route::post('admin/blog-categories',         [BlogController::class, 'storeCategorie']);
        Route::put('admin/blog-categories/{categorie}', [BlogController::class, 'updateCategorie']);
        Route::delete('admin/blog-categories/{categorie}', [BlogController::class, 'destroyCategorie']);

        // Gestion des candidatures enseignants
        Route::get('admin/candidatures-professeurs',                    [CandidatureProfesseurController::class, 'index']);
        Route::get('admin/candidatures-professeurs/{id}',               [CandidatureProfesseurController::class, 'show']);
        Route::get('admin/candidatures-professeurs/{id}/cv',            [CandidatureProfesseurController::class, 'cv']);
        Route::post('admin/candidatures-professeurs/{id}/approuver',    [CandidatureProfesseurController::class, 'approuver']);
        Route::post('admin/candidatures-professeurs/{id}/rejeter',      [CandidatureProfesseurController::class, 'rejeter']);
        Route::delete('admin/candidatures-professeurs/{id}',            [CandidatureProfesseurController::class, 'destroy']);
        // Paramètres de configuration plateforme
        Route::put('admin/parametres',                                  [AppParametreController::class, 'update']);
        Route::post('admin/parametres',                                 [AppParametreController::class, 'update']);
        // Gestion des abonnés newsletter
        Route::get('admin/newsletter',                                  [NewsletterController::class, 'index']);
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
