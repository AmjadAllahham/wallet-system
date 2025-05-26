<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CurrencyExchangeService;
use Illuminate\Support\Facades\Validator;

class CurrencyExchangeController extends Controller
{
    protected $currencyExchangeService;

    public function __construct(CurrencyExchangeService $currencyExchangeService)
    {
        $this->currencyExchangeService = $currencyExchangeService;
    }

    public function convert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string|exists:currencies,code',
            'to_currency'   => 'required|string|exists:currencies,code|different:from_currency',
            'amount'        => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'details' => $validator->errors()
            ], 422);
        }

        $result = $this->currencyExchangeService->convert(
            $request->user(),
            $request->from_currency,
            $request->to_currency,
            $request->amount
        );

        // إذا وُجد خطأ في النتيجة
        if (isset($result['error']) && $result['error'] === true) {
            return response()->json([
                'error' => true,
                'message' => $result['message'] ?? 'Unknown error'
            ], 422);
        }
        

        // نجاح: أزلنا حقل error لجعل التنسيق أنظف
        return response()->json([
            'message' => $result['message'],
            'converted_amount' => $result['converted_amount'],
            'rate' => $result['rate']
        ], 200);
    }
}
