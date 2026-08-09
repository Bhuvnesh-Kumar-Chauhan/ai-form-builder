<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('form_analytics', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('session_id');
            $table->index(['form_id', 'occurred_at', 'event_type']);
        });
    }

    public function down()
    {
        Schema::table('form_analytics', function (Blueprint $table) {
            $table->dropIndex(['form_id', 'occurred_at', 'event_type']);
            $table->dropColumn('ip_address');
        });
    }
};
