<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry of generated documents (PDFs, exports). Files are stored privately on
 * a tenant-prefixed path; this table records where, what, and how they map back
 * to their source record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('document_type')->index();
            $table->string('related_type');
            $table->unsignedBigInteger('related_id');
            $table->string('number')->nullable();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->default('application/pdf');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
