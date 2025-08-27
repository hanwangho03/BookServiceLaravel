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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('customer_user_id');
            $table->unsignedInteger('technician_user_id');
            $table->unsignedInteger('service_id');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->enum('status', ['da_xac_nhan', 'da_hoan_thanh', 'da_huy', 'cho_xac_nhan'])->default('cho_xac_nhan');
            $table->timestamps();

            // Khóa ngoại
            $table->foreign('customer_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('technician_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};