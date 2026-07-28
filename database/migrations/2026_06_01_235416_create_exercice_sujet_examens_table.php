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
        Schema::create('exercice_sujet_examen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('sujet_examen_id')->constrained('sujets_examen')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre')->default(1); // ordre dans le sujet
            $table->unique(['exercice_id', 'sujet_examen_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercice_sujet_examen');
    }
};
