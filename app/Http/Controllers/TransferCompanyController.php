<?php

namespace App\Http\Controllers;
use App\Models\TransferCompany;


class TransferCompanyController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => TransferCompany::select('id', 'name')->get()
        ]);
    }
}
