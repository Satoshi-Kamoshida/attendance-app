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
        Schema::create('break_time_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_correction_request_id')->constrained(indexName: 'bcr_attendance_correction_request_fk')->onDelete('cascade');
            $table->foreignId('break_time_id')->nullable()->constrained()->onDelete('cascade');
            $table->time('new_break_in')->nullable();
            $table->time('new_break_out')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('break_time_correction_requests');
    }
};
