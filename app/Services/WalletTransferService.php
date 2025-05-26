<?php

namespace App\Services;

use Exception;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use Illuminate\Support\Str;
use App\Models\WalletTransfer;
use App\Models\History;  // تم إضافة موديل History
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\WalletTransferNotification;

class WalletTransferService
{
    public function crosswalletexchange(array $data, User $sender)
    {
        try {
            // التحقق من صحة البيانات
            if (!isset($data['account_number'], $data['currency'], $data['amount'])) {
                throw new \Exception('Missing required fields.');
            }

            $amount = floatval($data['amount']);
            if ($amount <= 0) {
                throw new \Exception('Invalid amount.');
            }

            $currency = Currency::where('code', $data['currency'])->first();
            if (!$currency) {
                throw new \Exception('Invalid currency.');
            }

            $receiver = User::where('account_number', $data['account_number'])->first();
            if (!$receiver) {
                throw new \Exception('Receiver not found.');
            }

            if ($receiver->id === $sender->id) {
                throw new \Exception('You cannot transfer to your own account.');
            }

            // التحقق من رصيد المرسل
            $senderWallet = Wallet::where('user_id', $sender->id)
                ->where('currency_id', $currency->id)->first();

            if (!$senderWallet || $senderWallet->balance < $amount) {
                throw new \Exception('Insufficient balance.');
            }

            // تنفيذ التحويل داخل معاملة
            DB::beginTransaction();

            // سجل الرصيد قبل التغيير للمرسل
            $balanceBeforeSender = $senderWallet->balance;

            // خصم من المرسل
            $senderWallet->decrement('balance', $amount);

            // سجل الرصيد بعد التغيير للمرسل
            $balanceAfterSender = $senderWallet->balance;

            // إضافة إلى المستلم
            $receiverWallet = Wallet::firstOrCreate([
                'user_id' => $receiver->id,
                'currency_id' => $currency->id,
            ], [
                'balance' => 0
            ]);

            // سجل الرصيد قبل التغيير للمستلم
            $balanceBeforeReceiver = $receiverWallet->balance;

            $receiverWallet->increment('balance', $amount);

            // سجل الرصيد بعد التغيير للمستلم
            $balanceAfterReceiver = $receiverWallet->balance;

            // توليد رقم إيصال فريد
            $receiptNumber = $this->generateUniqueReceiptNumber();

            // تسجيل التحويل
            WalletTransfer::create([
                'from_user_id'   => $sender->id,
                'to_user_id'     => $receiver->id,
                'currency_id'    => $currency->id,
                'amount'         => $amount,
                'note'           => $data['note'] ?? null,
                'receipt_number' => $receiptNumber,
            ]);

            // تسجيل العملية في سجل الهيستوري للطرفين مع wallet_id والرصيد قبل وبعد التغيير
            History::create([
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'type' => 'transfer_sent',
                'currency' => $currency->code,
                'amount' => -$amount, // بالسالب لأنه خصم من رصيد المرسل
                'balance_before' => $balanceBeforeSender,
                'balance_after' => $balanceAfterSender,
                'note' => 'Transfer to account: ' . $receiver->account_number,
            ]);

            History::create([
                'user_id' => $receiver->id,
                'wallet_id' => $receiverWallet->id,
                'type' => 'transfer_received',
                'currency' => $currency->code,
                'amount' => $amount, // بالموجب لأنه إضافة إلى رصيد المستلم
                'balance_before' => $balanceBeforeReceiver,
                'balance_after' => $balanceAfterReceiver,
                'note' => 'Received from account: ' . $sender->account_number,
            ]);

            // إرسال إشعارات
            Notification::send($sender, new WalletTransferNotification('sent', $receiver, $amount, $currency->code));
            Notification::send($receiver, new WalletTransferNotification('received', $sender, $amount, $currency->code));

            DB::commit();

            return [
                'message' => 'Transfer completed successfully.',
                'receipt_number' => $receiptNumber
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Wallet transfer error: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage() ?: 'Unknown error'], 422);
        }
    }

    private function generateUniqueReceiptNumber(): string
    {
        do {
            $receipt = strtoupper(Str::random(10));
        } while (WalletTransfer::where('receipt_number', $receipt)->exists());

        return $receipt;
    }
}
