<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            // Who may move a card into / out of this step: internal, external or
            // both. Defaults keep every existing column fully open (both sides),
            // so behaviour is unchanged until a column is deliberately restricted.
            $table->string('move_in')->default('both')->after('category');
            $table->string('move_out')->default('both')->after('move_in');
        });
    }

    public function down(): void
    {
        Schema::table('board_columns', function (Blueprint $table) {
            $table->dropColumn(['move_in', 'move_out']);
        });
    }
};
