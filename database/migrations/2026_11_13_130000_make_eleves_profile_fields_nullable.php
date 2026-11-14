<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le profil élève est créé vide à l'inscription (formulaire ou Google) puis complété
     * depuis la page profil : matricule, niveau et classe doivent donc être facultatifs.
     * L'index unique sur le matricule est conservé (plusieurs NULL restent autorisés).
     */
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('matricule')->nullable()->change();
            $table->unsignedBigInteger('niveau_id')->nullable()->change();
            $table->string('classe')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->string('matricule')->nullable(false)->change();
            $table->unsignedBigInteger('niveau_id')->nullable(false)->change();
            $table->string('classe')->nullable(false)->change();
        });
    }
};
