<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Captured when a customer accepts/rejects via the public signed link.
            $table->string('decided_by_name')->nullable()->after('rejected_at');
            $table->string('decided_ip', 45)->nullable()->after('decided_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['decided_by_name', 'decided_ip']);
        });
    }
};
