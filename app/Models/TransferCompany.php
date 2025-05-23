<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferCompany extends Model
{
    use HasFactory;
    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }
}
