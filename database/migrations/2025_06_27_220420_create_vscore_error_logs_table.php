<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVSCoreErrorLogsTable extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('vscore_error_logs');
        Schema::create('vscore_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->longText('message');
            $table->text('file');
            $table->integer('line');
            $table->text('context')->nullable();
            $table->integer('occurrences')->default(1);
            $table->timestamp('last_occurrence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vscore_error_logs');
    }
};
