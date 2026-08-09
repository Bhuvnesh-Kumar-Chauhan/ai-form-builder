<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('settings')->nullable(); // theme, layout, etc.
            $table->json('validation_rules')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_multi_step')->default(false);
            $table->integer('submission_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_published']);
            $table->index(['user_id', 'created_at']);
            $table->index('slug');
            $table->index('uuid');
            $table->index('is_published');
            $table->index('published_at');
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('forms');
    }
};
