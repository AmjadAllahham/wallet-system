<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Admin\CheckAdminService;

class CheckAdminController extends Controller
{
    protected $adminService;

    public function __construct(CheckAdminService $adminService) // ✅ يجب أن يكون public
    {
        $this->adminService = $adminService;
    }

    public function UpdateUser($id, Request $request)
    {
        return response()->json($this->adminService->UpdateUser($id, $request->all()));
    }
}
