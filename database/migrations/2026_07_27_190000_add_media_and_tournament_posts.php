<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('slug');
            $table->string('login_image_path')->nullable()->after('logo_path');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('is_public');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('referee_name');
        });

        Schema::create('tournament_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('match_id')->nullable()->constrained('matches')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('caption')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['tournament_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_posts');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('banner_path');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('banner_path');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'login_image_path']);
        });
    }
};
