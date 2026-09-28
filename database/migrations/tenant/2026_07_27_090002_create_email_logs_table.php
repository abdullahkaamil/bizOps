<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An audit row for every outbound email a notification produces, so delivery and
 * failures are observable per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('notification_type');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->string('status')->index();
            $table->string('provider_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
