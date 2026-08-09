<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('version');
            $table->string('note')->nullable();
            $table->json('schema');
            $table->timestamps();

            $table->unique(['form_id', 'version']);
            $table->index(['form_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('form_versions');
    }
};
