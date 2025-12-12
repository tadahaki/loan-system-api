<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UserBorrow extends Authenticatable
{
    use HasFactory;

    protected $table = 'userborrows';

    protected $fillable = [
        'firstName',
        'lastName',
        'gender',
        'age',
        'email',
        'address',
        'username',
        'password'
    ];

    protected $hidden = [
        'password'
    ];
}
