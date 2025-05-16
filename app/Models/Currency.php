<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['name', 'code'];

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }
}
