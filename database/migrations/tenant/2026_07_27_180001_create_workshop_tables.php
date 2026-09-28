<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workshop / device registry: a customer's devices, repair tickets, their status
 * history and attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('serial_number')->nullable();
            $table->boolean('serial_number_unavailable')->default(false);
            $table->string('brand');
            $table->string('model');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Serial numbers are unique per tenant, but only for real (non-null) serials
        // on live rows — repeated "unavailable" devices are never blocked.
        DB::statement('CREATE UNIQUE INDEX customer_devices_serial_unique
            ON customer_devices (serial_number)
            WHERE serial_number IS NOT NULL AND deleted_at IS NULL');

        Schema::create('workshop_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('number')->unique();
            $table->foreignId('device_id')->constrained('customer_devices')->cascadeOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('issue_description');
            $table->text('repair_notes')->nullable();
            $table->string('status')->default('in_progress')->index();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workshop_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workshop_ticket_id')->constrained('workshop_tickets')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('workshop_attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('workshop_ticket_id')->constrained('workshop_tickets')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind')->default('repair');
            $table->string('disk');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('processed')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_attachments');
        Schema::dropIfExists('workshop_status_history');
        Schema::dropIfExists('workshop_tickets');
        Schema::dropIfExists('customer_devices');
    }
};
