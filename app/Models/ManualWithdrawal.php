<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualWithdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'amount',
        'receipt_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
