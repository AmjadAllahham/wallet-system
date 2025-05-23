<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'currency_id',
        'amount',
        'receipt_number',
        'status',
        'full_name',
        'phone',
        'location',
        'note',
        'transfer_company_id',
        'admin_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function transferCompany()
    {
        return $this->belongsTo(TransferCompany::class);
    }
}
