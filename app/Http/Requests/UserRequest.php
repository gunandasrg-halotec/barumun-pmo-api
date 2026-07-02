<?php

namespace App\Http\Requests;

use App\Enums\RoleName;
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
            "index" => [
                "filter" => ["sometimes", "array"],
                "filter.role" => [
                    'sometimes',
                    'string',
                    Rule::in(array_column(RoleName::cases(), 'name'))

                ]
            ],

            "store" =>
            [
                'full_name' => ['required', 'string', 'min:3', 'max:150'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
                "phone" => ['sometimes', 'nullable', 'string', 'min:8', 'max:20'],
                'role' => [
                    'sometimes',
                    'string',
                    Rule::in(array_column(RoleName::cases(), 'name'))
                ],
                'is_active' => ['sometimes', 'boolean'],
            ],
            "update" => [
                'full_name' => ['sometimes', 'string', 'min:3', 'max:150'],
                'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
                'password' => ['sometimes', 'nullable', 'string', 'min:8'],
                "phone" => ['sometimes', 'nullable', 'string', 'min:8', 'max:20'],
                'role' => [
                    'sometimes',
                    'string',
                    Rule::in(array_column(RoleName::cases(), 'name'))
                ],
                'is_active' => ['sometimes', 'boolean'],
            ],
            "setUserActivation" => [
                'is_active' => ["required", 'boolean']
            ],
            default => []
        };




    }
}
