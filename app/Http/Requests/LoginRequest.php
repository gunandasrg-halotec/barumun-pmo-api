<?php

namespace App\Http\Requests;
use App\Models\User;
use Auth;

use OpenApi\Attributes as OA;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;



class LoginRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "email" => ["required"],
            "password" => ["required"]
        ];
    }
    public function authenticate()
    {
        $user = User::where("email", "=", $this->input("email"))->first();
        if ($user) {
            if (!$user->is_active) {
                abort(
                    401,
                    "Your account has been deactivated. Please contact administrator.",
                    [
                        'WWW-Authenticate' => 'Bearer realm="jwtAuth" comment="credentials was deactivated."'
                    ]
                );
            }
        }

        $token = JWTAuth::attempt($this->only("email", "password"));
        if (!$token) {
            abort(401, "The provided credentials are incorrect.", [
                'WWW-Authenticate' => 'Bearer realm="jwtAuth" comment="invalid credentials"'
            ]);
        }
        return $token;
    }
}
