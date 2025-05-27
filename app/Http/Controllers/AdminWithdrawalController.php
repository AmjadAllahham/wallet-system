<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use App\Services\HistoryService;
use Illuminate\Support\Facades\DB;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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

        Log::info('Entered approve() in AdminWithdrawalController with withdrawal ID: ' . $id);

        $withdrawal = Withdrawal::where('id', $id)->where('status', 'pending')->first();

        if (!$withdrawal) {
            Log::warning("Withdrawal not found or already processed. ID: $id");
            return response()->json([
                'message' => 'Withdrawal request not found or already processed.'
            ], 404);
        }

        // التحقق من رصيد المستخدم قبل الخصم
        $userWallet = $withdrawal->user->wallets()->where('currency_id', $withdrawal->currency_id)->first();
        if (!$userWallet || $userWallet->balance < $withdrawal->amount) {
            Log::warning("Insufficient balance for user ID {$withdrawal->user_id}, Withdrawal ID: $id");
            return response()->json([
                'message' => 'User has insufficient balance for this withdrawal.'
            ], 422);
        }

        try {
            DB::transaction(function () use ($withdrawal, $userWallet, $admin, $id) {
                Log::info("Processing withdrawal ID $id - deducting from user wallet");

                $balanceBefore = $userWallet->balance;

                // خصم الرصيد من المستخدم
                $userWallet->decrement('balance', $withdrawal->amount);
                $userWallet->refresh();

                $balanceAfter = $userWallet->balance;

                // إضافة الرصيد إلى محفظة الأدمن
                $adminWallet = Wallet::firstOrCreate([
                    'user_id'     => $admin->id,
                    'currency_id' => $withdrawal->currency_id,
                ], [
                    'balance' => 0,
                ]);
                $adminWallet->increment('balance', $withdrawal->amount);

                // تحديث حالة السحب
                $withdrawal->status = 'approved';
                $withdrawal->admin_id = $admin->id;
                $withdrawal->save();

                Log::info("Withdrawal ID $id approved successfully by admin ID {$admin->id}");

                // تسجيل العملية في جدول التاريخ
                HistoryService::log(
                    $withdrawal->user_id,
                    $userWallet->id,
                    'withdrawal',
                    $userWallet->currency->code,
                    $withdrawal->amount,
                    $balanceBefore,
                    $balanceAfter,
                    'Withdrawal approved by admin ID ' . $admin->id
                );

                // إرسال إشعار للمستخدم
                $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));
            });
        } catch (\Exception $e) {
            Log::error("Error approving withdrawal ID $id: " . $e->getMessage());
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
    
    //     public function reject(Request $request, $id)
    // {
    //     $admin = Auth::user();

    //     $withdrawal = Withdrawal::where('id', $id)->where('status', 'pending')->first();

    //     if (!$withdrawal) {
    //         return response()->json([
    //             'message' => 'Withdrawal request not found or already processed.'
    //         ], 404);
    //     }

    //     $request->validate([
    //         'reason' => 'nullable|string|max:255',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $withdrawal->status = 'rejected';
    //         $withdrawal->admin_id = $admin->id;
    //         $withdrawal->note = $request->input('reason'); // إضافة سبب الرفض كملاحظة
    //         $withdrawal->save();

    //         // إرسال إشعار للمستخدم
    //         $withdrawal->user->notify(new \App\Notifications\WithdrawalStatusNotification($withdrawal));

    //         DB::commit();

    //         return response()->json([
    //             'message'    => 'Withdrawal request rejected successfully.',
    //             'withdrawal' => [
    //                 'id'        => $withdrawal->id,
    //                 'status'    => $withdrawal->status,
    //                 'reason'    => $withdrawal->note,
    //                 'admin_id'  => $withdrawal->admin_id,
    //             ]
    //         ]);

    //     } catch (\Throwable $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'message' => 'Unknown error occurred while rejecting the request.'
    //         ], 500);
    //     }
    // }

}
