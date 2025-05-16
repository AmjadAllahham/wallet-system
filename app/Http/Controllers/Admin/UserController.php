<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\Admin\UserService;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index()
    {
        // نعيد قائمة المستخدمين مع المحافظ الخاصة بهم
        return $this->userService->walletBalances();
    }

    public function show($id)
    {
        return $this->userService->show($id);
    }

    public function countUsers()
    {
        return $this->userService->countUsers();
    }

    public function create(Request $request)
    {
        return $this->userService->create($request->all());
    }

    public function update(Request $request, $id)
    {

        return $this->userService->update($request->all(), $id);
    }

    public function destroy($id)
    {
        return $this->userService->destroy($id);
    }
    public function getAdmins()
    {
        $admins = User::where('is_admin', true)->get();

        return response()->json([
            'success' => true,
            'message' => 'List of admin users',
            'data' => $admins
        ]);
    }
    // 👇 أضف الدالة الجديدة هنا
    public function walletBalances()
    {
        return $this->userService->walletBalances();
    }
 
}
