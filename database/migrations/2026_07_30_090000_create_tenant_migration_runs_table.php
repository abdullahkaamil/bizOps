<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant migration state for controlled, batched deployments. One row per
     * (tenant, release): the batched migrator upserts it so a release can be
     * resumed and failures can be retried without re-touching healthy tenants.
     */
    public function up(): void
    {
        Schema::create('tenant_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('release');
            $table->string('status')->default('pending'); // pending|running|completed|failed
            $table->unsignedInteger('batch')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('migrations_applied')->default(0);
            $table->text('output')->nullable();
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'release']);
            $table->index(['release', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_migration_runs');
    }
};
