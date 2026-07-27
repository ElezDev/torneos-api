<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $players = DB::table('players')
            ->where(function ($q) {
                $q->whereNull('document_id')->orWhere('document_id', '');
            })
            ->get(['id', 'tenant_id']);

        foreach ($players as $player) {
            DB::table('players')->where('id', $player->id)->update([
                'document_id' => sprintf('TMP-%d-%d', $player->tenant_id, $player->id),
            ]);
        }

        Schema::table('players', function (Blueprint $table) {
            $table->unique(['tenant_id', 'document_id'], 'players_tenant_document_unique');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropUnique('players_tenant_document_unique');
        });
    }
};
