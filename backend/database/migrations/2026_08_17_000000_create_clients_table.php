<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('document_type', [
                Client::DOCUMENT_TYPE_CPF,
                Client::DOCUMENT_TYPE_CNPJ,
            ]);
            $table->string('document', 14)->unique();
            $table->string('address')->nullable();
            $table->string('contact')->nullable();
            $table->enum('status', [
                Client::STATUS_ACTIVE,
                Client::STATUS_INACTIVE,
            ])->default(Client::STATUS_ACTIVE);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};
