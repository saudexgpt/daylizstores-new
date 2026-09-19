<?php

namespace App\Http\Requests\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAccountRequest extends FormRequest
{
    public function authorize()
    {
        return true; // route middleware: permission:manage accounting
    }

    public function rules()
    {
        $account = $this->route('account'); // null when creating

        return [
            'code' => [$account ? 'sometimes' : 'required', 'string', 'regex:/^[A-Za-z0-9\-]{2,10}$/', Rule::unique('accounts', 'code')->ignore($account ? $account->id : null)],
            'name' => ['required', 'string', 'max:120'],
            'type' => [$account ? 'sometimes' : 'required', Rule::in(Account::TYPES)],
            'subtype' => ['nullable', 'string', 'max:30', 'regex:/^[a-z_]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages()
    {
        return ['code.regex' => 'The code may only use letters, numbers and dashes (2–10 characters).'];
    }
}
