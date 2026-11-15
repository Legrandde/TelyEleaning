<?php

namespace Tests\Feature;

use App\Models\AppParametre;
use App\Models\Eleve;
use App\Models\Exercice;
use App\Models\Historique;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Page;
use App\Models\Professeur;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlateformeTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'eleve', array $extra = []): User
    {
        $user = User::create(array_merge([
            'nom' => 'Diallo', 'prenom' => 'Awa', 'email' => uniqid().'@t.sn',
            'password' => 'secret123', 'role' => $role,
        ], $extra));

        if ($role === 'eleve') {
            Eleve::create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function exercice(array $extra = []): Exercice
    {
        $prof = Professeur::create(['user_id' => $this->user('professeur')->id]);
        $m = Matiere::firstOrCreate(['code' => 'MATH'], ['nom' => 'Maths']);
        $n = Niveau::firstOrCreate(['libelle' => 'Tle'], ['cycle' => 'Lycée', 'ordre' => 1]);

        return Exercice::create(array_merge([
            'titre' => 'Ex', 'enonce' => 'Énoncé', 'type' => 'classique', 'difficulte' => 'moyen',
            'matiere_id' => $m->id, 'niveau_id' => $n->id, 'professeur_id' => $prof->id,
        ], $extra));
    }

    private function reinitialiserAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function bloc(string $type, array $props = []): array
    {
        return ['id' => uniqid(), 'type' => $type, 'props' => $props];
    }

    // ─── Page builder ─────────────────────────────────────────

    public function test_admin_cree_publie_et_le_public_voit_la_page(): void
    {
        $admin = $this->user('administrateur');

        $res = $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'Offre Rentrée',
            'blocs' => [
                $this->bloc('hero', ['titre' => 'Bienvenue', 'bouton_lien' => '/auth/signup']),
                $this->bloc('texte', ['titre' => 'Caché']) + ['masque' => true],
            ],
        ])->assertCreated();

        $id = $res->json('page.id');
        $this->assertSame('offre-rentree', $res->json('page.slug'));
        $this->getJson('/api/site/pages/offre-rentree')->assertNotFound();

        $this->actingAs($admin)->putJson("/api/admin/pages/{$id}", ['statut' => 'publie'])->assertOk();

        $public = $this->getJson('/api/site/pages/offre-rentree')->assertOk();
        $this->assertCount(1, $public->json('page.blocs'));
        $this->assertSame('hero', $public->json('page.blocs.0.type'));
        $this->assertNotNull(Page::find($id)->publie_le);
        $this->assertTrue(JournalActivite::where('action', 'page.publiee')->exists());
    }

    public function test_page_rejette_liens_dangereux_type_inconnu_et_slug_reserve(): void
    {
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'X', 'blocs' => [$this->bloc('cta', ['bouton_lien' => 'javascript:alert(1)'])],
        ])->assertStatus(422)->assertJsonValidationErrors('blocs.0.props');

        $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'X', 'blocs' => [$this->bloc('faq', ['elements' => [['question' => 'q', 'reponse' => 'r', 'lien' => '//evil.com']]])],
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'X', 'blocs' => [$this->bloc('script', [])],
        ])->assertStatus(422)->assertJsonValidationErrors('blocs.0.type');

        $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'Exercices', 'slug' => 'exercices', 'blocs' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_une_seule_page_d_accueil_et_reservee_aux_admins(): void
    {
        $admin = $this->user('administrateur');
        $a = $this->actingAs($admin)->postJson('/api/admin/pages', ['titre' => 'A', 'blocs' => [], 'est_accueil' => true, 'statut' => 'publie'])->json('page.id');
        $b = $this->actingAs($admin)->postJson('/api/admin/pages', ['titre' => 'B', 'blocs' => [], 'est_accueil' => true, 'statut' => 'publie'])->json('page.id');

        $this->assertFalse(Page::find($a)->est_accueil);
        $this->assertSame($b, $this->getJson('/api/site/accueil')->json('page.id'));

        $prof = $this->user('professeur');
        $this->actingAs($prof)->getJson('/api/admin/pages')->assertForbidden();
        $this->actingAs($prof)->postJson('/api/admin/pages', ['titre' => 'P', 'blocs' => []])->assertForbidden();
    }

    public function test_duplication_et_suppression_liberent_le_slug(): void
    {
        $admin = $this->user('administrateur');
        $id = $this->actingAs($admin)->postJson('/api/admin/pages', ['titre' => 'Promo', 'blocs' => []])->json('page.id');

        $copie = $this->actingAs($admin)->postJson("/api/admin/pages/{$id}/dupliquer")->assertCreated();
        $this->assertSame('promo-copie', $copie->json('page.slug'));
        $this->assertSame('brouillon', $copie->json('page.statut'));

        $this->actingAs($admin)->deleteJson("/api/admin/pages/{$id}")->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/pages', ['titre' => 'Promo', 'blocs' => []])->assertCreated();
    }

    public function test_seeder_pages_legales_idempotent(): void
    {
        $this->seed(PageSeeder::class);
        $this->seed(PageSeeder::class);

        $this->assertSame(5, Page::count());
        $nav = $this->getJson('/api/site/navigation')->assertOk();
        $this->assertCount(3, $nav->json('footer'));
        $this->assertCount(2, $nav->json('menu'));
    }

    public function test_mediatheque_refuse_svg(): void
    {
        Storage::fake('public');
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->postJson('/api/admin/medias', [
            'fichier' => UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"></svg>'),
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/api/admin/medias', [
            'fichier' => UploadedFile::fake()->image('photo.png'),
        ])->assertCreated()->assertJsonPath('media.nom', 'photo.png');
    }

    // ─── Comptes : suspension, suppression, export ───────────

    public function test_compte_suspendu_ne_peut_plus_se_connecter_ni_utiliser_son_jeton(): void
    {
        $admin = $this->user('administrateur');
        $eleve = $this->user('eleve', ['email' => 'awa@t.sn']);
        $token = $eleve->createToken('t')->plainTextToken;

        $this->actingAs($admin)->patchJson("/api/admin/users/{$eleve->id}/statut", ['suspendu' => true, 'motif' => 'Spam'])->assertOk();
        $this->assertSame(0, $eleve->tokens()->count());

        $this->reinitialiserAuth();
        $this->postJson('/api/auth/login', ['email' => 'awa@t.sn', 'password' => 'secret123'])
            ->assertForbidden()->assertJsonPath('code', 'compte_suspendu');

        // Un jeton émis malgré tout est bloqué par le middleware
        $eleve->refresh();
        $token = $eleve->createToken('t2')->plainTextToken;
        $this->reinitialiserAuth();
        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();

        $this->reinitialiserAuth();
        $this->actingAs($admin)->patchJson("/api/admin/users/{$eleve->id}/statut", ['suspendu' => false])->assertOk();
        $this->reinitialiserAuth();
        $this->postJson('/api/auth/login', ['email' => 'awa@t.sn', 'password' => 'secret123'])->assertOk();
        $this->assertNotNull($eleve->fresh()->derniere_connexion_le);
    }

    public function test_admin_ne_peut_ni_se_suspendre_ni_se_supprimer(): void
    {
        $admin = $this->user('administrateur');
        $this->actingAs($admin)->patchJson("/api/admin/users/{$admin->id}/statut", ['suspendu' => true])->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertForbidden();

        $eleve = $this->user();
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$eleve->id}")->assertOk();
        $this->assertSoftDeleted($eleve);
        $this->assertTrue(JournalActivite::where('action', 'utilisateur.supprime')->exists());
    }

    public function test_export_csv_neutralise_les_formules(): void
    {
        $admin = $this->user('administrateur');
        $this->user('eleve', ['nom' => '=HYPERLINK("http://x")']);

        $res = $this->actingAs($admin)->get('/api/admin/users/export?role=eleve')->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(';=HYPERLINK', $csv);
    }

    // ─── Espace élève ────────────────────────────────────────

    public function test_parcours_eleve_consultation_favori_tableau_de_bord(): void
    {
        $eleve = $this->user();
        $ex = $this->exercice();
        $autre = $this->exercice(['titre' => 'Autre']);

        $this->actingAs($eleve)->getJson("/api/exercices/{$ex->id}")->assertOk();
        $this->assertTrue(Historique::where(['exercice_id' => $ex->id, 'action' => 'consulte'])->exists());

        $this->actingAs($eleve)->postJson("/api/eleve/favoris/{$ex->id}")->assertCreated()->assertJsonPath('favori', true);
        $this->actingAs($eleve)->postJson('/api/eleve/activite', ['exercice_id' => $ex->id, 'action' => 'correction_vue'])->assertCreated();

        $tdb = $this->actingAs($eleve)->getJson('/api/eleve/tableau-de-bord')->assertOk();
        $tdb->assertJsonPath('stats.exercices_consultes', 1)
            ->assertJsonPath('stats.corrections_vues', 1)
            ->assertJsonPath('stats.favoris', 1)
            ->assertJsonPath('stats.serie_jours', 1)
            ->assertJsonPath('onboarding_fait', false);
        $this->assertCount(14, $tdb->json('activite'));
        $this->assertSame([$autre->id], array_column($tdb->json('recommandations'), 'id'));

        $this->actingAs($eleve)->getJson('/api/eleve/favoris/ids')->assertJsonPath('ids', [$ex->id]);
        $this->actingAs($eleve)->postJson("/api/eleve/favoris/{$ex->id}")->assertOk()->assertJsonPath('favori', false);
        $this->actingAs($eleve)->getJson('/api/eleve/favoris')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_onboarding_eleve_et_acces_refuse_aux_autres_roles(): void
    {
        $eleve = $this->user();
        $niveau = Niveau::create(['libelle' => '3e', 'cycle' => 'Collège', 'ordre' => 2]);
        $matiere = Matiere::create(['nom' => 'SVT', 'code' => 'SVT']);

        $this->actingAs($eleve)->postJson('/api/eleve/onboarding', [
            'niveau_id' => $niveau->id, 'matieres' => [$matiere->id], 'objectif' => 'examen',
        ])->assertOk()->assertJsonPath('user.eleve.niveau_id', $niveau->id);

        $this->assertNotNull($eleve->fresh()->onboarding_termine_le);
        $this->assertSame([$matiere->id], $eleve->eleve->fresh()->matieres_preferees);

        $this->actingAs($this->user('professeur'))->getJson('/api/eleve/tableau-de-bord')->assertForbidden();
    }

    // ─── RGPD ────────────────────────────────────────────────

    public function test_export_et_suppression_du_compte(): void
    {
        $eleve = $this->user('eleve', ['email' => 'moi@t.sn']);

        $this->actingAs($eleve)->getJson('/api/auth/export')->assertOk()->assertJsonPath('compte.email', 'moi@t.sn');

        $this->actingAs($eleve)->deleteJson('/api/auth/compte', ['confirmation' => 'SUPPRIMER', 'password' => 'mauvais'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->actingAs($eleve)->deleteJson('/api/auth/compte', ['confirmation' => 'non', 'password' => 'secret123'])
            ->assertStatus(422);

        $this->actingAs($eleve)->deleteJson('/api/auth/compte', ['confirmation' => 'SUPPRIMER', 'password' => 'secret123'])->assertOk();

        $supprime = User::withTrashed()->find($eleve->id);
        $this->assertTrue($supprime->trashed());
        $this->assertStringEndsWith('@supprime.invalid', $supprime->email);
        $this->assertDatabaseMissing('users', ['email' => 'moi@t.sn']);
    }

    // ─── Paramètres & site public ────────────────────────────

    public function test_parametres_liste_blanche_publics_et_inscriptions_fermees(): void
    {
        $admin = $this->user('administrateur');

        $this->actingAs($admin)->putJson('/api/admin/parametres', ['parametres' => [
            'annonce_active' => '1', 'annonce_texte' => 'Bac blanc ce samedi', 'cle_pirate' => 'x', 'inscriptions_ouvertes' => '0',
        ]])->assertOk();

        $this->assertNull(AppParametre::where('cle', 'cle_pirate')->first());

        $this->actingAs($admin)->putJson('/api/admin/parametres', ['parametres' => ['annonce_lien' => 'javascript:alert(1)']])
            ->assertStatus(422);

        $publics = $this->getJson('/api/site/parametres')->assertOk();
        $publics->assertJsonPath('parametres.annonce_texte', 'Bac blanc ce samedi');
        $this->assertArrayNotHasKey('longueur_min_mdp', $publics->json('parametres'));

        $this->reinitialiserAuth();
        $this->postJson('/api/auth/register', [
            'nom' => 'N', 'prenom' => 'P', 'email' => 'new@t.sn', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ])->assertForbidden()->assertJsonPath('code', 'inscriptions_fermees');

        $this->assertTrue(JournalActivite::where('action', 'parametres.modifies')->exists());
    }

    // ─── Régressions de l'audit de sécurité ─────────────────

    public function test_liens_avec_antislash_ou_caracteres_de_controle_refuses(): void
    {
        $admin = $this->user('administrateur');

        foreach (['/\\evil.com', '\\\\evil.com', "/\tjavascript:alert(1)", 'https:///evil.com', "java\nscript:alert(1)"] as $lien) {
            $this->actingAs($admin)->postJson('/api/admin/pages', [
                'titre' => 'X', 'blocs' => [$this->bloc('cta', ['bouton_lien' => $lien])],
            ])->assertStatus(422);
        }

        $this->actingAs($admin)->postJson('/api/admin/pages', [
            'titre' => 'Ok', 'blocs' => [$this->bloc('cta', ['bouton_lien' => '/auth/signup', 'image' => 'https://cdn.tely.sn/a.png'])],
        ])->assertCreated();
    }

    public function test_parametres_a_valeurs_fermees_valides(): void
    {
        $admin = $this->user('administrateur');

        foreach ([
            ['annonce_style' => '<img src=x onerror=alert(1)>'],
            ['mode_maintenance' => 'oui'],
            ['longueur_min_mdp' => '3'],
            ['contact_email' => 'pas-un-email'],
        ] as $parametres) {
            $this->actingAs($admin)->putJson('/api/admin/parametres', ['parametres' => $parametres])->assertStatus(422);
        }

        $this->actingAs($admin)->putJson('/api/admin/parametres', ['parametres' => [
            'annonce_style' => 'promo', 'mode_maintenance' => '0', 'longueur_min_mdp' => '10', 'contact_email' => 'contact@tely.sn',
        ]])->assertOk();
    }

    public function test_connexion_limitee_contre_la_force_brute(): void
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->postJson('/api/auth/login', ['email' => 'inconnu@t.sn', 'password' => 'mauvais-mdp'])->status();
        }

        $this->assertContains(429, $codes);
    }

    public function test_stats_publiques_et_recherche_admin(): void
    {
        $ex = $this->exercice(['titre' => 'Équations du second degré']);
        $this->exercice(['est_actif' => false]);

        $this->getJson('/api/site/stats')->assertOk()->assertJsonPath('stats.exercices', 1);

        $admin = $this->user('administrateur');
        $this->actingAs($admin)->getJson('/api/admin/recherche?q=second')
            ->assertOk()->assertJsonPath('exercices.0.id', $ex->id);
        $this->actingAs($this->user())->getJson('/api/admin/recherche?q=second')->assertForbidden();

        $this->actingAs($admin)->getJson('/api/admin/journal')->assertOk()->assertJsonStructure(['journal' => ['data'], 'actions']);
    }
}
