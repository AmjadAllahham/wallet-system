<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $table = 'inwalletexchange_rates';

    protected $fillable = [
        'from_currency',
        'to_currency',
        'amount'
    ];
}
