<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('source'); // preorder/qr/walkin
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('dining_spot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status')->default('baru'); // baru, diproses, siap, diantar, selesai
            $table->unsignedBigInteger('total');
            $table->string('payment_status')->default('unpaid');
            $table->boolean('bill_to_booking')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
