<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WalletTransferNotification extends Notification
{
    use Queueable;

    public function __construct(public string $type, public $user, public $amount, public $currency) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type'     => $this->type, // sent or received
            'user'     => $this->user->getFullNameAttribute(),
            'amount'   => $this->amount,
            'currency' => $this->currency,
        ];
    }
}
