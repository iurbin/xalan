<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id');
            $table->string('name')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('US');
            $table->string('pool_type')->nullable();
            $table->text('access_code')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'customer_id'], 'properties_tenant_customer_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'postal_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
