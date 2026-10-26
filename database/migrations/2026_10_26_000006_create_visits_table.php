<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('chlorine_reading', 6, 2)->nullable();
            $table->decimal('ph_reading', 4, 2)->nullable();
            $table->json('services_performed')->nullable();
            $table->json('photos')->nullable();
            $table->text('technician_notes')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'property_id'], 'visits_tenant_property_fk')
                ->references(['tenant_id', 'id'])
                ->on('properties')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'scheduled_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
