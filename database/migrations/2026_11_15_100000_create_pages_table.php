<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('slug')->unique();
            $table->string('meta_description', 300)->nullable();
            $table->string('image_og')->nullable();
            $table->json('blocs');
            $table->enum('statut', ['brouillon', 'publie'])->default('brouillon');
            $table->boolean('est_accueil')->default(false);
            $table->boolean('dans_menu')->default(false);
            $table->boolean('dans_footer')->default(false);
            $table->timestamp('publie_le')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modifie_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'est_accueil']);
        });

        Schema::create('medias', function (Blueprint $table) {
            $table->id();
            $table->string('chemin');
            $table->string('nom');
            $table->string('mime', 100);
            $table->unsignedInteger('taille');
            $table->string('alt')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medias');
        Schema::dropIfExists('pages');
    }
};
