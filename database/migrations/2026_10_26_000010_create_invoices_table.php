<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subscription_id');
            $table->string('invoice_number');
            $table->string('status')->default('due');
            $table->string('description');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->char('currency', 3);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->unique(['subscription_id', 'period_start', 'period_end']);
            $table->foreign(['tenant_id', 'subscription_id'], 'invoices_tenant_subscription_fk')
                ->references(['tenant_id', 'id'])
                ->on('subscriptions')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
