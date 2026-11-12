<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercices', function (Blueprint $table) {
            $table->index(['est_actif', 'matiere_id', 'niveau_id'], 'idx_exercices_actif_matiere_niveau');
            $table->index(['est_actif', 'difficulte'], 'idx_exercices_actif_difficulte');
            $table->index(['est_actif', 'type'], 'idx_exercices_actif_type');
            $table->index(['professeur_id', 'created_at'], 'idx_exercices_prof_created');
        });

        Schema::table('sujets_examen', function (Blueprint $table) {
            $table->index(['matiere_id', 'niveau_id', 'annee'], 'idx_sujets_matiere_niveau_annee');
            $table->index(['annee', 'session'], 'idx_sujets_annee_session');
            $table->index('type', 'idx_sujets_type');
        });

        Schema::table('ressources', function (Blueprint $table) {
            $table->index(['matiere_id', 'niveau_id', 'type'], 'idx_ressources_mat_niv_type');
            $table->index(['professeur_id', 'est_public'], 'idx_ressources_prof_public');
        });

        Schema::table('niveaux', function (Blueprint $table) {
            $table->index('ordre', 'idx_niveaux_ordre');
            $table->index('cycle', 'idx_niveaux_cycle');
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->index('nom', 'idx_matieres_nom');
        });
    }

    public function down(): void
    {
        Schema::table('exercices', function (Blueprint $table) {
            $table->dropIndex('idx_exercices_actif_matiere_niveau');
            $table->dropIndex('idx_exercices_actif_difficulte');
            $table->dropIndex('idx_exercices_actif_type');
            $table->dropIndex('idx_exercices_prof_created');
        });

        Schema::table('sujets_examen', function (Blueprint $table) {
            $table->dropIndex('idx_sujets_matiere_niveau_annee');
            $table->dropIndex('idx_sujets_annee_session');
            $table->dropIndex('idx_sujets_type');
        });

        Schema::table('ressources', function (Blueprint $table) {
            $table->dropIndex('idx_ressources_mat_niv_type');
            $table->dropIndex('idx_ressources_prof_public');
        });

        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropIndex('idx_niveaux_ordre');
            $table->dropIndex('idx_niveaux_cycle');
        });

        Schema::table('matieres', function (Blueprint $table) {
            $table->dropIndex('idx_matieres_nom');
        });
    }
};
