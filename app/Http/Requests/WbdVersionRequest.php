<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WbdVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();
        $user   = $this->user();

        return match ($method) {
            'index', 'show'        => true,                       // all roles can read
            'approve', 'reject'    => $user->canApproveWbd(),     // Direksi only
            'store', 'submit'      => $user->canManageWbd(),      // PM / Admin Proyek
            default                => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'store'  => [
                'based_on_version_id' => ['nullable', 'uuid', 'exists:wbd_versions,id'],
            ],
            'submit' => [],
            'approve'=> [],
            'reject' => [
                'reason' => ['required', 'string', 'min:5', 'max:1000'],
            ],
            default  => [],
        };
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan penolakan wajib diisi.',
            'reason.min'      => 'Alasan penolakan minimal 5 karakter.',
        ];
    }
}
