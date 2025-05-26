<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyExchange extends Model
{
    protected $fillable = [
        'user_id',
        'from_currency',
        'to_currency',
        'amount',
        'converted_amount',
        'rate',
    ];
}
