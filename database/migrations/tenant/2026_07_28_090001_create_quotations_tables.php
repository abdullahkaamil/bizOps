<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations with snapshot-preserving lines. All money is stored in integer
 * minor units (e.g. cents) — never floats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->date('issue_date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 3);
            // Money in minor units.
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('discount_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('line_type')->default('item');
            $table->string('internal_name_snapshot')->nullable();
            $table->string('customer_alias');
            $table->text('description')->nullable();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit')->default('unit');
            $table->bigInteger('unit_cost_snapshot')->nullable();
            $table->bigInteger('unit_price');
            $table->string('discount_type')->nullable();
            $table->decimal('discount_value', 14, 3)->nullable();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->bigInteger('line_subtotal')->default(0);
            $table->bigInteger('line_discount')->default(0);
            $table->bigInteger('line_tax')->default(0);
            $table->bigInteger('line_total')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quotation_status_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_status_history');
        Schema::dropIfExists('quotation_lines');
        Schema::dropIfExists('quotations');
    }
};
