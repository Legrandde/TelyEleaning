<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_blog', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('slug', 120)->unique();
            $table->string('description', 300)->nullable();
            $table->string('couleur', 20)->default('amber');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 200);
            $table->string('slug', 220)->unique();
            $table->string('extrait', 400)->nullable();
            $table->string('image_couverture', 500)->nullable();
            $table->json('blocs');
            $table->json('tags')->nullable();
            $table->enum('statut', ['brouillon', 'publie'])->default('brouillon');
            $table->timestamp('publie_le')->nullable();
            $table->boolean('mis_en_avant')->default(false);
            $table->unsignedSmallInteger('temps_lecture')->default(1);
            $table->unsignedInteger('vues')->default(0);
            $table->string('meta_description', 300)->nullable();
            $table->foreignId('categorie_blog_id')->nullable()->constrained('categories_blog')->nullOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'publie_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('categories_blog');
    }
};
