<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnUpdate();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_per_day', 12, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('photo')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('price_per_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
