<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('taro_readings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->index();
            $table->string('user_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('type')->nullable()->comment("e.g. 'Таро на день', 'Таро на любовь'");
            $table->text('question')->nullable();
            $table->integer('cards_count')->default(3);
            $table->text('result')->nullable()->comment('текст ответа от AI');
            $table->json('meta')->nullable()->comment('при необходимости: карты, сырой ответ и т.д.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taro_readings');
    }
};
