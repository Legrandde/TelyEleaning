<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\CategorieBlog;
use App\Models\User;
use Database\Seeders\BlogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['nom' => 'A', 'prenom' => 'D', 'email' => uniqid().'@t.sn', 'password' => 'secret123', 'role' => 'administrateur']);
    }

    private function bloc(string $type, array $props): array
    {
        return ['id' => uniqid(), 'type' => $type, 'props' => $props];
    }

    public function test_cycle_de_vie_article_brouillon_programme_publie(): void
    {
        $admin = $this->admin();
        $cat = CategorieBlog::create(['nom' => 'Examens', 'slug' => 'examens']);

        $res = $this->actingAs($admin)->postJson('/api/admin/articles', [
            'titre' => 'Calendrier du Bac 2027',
            'categorie_blog_id' => $cat->id,
            'tags' => ['Bac', ' bac ', 'Calendrier'],
            'blocs' => [$this->bloc('texte', ['contenu' => str_repeat('mot ', 450)])],
        ])->assertCreated();

        $id = $res->json('article.id');
        $this->assertSame('calendrier-du-bac-2027', $res->json('article.slug'));
        $this->assertSame(['bac', 'calendrier'], $res->json('article.tags'));
        $this->assertSame(3, $res->json('article.temps_lecture'));

        $this->getJson('/api/blog/articles/calendrier-du-bac-2027')->assertNotFound();

        // Programmé dans le futur : toujours invisible
        $this->actingAs($admin)->putJson("/api/admin/articles/{$id}", ['statut' => 'publie', 'publie_le' => now()->addDay()->toIso8601String()])
            ->assertOk()->assertJsonPath('article.est_programme', true);
        $this->getJson('/api/blog/articles/calendrier-du-bac-2027')->assertNotFound();
        $this->actingAs($admin)->getJson('/api/admin/articles?statut=programme')->assertJsonPath('counts.programme', 1);

        Article::find($id)->update(['publie_le' => now()->subMinute()]);
        $this->getJson('/api/blog/articles/calendrier-du-bac-2027')->assertOk()->assertJsonPath('article.categorie.slug', 'examens');
        $this->assertSame(1, Article::find($id)->vues);

        $this->getJson('/api/blog/articles?categorie=examens')->assertJsonPath('articles.total', 1);
        $this->getJson('/api/blog/articles?tag=bac')->assertJsonPath('articles.total', 1);
        $this->getJson('/api/blog/articles?categorie=autre')->assertJsonPath('articles.total', 0);
        $this->getJson('/api/blog/categories')->assertJsonPath('categories.0.articles_count', 1);
    }

    public function test_article_refuse_liens_dangereux_et_non_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/admin/articles', [
            'titre' => 'X', 'image_couverture' => 'javascript:alert(1)', 'blocs' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('image_couverture');

        $this->actingAs($admin)->postJson('/api/admin/articles', [
            'titre' => 'X', 'blocs' => [$this->bloc('cta', ['bouton_lien' => 'data:text/html,x'])],
        ])->assertStatus(422)->assertJsonValidationErrors('blocs.0.props');

        $eleve = User::create(['nom' => 'E', 'prenom' => 'L', 'email' => 'e@t.sn', 'password' => 'secret123', 'role' => 'eleve']);
        $this->actingAs($eleve)->postJson('/api/admin/articles', ['titre' => 'X', 'blocs' => []])->assertForbidden();
    }

    public function test_a_la_une_unique_rss_et_sitemap(): void
    {
        $this->seed(BlogSeeder::class);
        $this->seed(BlogSeeder::class);
        $this->assertSame(4, CategorieBlog::count());

        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/admin/articles', [
            'titre' => 'Nouveauté <script>', 'blocs' => [], 'statut' => 'publie', 'mis_en_avant' => true,
        ])->json('article.id');

        $this->assertSame(1, Article::where('mis_en_avant', true)->count());
        $this->getJson('/api/blog/articles')->assertJsonPath('a_la_une.id', $id);

        $rss = $this->get('/api/blog/rss.xml')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('Nouveauté &lt;script&gt;', $rss);
        $this->assertNotFalse(simplexml_load_string($rss));

        $sitemap = $this->get('/api/site/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('/blog/5-habitudes-pour-reviser-efficacement', $sitemap);
        $this->assertNotFalse(simplexml_load_string($sitemap));
    }

    public function test_suppression_categorie_et_article(): void
    {
        $admin = $this->admin();
        $cat = $this->actingAs($admin)->postJson('/api/admin/blog-categories', ['nom' => 'Orientation'])
            ->assertCreated()->assertJsonPath('categorie.slug', 'orientation');

        $id = $this->actingAs($admin)->postJson('/api/admin/articles', [
            'titre' => 'Choisir sa série', 'blocs' => [], 'categorie_blog_id' => $cat->json('categorie.id'),
        ])->json('article.id');

        $this->actingAs($admin)->deleteJson('/api/admin/blog-categories/'.$cat->json('categorie.id'))->assertOk();
        $this->assertNull(Article::find($id)->categorie_blog_id);

        $this->actingAs($admin)->postJson("/api/admin/articles/{$id}/dupliquer")->assertCreated()->assertJsonPath('article.statut', 'brouillon');
        $this->actingAs($admin)->deleteJson("/api/admin/articles/{$id}")->assertOk();
        $this->actingAs($admin)->postJson('/api/admin/articles', ['titre' => 'Choisir sa série', 'blocs' => []])->assertCreated();
    }
}
