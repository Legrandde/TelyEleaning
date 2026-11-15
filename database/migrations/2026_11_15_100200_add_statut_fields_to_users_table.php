<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspendu_le')->nullable()->after('role');
            $table->string('motif_suspension', 255)->nullable()->after('suspendu_le');
            $table->timestamp('derniere_connexion_le')->nullable()->after('motif_suspension');
            $table->timestamp('onboarding_termine_le')->nullable()->after('derniere_connexion_le');
        });

        Schema::table('eleves', function (Blueprint $table) {
            $table->json('matieres_preferees')->nullable();
            $table->string('objectif', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropColumn(['matieres_preferees', 'objectif']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspendu_le', 'motif_suspension', 'derniere_connexion_le', 'onboarding_termine_le']);
        });
    }
};
