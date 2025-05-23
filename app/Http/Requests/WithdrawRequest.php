<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    // app/Http/Requests/WithdrawRequest.php

    public function rules()
    {
        return [
            'currency' => 'required|in:SYP,USD,TRY',
            'amount'   => 'required|numeric|min:1',
            'password' => 'required|string',
        ];
    }
}
