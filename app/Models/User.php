<?php

namespace App\Models;

use App\Models\Wallet;
use App\Models\Currency;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'birth_date',
        'answer_security',
        'account_number',
        'email_verified_at',
        'job',
        'phone',
        'address'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }


    // إزالة currencies() لأنها تكرر wallets()

    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            $currencies = Currency::whereIn('code', ['SYP', 'USD', 'TRY'])->get();

            foreach ($currencies as $currency) {
                Wallet::create([
                    'user_id'     => $user->id,
                    'currency_id' => $currency->id,
                    'balance'     => 1000.00
                ]);
            }
        });
    }
}
