<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->string('label');
            $table->string('value');
            $table->integer('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->json('extra_data')->nullable(); // for additional attributes
            $table->timestamps();

            $table->index(['form_field_id', 'order']);
            $table->index('is_default');
        });
    }

    public function down()
    {
        Schema::dropIfExists('field_options');
    }
};