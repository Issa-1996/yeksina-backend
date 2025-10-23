<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birth_date');
            $table->string('address', 255);
            $table->string('cni_photo_path', 255);
            $table->string('vehicle_type', 100)->nullable();
            $table->string('license_plate', 50)->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_online_at')->nullable();
            $table->decimal('current_balance', 10, 2)->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->float('average_rating', 2, 1)->default(0);
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};