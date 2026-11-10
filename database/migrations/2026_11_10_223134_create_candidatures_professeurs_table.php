<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidatures_professeurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->string('email', 150)->index();
            $table->string('telephone', 50)->nullable();
            $table->string('specialite', 150);
            $table->string('etablissement', 150)->nullable();
            $table->string('diplome', 150)->nullable();
            $table->integer('experience_annees')->default(0);
            $table->text('message')->nullable();
            $table->string('cv_path', 255)->nullable();
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motif_rejet')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures_professeurs');
    }
};
