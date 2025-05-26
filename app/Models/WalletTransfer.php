<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WalletTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'currency_id',
        'amount',
        'note',
        'receipt_number' // <-- الإضافة الجديدة
    ];

    // علاقات (اختياري ولكن مفيد)

    public function sender()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
