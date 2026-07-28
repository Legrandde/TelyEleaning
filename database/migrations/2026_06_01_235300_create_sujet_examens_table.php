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
        Schema::create('sujets_examen', function (Blueprint $table) {
            $table->id();
            $table->string('titre');              // ex: "BAC S2 — Maths 2024"
            $table->unsignedSmallInteger('annee');
            $table->enum('session', ['juin', 'septembre', 'janvier'])->default('juin');
            $table->enum('type', ['bac', 'brevet', 'composition', 'autre'])->default('bac');
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->foreignId('niveau_id')->constrained('niveaux');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sujet_examen');
    }
};
