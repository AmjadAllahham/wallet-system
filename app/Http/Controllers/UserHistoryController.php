<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;

class UserHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = History::where('user_id', $user->id);

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

        return response()->json([
            'history' => $query->orderBy('created_at', 'desc')->paginate(20)
        ]);
    }
}

