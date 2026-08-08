<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_form_generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode')->default('create'); // create | edit
            $table->text('prompt');
            $table->string('status')->default('queued'); // queued | processing | succeeded | failed
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->longText('raw_response')->nullable();
            $table->string('model')->nullable();
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['form_id', 'status']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_form_generation_jobs');
    }
};
