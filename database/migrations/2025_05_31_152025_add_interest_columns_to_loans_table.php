<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInterestColumnsToLoansTable extends Migration
{
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->nullable()->after('repayment_frequency');
            $table->string('interest_type', 20)->nullable()->after('interest_rate');
            $table->decimal('total_interest', 12, 2)->nullable()->after('interest_type');
            $table->decimal('total_payable', 12, 2)->nullable()->after('total_interest');
            $table->decimal('payment_per_period', 12, 2)->nullable()->after('total_payable');
        });
    }

    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn([
                'interest_rate',
                'interest_type',
                'total_interest',
                'total_payable',
                'payment_per_period'
            ]);
        });
    }
}
