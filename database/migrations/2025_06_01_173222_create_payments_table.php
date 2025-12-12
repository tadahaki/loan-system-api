<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans');
            $table->foreignId('user_id')->constrained('userborrows');
            $table->integer('installment_number');
            $table->decimal('amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->string('payment_method');
            $table->string('screenshot')->nullable(); // This is the correct column name
            $table->string('status')->default('Pending Verification');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
