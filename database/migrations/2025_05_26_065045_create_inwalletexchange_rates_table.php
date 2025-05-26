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
        Schema::create('inwalletexchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency');   // USD
            $table->string('to_currency'); // SYP, TRY...
            $table->decimal('amount', 15, 6);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inwalletexchange_rates');
    }
};
