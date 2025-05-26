<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\Withdrawal;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\TransferCompany;
use App\Models\ManualWithdrawal;
use App\Services\HistoryService;
use Illuminate\Support\Facades\DB;
use App\Services\WithdrawalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;


class WithdrawalController extends Controller
{
    protected $withdrawalService;

    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    public function requestWithdrawal(Request $request)
    {
        $validated = $request->validate([
            'currency_code'         => 'required|string|exists:currencies,code',
            'amount'                => 'required|numeric|min:0.01',
            'password'              => 'required|string',
            'full_name'             => 'required|string|max:255',
            'phone'                 => 'required|string|max:20',
            'location'              => 'required|string|max:255',
            'note'                  => 'nullable|string|max:500',
            'transfer_company_name' => 'required|string|exists:transfer_companies,name',
        ]);

        $user = Auth::user();
        $currency = Currency::where('code', $validated['currency_code'])->first();
        $transferCompany = TransferCompany::where('name', $validated['transfer_company_name'])->first();

        try {
            $withdrawal = $this->withdrawalService->createWithdrawalRequest($user, [
                'currency_id'         => $currency->id,
                'amount'              => $validated['amount'],
                'password'            => $validated['password'],
                'full_name'           => $validated['full_name'],
                'phone'               => $validated['phone'],
                'location'            => $validated['location'],
                'note'                => $validated['note'] ?? null,
                'transfer_company_id' => $transferCompany->id,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Withdrawal request failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        $withdrawal->load(['currency', 'transferCompany']);
        /** @var User $user */
        $wallets = $user->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'currency' => $wallet->currency->code,
                'balance'  => number_format($wallet->balance, 2),
            ];
        });

        return response()->json([
            'message'    => 'Withdrawal request created successfully and pending approval.',
            'withdrawal' => [
                'id'               => $withdrawal->id,
                'amount'           => $withdrawal->amount,
                'currency'         => $withdrawal->currency->code,
                'transfer_company' => $withdrawal->transferCompany?->name,
                'receipt_number'   => $withdrawal->receipt_number,
                'status'           => $withdrawal->status,
                'created_at'       => $withdrawal->created_at,
                'full_name'        => $withdrawal->full_name,
                'phone'            => $withdrawal->phone,
                'location'         => $withdrawal->location,
                'note'             => $withdrawal->note,
            ],
            'wallets' => $wallets,
        ]);
    }

    public function myWithdrawals()
    {
        /** @var User $user */
        $user = Auth::user();
        $withdrawals = $user->withdrawals()->with(['currency', 'transferCompany'])->latest()->get();

        return response()->json(
            $withdrawals->map(function ($withdrawal) {
                return [
                    'id'               => $withdrawal->id,
                    'amount'           => $withdrawal->amount,
                    'currency'         => $withdrawal->currency->code,
                    'transfer_company' => $withdrawal->transferCompany?->name,
                    'status'           => $withdrawal->status,
                    'receipt_number'   => $withdrawal->receipt_number,
                    'created_at'       => $withdrawal->created_at,
                    'full_name'        => $withdrawal->full_name,
                    'phone'            => $withdrawal->phone,
                    'location'         => $withdrawal->location,
                    'note'             => $withdrawal->note,
                ];
            })
        );
    }

    public function approve(Request $request, $withdrawalId)
    {
        Log::info('Entered WithdrawalController@approve');

        $admin = Auth::user();

        try {
            $withdrawal = $this->withdrawalService->getWithdrawalById($withdrawalId);
            $result = $this->withdrawalService->approveWithdrawal($withdrawal, $admin);
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Approval failed.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    public function reject(Request $request, $withdrawalId)
    {
        $admin = Auth::user();

        try {
            $withdrawal = $this->withdrawalService->getWithdrawalById($withdrawalId);
            $result = $this->withdrawalService->rejectWithdrawal($withdrawal, $admin);
            return response()->json($result);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Rejection failed.',
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    // public function manualWithdrawal(Request $request)
    // {
    //     $admin = auth()->user();

    //     // التحقق من صلاحية المستخدم (يجب أن يكون أدمن)
    //     if (!$admin || !$admin->is_admin) {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     // التحقق من صحة البيانات المدخلة
    //     $validator = Validator::make($request->all(), [
    //         'account_number' => 'required|string|exists:users,account_number',
    //         'currency'       => 'required|string|in:USD,SYP,TRY',
    //         'amount'         => 'required|numeric|min:0.01',
    //         'note'           => 'nullable|string',
    //     ], [
    //         'account_number.required' => 'Account number is required',
    //         'account_number.exists'   => 'User with this account number does not exist',
    //         'currency.required'       => 'Currency is required',
    //         'currency.in'             => 'Currency must be one of: USD, SYP, TRY',
    //         'amount.required'         => 'Amount is required',
    //         'amount.numeric'          => 'Amount must be a number',
    //         'amount.min'              => 'Amount must be greater than zero',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => $validator->errors()->first()], 422);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         // جلب المستخدم المستهدف بناءً على رقم الحساب
    //         $targetUser = User::where('account_number', $request->account_number)->first();

    //         // جلب العملة حسب الرمز
    //         $currency = Currency::where('code', strtoupper($request->currency))->first();

    //         // جلب المحفظة الخاصة بالمستخدم للعملة المحددة مع قفل السجل لتجنب مشاكل التزامن
    //         $wallet = Wallet::where('user_id', $targetUser->id)
    //             ->where('currency_id', $currency->id)
    //             ->lockForUpdate()
    //             ->first();

    //         if (!$wallet) {
    //             DB::rollBack();
    //             return response()->json(['error' => 'Wallet not found'], 404);
    //         }

    //         // التحقق من وجود رصيد كافٍ في المحفظة
    //         if ($wallet->balance < $request->amount) {
    //             DB::rollBack();
    //             return response()->json(['error' => 'Insufficient balance'], 422);
    //         }

    //         $balanceBefore = $wallet->balance;

    //         // خصم المبلغ من المحفظة
    //         $wallet->balance -= $request->amount;
    //         $wallet->save();

    //         $balanceAfter = $wallet->balance;

    //         // إنشاء رقم إيصال فريد
    //         $receiptNumber = 'REC-' . strtoupper(Str::random(10));

    //         // تسجيل السحب في جدول السحوبات
    //         $withdrawal = Withdrawal::create([
    //             'user_id'        => $targetUser->id,
    //             'admin_id'       => $admin->id,
    //             'currency_id'    => $currency->id,
    //             'amount'         => $request->amount,
    //             'receipt_number' => $receiptNumber,
    //             'status'         => 'approved',
    //             'type'           => 'manual',
    //             'full_name'      => $targetUser->getFullNameAttribute(),
    //             'phone'          => $targetUser->phone ?? '',
    //             'location'       => $targetUser->address ?? '',
    //             'note'           => $request->note ?? null,
    //         ]);

    //                 DB::commit();

    //         // رد ناجح مع بيانات العملية
    //         return response()->json([
    //             'message' => 'Manual withdrawal completed successfully',
    //             'receipt_number' => $receiptNumber,
    //             'account_number' => $request->account_number,
    //             'currency' => strtoupper($request->currency),
    //             'amount' => $request->amount,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Manual withdrawal error: ' . $e->getMessage());

    //         return response()->json(['error' => 'Unknown error occurred'], 500);
    //     }
    // }

    public function manualWithdrawal(Request $request, WithdrawalService $withdrawalService)
    {
        $result = $withdrawalService->manualWithdrawal($request->all());

        if ($result['error']) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'receipt_number' => $result['receipt_number'] ?? null,
        ], 200);
    }
}
