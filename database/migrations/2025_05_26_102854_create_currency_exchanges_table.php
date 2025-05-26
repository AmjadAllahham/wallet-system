<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('from_currency');
            $table->string('to_currency');
            $table->decimal('amount', 16, 2); // المبلغ الأصلي
            $table->decimal('converted_amount', 16, 2); // المبلغ بعد التحويل
            $table->decimal('rate', 16, 6); // سعر التحويل المستخدم
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
