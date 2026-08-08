<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('form_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->string('session_id');
            $table->string('event_type'); // view, start, complete, abandon, field_interaction
            $table->json('event_data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['form_id', 'event_type']);
            $table->index(['form_id', 'session_id']);
            $table->index(['form_id', 'occurred_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_analytics');
    }
};