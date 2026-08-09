<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->string('field_key');
            $table->string('label');
            $table->string('type'); // text, email, number, select, checkbox, radio, textarea, file, date, etc.
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('validation')->nullable(); // rules, messages
            $table->json('settings')->nullable(); // min, max, step, etc.
            $table->unsignedInteger('step')->default(1);
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->string('default_value')->nullable();
            $table->string('css_class')->nullable();
            $table->json('conditional_logic')->nullable(); // show/hide based on other fields
            $table->timestamps();

            $table->unique(['form_id', 'field_key']);
            $table->index(['form_id', 'order']);
            $table->index('type');
            $table->index('is_required');
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_fields');
    }
};
