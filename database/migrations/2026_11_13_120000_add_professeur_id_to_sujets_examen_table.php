<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auteur d'un sujet d'examen : permet de limiter la modification à l'auteur (ou à un administrateur).
     * Les sujets existants restent sans auteur et ne sont donc modifiables que par un administrateur.
     */
    public function up(): void
    {
        if (Schema::hasTable('sujets_examen') && !Schema::hasColumn('sujets_examen', 'professeur_id')) {
            Schema::table('sujets_examen', function (Blueprint $table) {
                $table->foreignId('professeur_id')->nullable()->after('niveau_id')
                    ->constrained('professeurs')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sujets_examen') && Schema::hasColumn('sujets_examen', 'professeur_id')) {
            Schema::table('sujets_examen', function (Blueprint $table) {
                $table->dropConstrainedForeignId('professeur_id');
            });
        }
    }
};
