<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Validator;

class CheckAdminService
{
    use ApiResponser;

    public function updateUser($id, $data)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json($this->errorResponse('User not found', 404));
        }

        $validator = Validator::make($data, [
            'is_admin' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json($this->errorResponse($validator->errors(), 400));
        }

        // التحقق مما إذا كانت القيمة لم تتغير
        if ($user->is_admin == $data['is_admin']) {
            return response()->json($this->errorResponse('User is already in the requested role', 422));
        }

        // تنفيذ التحديث
        $user->is_admin = $data['is_admin'];
        $user->save();

        return response()->json($this->successResponse('User updated successfully', $user));
    }
}
