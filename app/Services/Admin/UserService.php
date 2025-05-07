<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\AccountService;
use Illuminate\Support\Facades\Validator;

class UserService
{
    use ApiResponser;

    public function index()
    {
        $users = User::paginate(10);
        if ($users->isEmpty()) {
            return $this->errorResponse('No users found', null, 404);
        }
        return $this->successResponse('Users found successfully', $users, 200);
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }
        return $this->successResponse('User found successfully', $user, 200);
    }

    public function countUsers()
    {
        $count = User::count();
        return $this->successResponse('Users counted successfully', ['count' => $count], 200);
    }

    public function create($data)
    {
        $validator = Validator::make($data, [
            'first_name' => 'required|string|min:3|max:25',
            'last_name' => 'required|string|min:3|max:25',
            'email' => 'required|email|min:3|max:25|string|unique:users,email',
            'password' => 'required|string|min:6|max:32|confirmed',
            'answer_security'=>'required|string|min:3|max:32',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        // Generate unique account number
        $accountNumber = AccountService::generateUniqueAccountNumber();

        // Create user with the new account_number field
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'account_number' => $accountNumber, // New field added
            'answer_security' => $data['answer_security'] ?? null, // Optional field
        ]);

        return $this->successResponse('User created successfully', $user, 201);
    }

    public function update($data, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }

        $validator = Validator::make($data, [
            'first_name'       => 'sometimes|string|min:3|max:25',
            'last_name'        => 'sometimes|string|min:3|max:25',
            'password'         => 'sometimes|string|min:6|max:32|confirmed',
            'birth_date'       => 'sometimes|date',
            'answer_security'  => 'sometimes|string|min:3|max:25',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors(), 400);
        }

        $user->update([
            'first_name'      => $data['first_name'] ?? $user->first_name,
            'last_name'       => $data['last_name'] ?? $user->last_name,
            'birth_date'      => $data['birth_date'] ?? $user->birth_date,
            'password'        => isset($data['password']) ? Hash::make($data['password']) : $user->password,
            'answer_security' => $data['answer_security'] ?? $user->answer_security,
        ]);

        return $this->successResponse('User updated successfully', $user, 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return $this->errorResponse('User not found', null, 404);
        }

        $user->delete();
        return $this->successResponse('User deleted successfully', null, 200);
    }
}
