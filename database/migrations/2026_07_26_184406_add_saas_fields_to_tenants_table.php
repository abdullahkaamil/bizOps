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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('status')->default('active')->after('slug');
            $table->string('plan_code')->nullable()->after('status');
            $table->timestamp('trial_ends_at')->nullable()->after('license_expires_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('trial_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['slug', 'status', 'plan_code', 'trial_ends_at', 'subscription_ends_at']);
        });
    }
};
