<?php

namespace App\Traits;

trait ApiResponser
{
    protected function successResponse($message, $data = [], $code = 200)
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'status_code' => $code,
        ];
    }

    protected function errorResponse($message, $code)
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => null,
            'status_code' => $code,
        ];
    }
}
