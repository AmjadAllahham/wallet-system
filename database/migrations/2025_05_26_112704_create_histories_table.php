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
        Schema::create('histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade'); // الجديد
            $table->string('type'); // deposit, withdrawal, internal_transfer_in, internal_transfer_out, currency_exchange_in, currency_exchange_out
            $table->string('currency')->nullable(); // SYP, USD, TRY
            $table->decimal('amount', 20, 2); // موجب أو سالب حسب العملية
            $table->decimal('balance_before', 20, 2)->nullable();
            $table->decimal('balance_after', 20, 2)->nullable();
            $table->string('note')->nullable(); // ملاحظات أو وصف مختصر للعملية
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histories');
    }
};
