<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStampCorrectionBreaksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stamp_correction_breaks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stamp_correction_request_id')
                ->constrained('stamp_correction_requests')
                ->cascadeOnDelete();

            $table->dateTime('requested_break_start_at')->nullable();
            $table->dateTime('requested_break_end_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stamp_correction_breaks');
    }
}
