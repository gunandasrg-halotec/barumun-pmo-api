<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
class UserProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $endpointName = $this->route()->getActionMethod();
        return match ($endpointName) {
            "updateProfile" => [
                "full_name" => ["sometimes", "string", "min:3", "max:150"],
                "phone" => [ "nullable", "string", "min:8", "max:30"],
            ],
            "changePassword" => [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'confirmed', Password::defaults()],

            ]
        };

    }
}
