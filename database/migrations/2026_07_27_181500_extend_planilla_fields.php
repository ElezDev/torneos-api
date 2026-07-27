<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_sheets', function (Blueprint $table) {
            $table->string('delegate_name', 150)->nullable()->after('status');
            $table->text('observations')->nullable()->after('delegate_name');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->string('referee_name', 150)->nullable()->after('notes');
        });

        Schema::table('match_events', function (Blueprint $table) {
            $table->foreignId('related_player_id')
                ->nullable()
                ->after('player_id')
                ->constrained('players')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('match_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_player_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('referee_name');
        });

        Schema::table('match_sheets', function (Blueprint $table) {
            $table->dropColumn(['delegate_name', 'observations']);
        });
    }
};
