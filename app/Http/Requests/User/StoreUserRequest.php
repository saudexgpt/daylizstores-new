<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Route-level `permission:manage user` middleware already gates this
     * endpoint; UserController::store() also re-checks in code (defense in
     * depth) and additionally restricts who may hand out the admin role.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'roles' => ['sometimes', 'array'],
            'password' => ['required', 'string', 'min:8', 'max:190'],
            'confirmPassword' => ['same:password'],
        ];
    }
}
