<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numerology_readings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->index();
            $table->string('user_name')->nullable();
            $table->string('surname')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('type')->default('free');
            $table->text('result')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numerology_readings');
    }
};
