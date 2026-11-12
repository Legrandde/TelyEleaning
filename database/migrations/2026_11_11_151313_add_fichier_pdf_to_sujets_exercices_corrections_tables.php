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
        if (Schema::hasTable('sujets_examen') && !Schema::hasColumn('sujets_examen', 'fichier_pdf')) {
            Schema::table('sujets_examen', function (Blueprint $table) {
                $table->string('fichier_pdf')->nullable()->after('ennonce');
            });
        }

        if (Schema::hasTable('exercices') && !Schema::hasColumn('exercices', 'fichier_pdf')) {
            Schema::table('exercices', function (Blueprint $table) {
                $table->string('fichier_pdf')->nullable()->after('image');
            });
        }

        if (Schema::hasTable('corrections') && !Schema::hasColumn('corrections', 'fichier_pdf')) {
            Schema::table('corrections', function (Blueprint $table) {
                $table->string('fichier_pdf')->nullable()->after('explication');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('sujets_examen', 'fichier_pdf')) {
            Schema::table('sujets_examen', function (Blueprint $table) {
                $table->dropColumn('fichier_pdf');
            });
        }

        if (Schema::hasColumn('exercices', 'fichier_pdf')) {
            Schema::table('exercices', function (Blueprint $table) {
                $table->dropColumn('fichier_pdf');
            });
        }

        if (Schema::hasColumn('corrections', 'fichier_pdf')) {
            Schema::table('corrections', function (Blueprint $table) {
                $table->dropColumn('fichier_pdf');
            });
        }
    }
};
