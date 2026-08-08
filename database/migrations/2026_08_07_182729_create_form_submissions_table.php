<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->uuid('submission_uuid')->unique();
            $table->json('data'); // all form field values
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta_data')->nullable(); // referrer, location, etc.
            $table->boolean('is_spam')->default(false);
            $table->timestamp('submitted_at');
            $table->timestamps();

           // Indexes for submission queries
            $table->index(['form_id', 'submitted_at']);
            $table->index(['form_id', 'is_spam']);
            $table->index('submission_uuid');
            $table->index('ip_address');
            $table->index('submitted_at');
            
            // For search functionality
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText('data');
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_submissions');
    }
};