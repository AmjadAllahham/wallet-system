<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('currency_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('admin_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->foreignId('transfer_company_id')
                ->nullable()   // أصبح اختياري
                ->constrained()
                ->onDelete('set null');

            $table->decimal('amount', 15, 2);

            $table->string('receipt_number', 50)->unique();

            // لو أردت استخدام enum للحالة:
            // $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // لكن إذا لا تريد، يمكن تركه string كما هو
            $table->string('status')->default('pending');

            $table->string('full_name', 150);

            $table->string('phone', 20);

            $table->string('location', 255);

            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
