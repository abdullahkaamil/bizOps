<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central SaaS-administration additions: per-tenant feature flags, staged
 * deletion metadata, an audited support-access log, and system announcements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->jsonb('features')->nullable()->after('plan_code');
            $table->timestamp('deletion_requested_at')->nullable()->after('subscription_ends_at');
            $table->timestamp('purge_after')->nullable()->after('deletion_requested_at');
        });

        // Audited support (impersonation) access. Central database.
        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('tenant_id');
            $table->foreignId('admin_id')->nullable();
            $table->string('admin_email')->nullable();
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        // System announcements shown to tenant users. Central database.
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('level')->default('info');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('support_sessions');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['features', 'deletion_requested_at', 'purge_after']);
        });
    }
};
