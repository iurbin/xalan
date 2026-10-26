<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id');
            $table->index(['tenant_id', 'customer_id', 'status'], 'subscriptions_customer_status_index');
            $table->foreign(['tenant_id', 'customer_id'], 'subscriptions_tenant_customer_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id');
        });

        DB::table('invoices')
            ->whereNotNull('visit_id')
            ->update([
                'customer_id' => DB::raw('(SELECT properties.customer_id FROM visits JOIN properties ON properties.id = visits.property_id WHERE visits.id = invoices.visit_id)'),
            ]);

        DB::table('invoices')
            ->whereNull('customer_id')
            ->whereNotNull('subscription_id')
            ->update([
                'customer_id' => DB::raw('(SELECT subscriptions.customer_id FROM subscriptions WHERE subscriptions.id = invoices.subscription_id)'),
            ]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['tenant_id', 'customer_id', 'issued_at'], 'invoices_customer_issued_index');
            $table->foreign(['tenant_id', 'customer_id'], 'invoices_tenant_customer_fk')
                ->references(['tenant_id', 'id'])
                ->on('customers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign($this->customerInvoiceForeignKey());
            $table->dropIndex('invoices_customer_issued_index');
            $table->dropColumn('customer_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign($this->customerSubscriptionForeignKey());
            $table->dropIndex('subscriptions_customer_status_index');
            $table->dropColumn('customer_id');
        });
    }

    private function customerInvoiceForeignKey(): array|string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? ['tenant_id', 'customer_id']
            : 'invoices_tenant_customer_fk';
    }

    private function customerSubscriptionForeignKey(): array|string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? ['tenant_id', 'customer_id']
            : 'subscriptions_tenant_customer_fk';
    }
};
