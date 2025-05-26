<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;

class AdminHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = History::with('user');

        // فلترة حسب المستخدم
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // فلترة حسب النوع
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // فلترة حسب العملة
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        // فلترة حسب التاريخ من
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        // فلترة حسب التاريخ إلى
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $limit = min((int) $request->get('limit', 20), 100);

        return response()->json([
            'history' => $query->orderBy('created_at', 'desc')->paginate($limit)->through(function ($item) {
                return [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'username' => $item->user->name ?? 'Unknown',
                    'type' => $item->type,
                    'currency' => $item->currency,
                    'amount' => $item->amount,
                    'balance_before' => $item->balance_before,
                    'balance_after' => $item->balance_after,
                    'note' => $item->note,
                    'created_at' => $item->created_at->toDateTimeString(),
                ];
            })
        ]);
    }
}
