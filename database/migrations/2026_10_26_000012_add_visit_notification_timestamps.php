<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->timestamp('schedule_notification_sent_at')->nullable();
            $table->timestamp('reminder_sent_at')->nullable();
            $table->index(['status', 'scheduled_at', 'reminder_sent_at'], 'visits_reminder_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_reminder_lookup_idx');
            $table->dropColumn(['schedule_notification_sent_at', 'reminder_sent_at']);
        });
    }
};
