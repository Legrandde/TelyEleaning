<?php

namespace Tests\Feature;

use App\Models\AbonneNewsletter;
use App\Models\AppParametre;
use App\Models\Correction;
use App\Models\Eleve;
use App\Models\Exercice;
use App\Models\Historique;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Professeur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Régressions des constats de l'audit de sécurité (novembre 2026).
 */
class AuditSecuriteRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function user(string $role = 'eleve', array $extra = []): User
    {
        $user = User::create(array_merge([
            'nom' => 'N', 'prenom' => 'P', 'email' => uniqid().'@t.sn', 'password' => 'secret123', 'role' => $role,
        ], $extra));
        if ($role === 'eleve') {
            Eleve::create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function exercice(bool $actif): Exercice
    {
        $prof = Professeur::create(['user_id' => $this->user('professeur')->id]);

        return Exercice::create([
            'titre' => 'Brouillon', 'enonce' => 'secret', 'type' => 'classique', 'difficulte' => 'moyen', 'est_actif' => $actif,
            'matiere_id' => Matiere::firstOrCreate(['code' => 'M'], ['nom' => 'M'])->id,
            'niveau_id' => Niveau::firstOrCreate(['libelle' => 'N'], ['cycle' => 'C', 'ordre' => 1])->id,
            'professeur_id' => $prof->id,
        ]);
    }

    private function oneTap(string $email): TestResponse
    {
        config(['services.google.client_id' => 'client']);
        Http::swap(new Factory);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'aud' => 'client', 'iss' => 'accounts.google.com', 'exp' => time() + 60,
            'email' => $email, 'email_verified' => 'true', 'sub' => 'g-'.md5($email),
        ])]);

        return $this->postJson('/api/auth/google/one-tap', ['credential' => 'jeton']);
    }

    public function test_exercice_desactive_ni_corrige_ni_historique(): void
    {
        $ex = $this->exercice(false);
        Correction::create(['exercice_id' => $ex->id, 'contenu' => 'réponse secrète']);
        $eleve = $this->user();

        $this->actingAs($eleve)->getJson("/api/exercices/{$ex->id}/correction")->assertNotFound();
        $this->actingAs($eleve)->postJson('/api/eleve/activite', ['exercice_id' => $ex->id, 'action' => 'consulte'])->assertStatus(422);
        $this->actingAs($eleve)->postJson('/api/historique', ['eleve_id' => $eleve->eleve->id, 'exercice_id' => $ex->id, 'action' => 'consulte'])->assertStatus(422);

        // Une trace antérieure (exercice désactivé après coup) n'est plus exposée
        Historique::create(['eleve_id' => $eleve->eleve->id, 'exercice_id' => $ex->id, 'action' => 'consulte', 'date_consultation' => now()]);
        $this->actingAs($eleve)->getJson('/api/eleve/historique')->assertJsonPath('total', 0);
        $this->actingAs($eleve)->getJson('/api/historique')->assertJsonPath('total', 0);
        $this->assertSame([], $this->actingAs($eleve)->getJson('/api/eleve/tableau-de-bord')->json('recents'));
    }

    public function test_google_ne_cree_pas_de_compte_si_inscriptions_fermees(): void
    {
        AppParametre::set('inscriptions_ouvertes', '0');
        $this->oneTap('nouveau@gmail.com')->assertForbidden()->assertJsonPath('code', 'inscriptions_fermees');
        $this->assertDatabaseMissing('users', ['email' => 'nouveau@gmail.com']);

        // Un compte existant peut toujours se connecter
        $this->user('eleve', ['email' => 'existant@gmail.com']);
        $this->oneTap('existant@gmail.com')->assertOk();
    }

    public function test_pre_detournement_de_compte_neutralise_par_google(): void
    {
        // L'attaquant crée un compte avec l'adresse de la victime et garde une session
        $compte = $this->user('eleve', ['email' => 'victime@gmail.com']);
        $jetonAttaquant = $compte->createToken('attaquant')->plainTextToken;

        $this->oneTap('victime@gmail.com')->assertOk();

        $compte->refresh();
        $this->assertNotNull($compte->email_verified_at);
        $this->assertFalse(Hash::check('secret123', $compte->password));
        $this->app['auth']->forgetGuards();
        $this->withToken($jetonAttaquant)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_redirection_google_transmet_le_nonce(): void
    {
        config(['services.google.client_id' => 'client', 'services.google.client_secret' => 's', 'services.google.redirect' => 'http://localhost/cb']);

        $location = $this->get('/api/auth/google?nonce=abcdefghijklmnop1234')->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('state=abcdefghijklmnop1234', $location);

        $location = $this->get('/api/auth/google?nonce=%3Cscript%3E')->headers->get('Location');
        $this->assertStringNotContainsString('script', $location);
    }

    public function test_inscription_fermee_ne_revele_pas_les_comptes(): void
    {
        $this->user('eleve', ['email' => 'connu@t.sn']);
        AppParametre::set('inscriptions_ouvertes', '0');

        $this->postJson('/api/auth/register', [
            'nom' => 'a', 'prenom' => 'b', 'email' => 'connu@t.sn', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ])->assertForbidden()->assertJsonPath('code', 'inscriptions_fermees');
    }

    public function test_longueur_minimale_du_mot_de_passe_appliquee(): void
    {
        AppParametre::set('longueur_min_mdp', '12');

        $this->postJson('/api/auth/register', [
            'nom' => 'a', 'prenom' => 'b', 'email' => 'court@t.sn', 'password' => 'Secret12!', 'password_confirmation' => 'Secret12!',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_changement_d_email_exige_le_mot_de_passe(): void
    {
        $user = $this->user('eleve', ['email' => 'ancien@t.sn']);

        $this->actingAs($user)->putJson('/api/auth/profile', ['nom' => 'N', 'prenom' => 'P', 'email' => 'nouveau@t.sn'])
            ->assertStatus(422)->assertJsonValidationErrors('current_password');
        $this->assertSame('ancien@t.sn', $user->fresh()->email);

        $this->actingAs($user)->putJson('/api/auth/profile', ['nom' => 'N', 'prenom' => 'P', 'email' => 'nouveau@t.sn', 'current_password' => 'secret123'])
            ->assertOk();
        $this->assertSame('nouveau@t.sn', $user->fresh()->email);

        // Modifier son nom sans changer d'e-mail ne demande rien
        $this->actingAs($user)->putJson('/api/auth/profile', ['nom' => 'Autre', 'prenom' => 'P', 'email' => 'nouveau@t.sn'])->assertOk();
    }

    public function test_suppression_admin_anonymise_et_libere_l_email(): void
    {
        $admin = $this->user('administrateur');
        $eleve = $this->user('eleve', ['email' => 'parti@t.sn']);
        AbonneNewsletter::create(['email' => 'parti@t.sn', 'cle' => 'k', 'demande_le' => now()]);
        Historique::create(['eleve_id' => $eleve->eleve->id, 'exercice_id' => $this->exercice(true)->id, 'action' => 'favori', 'date_consultation' => now()]);

        $this->actingAs($admin)->deleteJson("/api/admin/users/{$eleve->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'parti@t.sn']);
        $this->assertSame(0, AbonneNewsletter::where('email', 'parti@t.sn')->count());
        $this->assertSame(0, Historique::count());
        $this->assertFalse(JournalActivite::where('description', 'like', '%parti@t.sn%')->exists());

        // L'adresse est libérée : la personne peut se réinscrire
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/register', [
            'nom' => 'a', 'prenom' => 'b', 'email' => 'parti@t.sn', 'password' => 'Secret123!', 'password_confirmation' => 'Secret123!',
        ])->assertCreated();
    }

    public function test_maintenance_bloque_l_api_sauf_admin_et_connexion(): void
    {
        $admin = $this->user('administrateur', ['email' => 'admin@t.sn']);
        AppParametre::set('mode_maintenance', '1');
        Cache::forget('mode_maintenance');

        $this->getJson('/api/exercices')->assertStatus(503)->assertJsonPath('code', 'maintenance');
        $this->getJson('/api/site/parametres')->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'admin@t.sn', 'password' => 'secret123'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($admin->createToken('t')->plainTextToken)->getJson('/api/admin/pages')->assertOk();
    }
}
