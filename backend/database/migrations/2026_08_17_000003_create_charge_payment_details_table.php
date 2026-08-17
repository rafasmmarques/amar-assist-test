<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('charge_payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('boleto_barcode')->nullable();
            $table->string('pix_key')->nullable();
            $table->string('pix_transaction_id')->nullable();
            $table->string('card_reference')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('charge_payment_details');
    }
};
