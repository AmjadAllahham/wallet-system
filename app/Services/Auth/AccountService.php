<?php
namespace App\Services\Auth;

use App\Models\User;

class AccountService
{
    public static function generateUniqueAccountNumber()
    {
        do {
            $number = 'ACC-' . mt_rand(1000000000, 9999999999);
        } while (User::where('account_number', $number)->exists());

        return $number;
    }
}

?>
