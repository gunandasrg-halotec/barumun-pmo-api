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
            'index', 'show', 'diff' => true,                       // all roles can read
            'pending'               => $user->canApproveWbd(),     // Direksi only — bug lama: aksi ini tidak ada di match arms sama sekali (selalu 403)
            'approve', 'reject'     => $user->canApproveWbd(),     // Direksi only
            'store', 'submit'       => $user->canManageWbd(),      // PM / Admin Proyek
            'unlockRevision', 'revokeUnlock', 'finalize' => $user->canApproveWbd(), // Direksi only
            'startRevision'         => $user->canManageWbd(),      // PM / Admin Proyek
            default                 => false,
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
            'finalize' => [
                'decisions'            => ['required', 'array', 'min:1'],
                'decisions.*.code'     => ['required', 'string'],
                'decisions.*.decision' => ['required', 'string', 'in:APPROVED,REJECTED'],
                'decisions.*.reason'   => ['nullable', 'string', 'max:1000'],
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
