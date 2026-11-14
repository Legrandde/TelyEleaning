<?php

namespace Tests\Feature;

use App\Models\CandidatureProfesseur;
use App\Models\Correction;
use App\Models\Exercice;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Professeur;
use App\Models\Ressource;
use App\Models\SujetExamen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecuriteAuditTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): array
    {
        $user = User::create(['nom' => 'P', 'prenom' => 'P', 'email' => uniqid() . '@t.sn', 'password' => 'secret123', 'role' => 'professeur']);
        return [$user, Professeur::create(['user_id' => $user->id])];
    }

    private function exercice(Professeur $prof, array $extra = []): Exercice
    {
        $m = Matiere::firstOrCreate(['code' => 'MATH'], ['nom' => 'Maths']);
        $n = Niveau::firstOrCreate(['libelle' => 'Tle'], ['cycle' => 'Lycée', 'ordre' => 1]);
        return Exercice::create(array_merge([
            'titre' => 'Ex', 'enonce' => str_repeat('a', 500), 'type' => 'classique', 'difficulte' => 'moyen',
            'matiere_id' => $m->id, 'niveau_id' => $n->id, 'professeur_id' => $prof->id, 'fichier_pdf' => 'exercices/x.pdf',
        ], $extra));
    }

    public function test_one_tap_refuse_sans_client_id(): void
    {
        config(['services.google.client_id' => null]);
        $this->postJson('/api/auth/google/one-tap', ['credential' => 'x'])->assertStatus(503);
    }

    public function test_one_tap_refuse_mauvaise_audience_et_email_non_verifie(): void
    {
        config(['services.google.client_id' => 'mon-client']);
        User::create(['nom' => 'O', 'prenom' => 'K', 'email' => 'ok@b.c', 'password' => 'secret123', 'role' => 'eleve']);
        Http::fake(['oauth2.googleapis.com/*' => Http::sequence()
            ->push(['aud' => 'autre-app', 'iss' => 'accounts.google.com', 'exp' => time() + 60, 'email' => 'a@b.c', 'email_verified' => 'true'])
            ->push(['aud' => 'mon-client', 'iss' => 'accounts.google.com', 'exp' => time() + 60, 'email' => 'a@b.c'])
            ->push(['aud' => 'mon-client', 'iss' => 'accounts.google.com', 'exp' => time() + 60, 'email' => 'ok@b.c', 'email_verified' => 'true', 'sub' => '1']),
        ]);
        $this->postJson('/api/auth/google/one-tap', ['credential' => 'a&access_token=b'])->assertStatus(403);
        $this->postJson('/api/auth/google/one-tap', ['credential' => 'x'])->assertStatus(403);
        $this->postJson('/api/auth/google/one-tap', ['credential' => 'x'])->assertOk();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'id_token=a%26access_token%3Db'));
    }

    public function test_exercice_public_sans_pdf_ni_email_et_inactif_cache(): void
    {
        [, $prof] = $this->prof();
        $ex = $this->exercice($prof);
        $res = $this->getJson("/api/exercices/{$ex->id}")->assertOk();
        $this->assertNull($res->json('pdf_url'));
        $this->assertArrayNotHasKey('email', $res->json('professeur.user'));
        $this->assertSame(180, mb_strlen($res->json('enonce')));

        $inactif = $this->exercice($prof, ['est_actif' => false]);
        $this->getJson("/api/exercices/{$inactif->id}")->assertNotFound();
    }

    public function test_correction_sur_exercice_d_un_autre_prof_refusee(): void
    {
        [, $profA] = $this->prof();
        [$userB] = $this->prof();
        $ex = $this->exercice($profA);
        $this->actingAs($userB)->postJson('/api/corrections', ['exercice_id' => $ex->id, 'contenu' => 'c'])->assertForbidden();
        $this->assertSame(0, Correction::count());
    }

    public function test_sujet_modifiable_uniquement_par_auteur_ou_admin(): void
    {
        [$userA] = $this->prof();
        [$userB] = $this->prof();
        $m = Matiere::create(['code' => 'PC', 'nom' => 'PC']);
        $n = Niveau::create(['libelle' => '1re', 'cycle' => 'Lycée', 'ordre' => 2]);

        $id = $this->actingAs($userA)->postJson('/api/sujets', [
            'titre' => 'S', 'annee' => 2024, 'session' => 'juin', 'type' => 'bac',
            'matiere_id' => $m->id, 'niveau_id' => $n->id, 'ennonce' => 'e',
        ])->assertCreated()->json('sujet.id');

        $this->actingAs($userB)->putJson("/api/sujets/{$id}", ['titre' => 'pirate'])->assertForbidden();
        $this->actingAs($userA)->putJson("/api/sujets/{$id}", ['titre' => 'ok'])->assertOk();
        $this->assertSame('ok', SujetExamen::find($id)->titre);
    }

    public function test_ressource_chemin_interne_refuse_et_fichier_d_autrui_preserve(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('exercices/autre.png', 'x');
        [$user, $prof] = $this->prof();
        $m = Matiere::create(['code' => 'SVT', 'nom' => 'SVT']);
        $n = Niveau::create(['libelle' => '2nde', 'cycle' => 'Lycée', 'ordre' => 3]);

        $this->actingAs($user)->postJson('/api/ressources', [
            'titre' => 'R', 'type' => 'cours', 'matiere_id' => $m->id, 'niveau_id' => $n->id,
            'fichier_path' => 'exercices/autre.png',
        ])->assertStatus(422);

        // Ligne historique malveillante déjà en base : la suppression ne doit pas toucher le fichier d'autrui
        $r = Ressource::create(['titre' => 'R', 'type' => 'cours', 'fichier_path' => 'exercices/autre.png',
            'matiere_id' => $m->id, 'niveau_id' => $n->id, 'professeur_id' => $prof->id, 'est_public' => true]);
        $this->actingAs($user)->deleteJson("/api/ressources/{$r->id}")->assertOk();
        Storage::disk('public')->assertExists('exercices/autre.png');
    }

    public function test_approbation_ne_touche_pas_un_compte_existant(): void
    {
        $admin = User::create(['nom' => 'A', 'prenom' => 'A', 'email' => 'admin@t.sn', 'password' => 'adminpass1', 'role' => 'administrateur']);
        $eleve = User::create(['nom' => 'E', 'prenom' => 'E', 'email' => 'eleve@t.sn', 'password' => 'elevepass1', 'role' => 'eleve']);

        $c1 = CandidatureProfesseur::create(['nom' => 'x', 'prenom' => 'x', 'email' => 'admin@t.sn', 'specialite' => 'm', 'statut' => 'en_attente']);
        $this->actingAs($admin)->postJson("/api/admin/candidatures-professeurs/{$c1->id}/approuver")->assertStatus(409);
        $this->assertSame('administrateur', $admin->fresh()->role);

        $c2 = CandidatureProfesseur::create(['nom' => 'x', 'prenom' => 'x', 'email' => 'eleve@t.sn', 'specialite' => 'm', 'statut' => 'en_attente']);
        $res = $this->actingAs($admin)->postJson("/api/admin/candidatures-professeurs/{$c2->id}/approuver")->assertOk();
        $this->assertNull($res->json('generated_password'));
        $this->assertTrue(Hash::check('elevepass1', $eleve->fresh()->password));
    }

    public function test_cv_prive_et_reserve_admin(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $file = \Illuminate\Http\UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf');
        $this->postJson('/api/candidatures-professeurs', ['nom' => 'n', 'prenom' => 'p', 'email' => 'c@t.sn', 'specialite' => 's', 'cv' => $file])->assertCreated();

        $c = CandidatureProfesseur::first();
        Storage::disk('local')->assertExists($c->cv_path);
        Storage::disk('public')->assertMissing($c->cv_path);

        [$prof] = $this->prof();
        $this->actingAs($prof)->get("/api/admin/candidatures-professeurs/{$c->id}/cv")->assertForbidden();
        $admin = User::create(['nom' => 'A', 'prenom' => 'A', 'email' => 'a2@t.sn', 'password' => 'adminpass1', 'role' => 'administrateur']);
        $this->actingAs($admin)->get("/api/admin/candidatures-professeurs/{$c->id}/cv")->assertOk();
    }

    public function test_inscription_cree_un_profil_eleve_vide(): void
    {
        $this->postJson('/api/auth/register', [
            'nom' => 'Diop', 'prenom' => 'Awa', 'email' => 'awa@t.sn',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertCreated();

        $this->postJson('/api/auth/register', [
            'nom' => 'Ba', 'prenom' => 'Moussa', 'email' => 'moussa@t.sn',
            'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1',
        ])->assertCreated();

        $this->assertSame(2, \App\Models\Eleve::whereNull('matricule')->count());
    }

    public function test_matricule_en_double_refuse_a_la_mise_a_jour_du_profil(): void
    {
        $a = User::create(['nom' => 'A', 'prenom' => 'A', 'email' => 'a@t.sn', 'password' => 'secret123', 'role' => 'eleve']);
        $b = User::create(['nom' => 'B', 'prenom' => 'B', 'email' => 'b@t.sn', 'password' => 'secret123', 'role' => 'eleve']);
        \App\Models\Eleve::create(['user_id' => $a->id, 'matricule' => 'M001']);
        \App\Models\Eleve::create(['user_id' => $b->id]);

        $payload = fn (User $u) => ['nom' => $u->nom, 'prenom' => $u->prenom, 'email' => $u->email, 'matricule' => 'M001'];
        $this->actingAs($b)->postJson('/api/auth/profile', $payload($b))->assertStatus(422);
        $this->actingAs($a)->postJson('/api/auth/profile', $payload($a))->assertOk();
    }

    public function test_suppression_candidature_et_cv(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Storage::disk('local')->put('candidatures/cv/x.pdf', 'cv');
        $c = CandidatureProfesseur::create(['nom' => 'n', 'prenom' => 'p', 'email' => 'd@t.sn', 'specialite' => 's', 'statut' => 'en_attente', 'cv_path' => 'candidatures/cv/x.pdf']);
        $admin = User::create(['nom' => 'A', 'prenom' => 'A', 'email' => 'a3@t.sn', 'password' => 'adminpass1', 'role' => 'administrateur']);

        $this->actingAs($admin)->deleteJson("/api/admin/candidatures-professeurs/{$c->id}")->assertOk();
        $this->assertNull(CandidatureProfesseur::find($c->id));
        Storage::disk('local')->assertMissing('candidatures/cv/x.pdf');
    }

    public function test_matiere_publique_ne_divulgue_pas_les_exercices_complets(): void
    {
        [, $prof] = $this->prof();
        $ex = $this->exercice($prof);
        $this->exercice($prof, ['est_actif' => false, 'titre' => 'Brouillon']);

        $res = $this->getJson("/api/matieres/{$ex->matiere_id}")->assertOk();
        $this->assertCount(1, $res->json('exercices'));
        $this->assertArrayNotHasKey('enonce', $res->json('exercices.0'));
        $this->assertNull($res->json('exercices.0.pdf_url'));

        $this->getJson("/api/niveaux/{$ex->niveau_id}")->assertOk()->assertJsonMissing(['titre' => 'Brouillon']);
    }

    public function test_image_matiere_refuse_script_php_et_lien_javascript(): void
    {
        Storage::fake('public');
        $admin = User::create(['nom' => 'A', 'prenom' => 'A', 'email' => 'a4@t.sn', 'password' => 'adminpass1', 'role' => 'administrateur']);
        $php = \Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>');

        $this->actingAs($admin)->postJson('/api/matieres', ['nom' => 'X', 'code' => 'X1', 'image' => $php])->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/matieres', ['nom' => 'X', 'code' => 'X2', 'image' => 'javascript:alert(1)'])->assertStatus(422);
        $this->actingAs($admin)->postJson('/api/matieres', ['nom' => 'X', 'code' => 'X3', 'image' => 'https://cdn.exemple.com/m.png'])->assertCreated();
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_filtres_non_numeriques_ne_plantent_pas(): void
    {
        $this->getJson('/api/exercices?niveau_id=abc&matiere_id=x')->assertOk();
    }

    public function test_tracker_accepte_le_payload_reel_du_frontend(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->postJson('/api/analytics/track', [
            'action' => 'page_view', 'path' => '/exercices', 'session_id' => 'sid_abc123xyz',
            'os' => 'iOS (iPhone)', 'browser' => 'Google Chrome', 'device_type' => 'mobile', 'timezone' => 'Africa/Dakar',
        ])->assertCreated();

        $visite = \App\Models\AnalyticsVisit::where('session_id', 'sid_abc123xyz')->first();
        $this->assertSame('/exercices', $visite->path);
        $this->assertSame('10.1.2.0', $visite->ip_address);
        $this->assertSame('Sénégal', $visite->country);
    }

    public function test_tracker_refuse_valeurs_trop_longues_sans_erreur_500(): void
    {
        $this->postJson('/api/analytics/track', ['path' => '/' . str_repeat('a', 300)])->assertStatus(422);
        $this->postJson('/api/analytics/track', ['metadata' => ['x' => str_repeat('a', 3000)]])->assertStatus(422);
    }

    public function test_ip_anonymisee_et_masquee(): void
    {
        $this->assertSame('197.25.4.0', \App\Services\GeoIpService::anonymiser('197.25.4.211'));
        $this->assertSame('2001:db8:85a3::', \App\Services\GeoIpService::anonymiser('2001:db8:85a3:8d3:1319:8a2e:370:7348'));
        $this->assertSame('2001:db8:85a3:****', \App\Services\GeoIpService::masquer('2001:db8:85a3::'));
        $this->assertNull(\App\Services\GeoIpService::anonymiser('pas-une-ip'));
    }

    public function test_traces_de_plus_de_13_mois_purgees(): void
    {
        $vieille = \App\Models\AnalyticsVisit::create(['action' => 'page_view', 'path' => '/']);
        $vieille->forceFill(['created_at' => now()->subMonths(14)])->save();
        \App\Models\AnalyticsVisit::create(['action' => 'page_view', 'path' => '/']);

        $this->artisan('model:prune', ['--model' => [\App\Models\AnalyticsVisit::class]])->assertSuccessful();
        $this->assertSame(1, \App\Models\AnalyticsVisit::count());
    }

    public function test_analytics_ignore_user_id_fourni(): void
    {
        [$prof] = $this->prof();
        $this->postJson('/api/analytics/track', ['action' => 'page_view', 'user_id' => $prof->id])->assertCreated();
        $this->assertNull(\App\Models\AnalyticsVisit::latest('id')->first()->user_id);
    }
}
