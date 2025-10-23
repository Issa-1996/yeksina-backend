<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('status', 50)->default('pending');
            $table->text('pickup_address');
            $table->decimal('pickup_lat', 10, 8);
            $table->decimal('pickup_lng', 11, 8);
            $table->text('destination_address');
            $table->decimal('destination_lat', 10, 8);
            $table->decimal('destination_lng', 11, 8);
            $table->string('recipient_name', 100);
            $table->string('recipient_phone', 20);
            $table->string('package_type', 50);
            $table->decimal('estimated_weight', 5, 2)->nullable();
            $table->decimal('price', 8, 2);
            $table->integer('security_code');
            $table->boolean('security_code_validated')->default(false);
            $table->text('client_notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picking_up_at')->nullable();
            $table->timestamp('on_route_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};