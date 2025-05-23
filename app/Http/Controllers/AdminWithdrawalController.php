<?php

namespace App\Http\Controllers;

use App\Models\Withdrawal;
use App\Models\Wallet;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminWithdrawalController extends Controller
{
    protected $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    
    public function index()
    {
        $withdrawals = Withdrawal::with(['user', 'currency'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        $data = $withdrawals->map(function ($withdrawal) {
            return [
                'id'             => $withdrawal->id,
                'user_id'        => $withdrawal->user->id,
                'user_name'      => $withdrawal->user->name,
                'amount'         => $withdrawal->amount,
                'currency'       => $withdrawal->currency->code,
                'receipt_number' => $withdrawal->receipt_number,
                'status'         => $withdrawal->status,
                'created_at'     => $withdrawal->created_at,
                'full_name'      => $withdrawal->full_name,
                'phone'          => $withdrawal->phone,
                'location'       => $withdrawal->location,
                'note'           => $withdrawal->note,
            ];
        });

        return response()->json($data);
    }

    
    public function approve(Request $request, $id)
    {
        $admin = Auth::user();

        $withdrawal = Withdrawal::where('id', $id)->where('status', 'pending')->first();

        if (!$withdrawal) {
            return response()->json([
                'message' => 'Withdrawal request not found or already processed.'
            ], 404);
        }

        // التحقق من رصيد المستخدم قبل الخصم
        $userWallet = $withdrawal->user->wallets()->where('currency_id', $withdrawal->currency_id)->first();
        if (!$userWallet || $userWallet->balance < $withdrawal->amount) {
            return response()->json([
                'message' => 'User has insufficient balance for this withdrawal.'
            ], 422);
        }

        // تنفيذ عملية السحب ونقل المبلغ إلى محفظة الأدمن (نقطة البيع)
        try {
            DB::transaction(function () use ($withdrawal, $userWallet, $admin) {
                // خصم الرصيد من المستخدم
                $userWallet->decrement('balance', $withdrawal->amount);

                // إضافة الرصيد إلى محفظة الأدمن (كـ "نقطة بيع")
                $adminWallet = Wallet::firstOrCreate([
                    'user_id'     => $admin->id,
                    'currency_id' => $withdrawal->currency_id,
                ], [
                    'balance' => 0,
                ]);
                $adminWallet->increment('balance', $withdrawal->amount);

                // تحديث حالة طلب السحب
                $withdrawal->status = 'approved';
                $withdrawal->admin_id = $admin->id;
                $withdrawal->save();

                // إرسال إشعار للمستخدم
                $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while approving the withdrawal.',
                'error'   => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => 'Withdrawal request approved successfully.',
            'withdrawal' => $withdrawal,
        ]);
    }

    
    public function reject(Request $request, $id)
    {
        $admin = Auth::user();

        $withdrawal = Withdrawal::where('id', $id)->where('status', 'pending')->first();

        if (!$withdrawal) {
            return response()->json([
                'message' => 'Withdrawal request not found or already processed.'
            ], 404);
        }

        // تحديث حالة طلب السحب إلى مرفوض
        $withdrawal->status = 'rejected';
        $withdrawal->admin_id = $admin->id;
        $withdrawal->save();

        // إرسال إشعار للمستخدم
        $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));

        return response()->json([
            'message' => 'Withdrawal request rejected successfully.',
            'withdrawal' => $withdrawal,
        ]);
    }
}
