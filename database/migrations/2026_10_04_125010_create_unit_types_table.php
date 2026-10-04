<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('capacity');
            $table->json('facilities')->nullable();
            $table->unsignedBigInteger('base_price_weekday');
            $table->unsignedBigInteger('base_price_weekend');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_types');
    }
};
