<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'amount',
        'purpose',
        'term',
        'repayment_frequency',
        'status',
        'interest_rate',
        'interest_type',
        'total_interest',
        'total_payable',
        'payment_per_period',
        'number_of_payments'
    ];

    public function user()
    {
        return $this->belongsTo(UserBorrow::class);
    }
}
