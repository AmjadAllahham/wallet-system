<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Withdrawal;

class WithdrawalStatusNotification extends Notification
{
    use Queueable;

    protected $withdrawal;

    public function __construct(Withdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }

    public function via($notifiable)
    {
        return ['database']; // لاحقًا يمكن إضافة mail أو broadcast
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => 'تم تنفيذ عملية سحب بمبلغ ' . $this->withdrawal->amount . ' ' . $this->withdrawal->currency->code,
            'withdrawal_id' => $this->withdrawal->id,
        ];
    }
}
