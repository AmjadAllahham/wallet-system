<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\UserService;
use Illuminate\Http\Request;
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
        return $this->userService->index();
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
        
        return $this->userService->update( $request->all(),$id);
    }

    public function destroy($id)
    {
        return $this->userService->destroy($id);
    }
}
