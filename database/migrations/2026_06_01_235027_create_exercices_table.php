<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercices', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->longText('enonce');
            $table->enum('type', ['classique', 'qcm', 'probleme', 'sujet_examen'])->default('classique');
            $table->enum('difficulte', ['facile', 'moyen', 'difficile'])->default('moyen');
            $table->boolean('est_actif')->default(true);
 
            // Clés étrangères
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->foreignId('niveau_id')->constrained('niveaux');
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('professeur_id')->constrained('professeurs');
 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercices');
    }
};
