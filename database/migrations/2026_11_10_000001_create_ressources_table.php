<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ressources', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('type')->default('cours'); // cours, resume, fiche, td, autre
            $table->string('fichier_path');
            $table->string('fichier_nom')->nullable();
            $table->string('format')->nullable(); // pdf, docx, etc.
            $table->unsignedBigInteger('taille')->nullable(); // en octets
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('professeur_id')->nullable()->constrained('professeurs')->nullOnDelete();
            $table->boolean('est_public')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ressources');
    }
};
