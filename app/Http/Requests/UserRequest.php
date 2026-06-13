<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdministratorSistem();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $endpointName = $this->route()->getActionMethod();
        $user = json_decode($this->route()->parameter("user"));


        return match ($endpointName) {
            
            "store" =>
            [
                'full_name' => ['required', 'string', 'min:3', 'max:150'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                'role_id' => ['required', 'uuid', 'exists:roles,id'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            "update" => [
                'full_name' => ['sometimes', 'string', 'min:3', 'max:150'],
                'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
                'role_id' => ['sometimes', 'uuid', 'exists:roles,id'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            default => []
        };




    }
}
