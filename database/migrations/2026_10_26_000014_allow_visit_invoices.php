<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('visits', 'visits_tenant_id_id_unique')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->unique(['tenant_id', 'id'], 'visits_tenant_id_id_unique');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign($this->subscriptionForeignKey());
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
            $table->unsignedBigInteger('visit_id')->nullable()->unique();
            $table->foreign(['tenant_id', 'subscription_id'], 'invoices_tenant_subscription_fk')
                ->references(['tenant_id', 'id'])
                ->on('subscriptions')
                ->cascadeOnDelete();
            $table->foreign(['tenant_id', 'visit_id'], 'invoices_tenant_visit_fk')
                ->references(['tenant_id', 'id'])
                ->on('visits')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign($this->subscriptionForeignKey());
            $table->dropForeign($this->visitForeignKey());
            $table->dropUnique(['visit_id']);
            $table->dropColumn('visit_id');
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
            $table->foreign(['tenant_id', 'subscription_id'], 'invoices_tenant_subscription_fk')
                ->references(['tenant_id', 'id'])
                ->on('subscriptions')
                ->cascadeOnDelete();
        });

        if (Schema::hasIndex('visits', 'visits_tenant_id_id_unique')) {
            Schema::table('visits', function (Blueprint $table) {
                $table->dropUnique('visits_tenant_id_id_unique');
            });
        }
    }

    private function subscriptionForeignKey(): array|string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? ['tenant_id', 'subscription_id']
            : 'invoices_tenant_subscription_fk';
    }

    private function visitForeignKey(): array|string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? ['tenant_id', 'visit_id']
            : 'invoices_tenant_visit_fk';
    }
};
