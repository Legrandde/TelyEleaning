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
        Schema::create('historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->enum('action', ['consulte', 'correction_vue', 'favori'])->default('consulte');
            $table->timestamp('date_consultation')->useCurrent();
            $table->timestamps();
 
            // Index pour accélérer les requêtes par élève
            $table->index(['eleve_id', 'date_consultation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historiques');
    }
};
