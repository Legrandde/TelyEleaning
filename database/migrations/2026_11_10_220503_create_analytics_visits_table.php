<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('path', 255)->default('/');
            $table->string('action', 100)->default('page_view')->index();
            $table->json('metadata')->nullable();
            $table->string('country', 100)->default('Sénégal')->index();
            $table->string('country_code', 10)->default('SN')->index();
            $table->string('city', 100)->nullable();
            $table->string('os', 50)->default('Windows')->index();
            $table->string('os_version', 50)->nullable();
            $table->string('browser', 50)->default('Chrome')->index();
            $table->string('browser_version', 50)->nullable();
            $table->string('device_type', 30)->default('desktop')->index();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_visits');
    }
};
