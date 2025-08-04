<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('datos_personales', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->unique();
        $table->string('apellidos')->nullable();
        $table->string('alias')->nullable();
        $table->string('cedula')->nullable();
        $table->string('telefono')->nullable();
        $table->date('fecha_nacimiento')->nullable();
        $table->string('foto')->nullable();
        $table->timestamps();

        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    });
    }
};
