<?php

use App\Models\Charge;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id')->constrained()->restrictOnDelete();
            $table->date('billing_period');
            $table->enum('payment_method', [
                Charge::PAYMENT_METHOD_BOLETO,
                Charge::PAYMENT_METHOD_CARD,
                Charge::PAYMENT_METHOD_PIX,
            ]);
            $table->decimal('original_amount', 12, 2);
            $table->decimal('fixed_fee_amount', 12, 2);
            $table->date('due_date');
            $table->enum('status', [
                Charge::STATUS_OPEN,
                Charge::STATUS_PAID,
            ])->default(Charge::STATUS_OPEN);
            $table->decimal('paid_original_amount', 12, 2)->nullable();
            $table->decimal('paid_fixed_fee_amount', 12, 2)->nullable();
            $table->decimal('paid_late_interest_amount', 12, 2)->nullable();
            $table->decimal('paid_total_amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamps();

            $table->unique(['contract_id', 'billing_period']);
            $table->index(['status', 'due_date']);
            $table->index(['payment_method', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('charges');
    }
};
