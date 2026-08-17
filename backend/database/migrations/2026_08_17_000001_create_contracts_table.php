<?php

use App\Models\Contract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->enum('person_type', [
                Contract::PERSON_TYPE_PF,
                Contract::PERSON_TYPE_PJ,
            ]);
            $table->unsignedTinyInteger('billing_cycle_day');
            $table->enum('status', [
                Contract::STATUS_ACTIVE,
                Contract::STATUS_ENDED,
            ])->default(Contract::STATUS_ACTIVE);
            $table->date('started_at');
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('contracts');
    }
};
