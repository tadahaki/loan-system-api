<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
    'loan_id',
    'user_id',
    'installment_number',
    'amount',
    'penalty_amount',
    'payment_method',
    'screenshot', // Make sure this is included
    'status'
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    public function user()
    {
        return $this->belongsTo(UserBorrow::class);
    }
}
