<?php

namespace App\Http\Requests;

use App\Enums\ProgressStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();
        $user   = $this->user();

        return match ($method) {
            'index', 'show'    => true,
            'store'            => $user->canInputProgress(),
            'approve', 'reject'=> $user->canApproveProgress() || $user->isDireksi(),
            default            => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'wbd_node_id'     => ['required', 'uuid', 'exists:wbd_nodes,id'],
                'progress_date'   => ['required', 'date'],
                // Minimal salah satu dari volume atau biaya harus diisi (>0) — bisa saja hanya
                // ada realisasi volume tanpa biaya, atau sebaliknya hanya biaya tanpa volume.
                'progress_volume' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                    $vol  = (float) ($value ?? 0);
                    $cost = (float) ($this->input('actual_cost') ?? 0);
                    if ($vol <= 0 && $cost <= 0) {
                        $fail('Realisasi volume atau realisasi biaya harus diisi (tidak boleh keduanya 0).');
                    }
                }],
                'actual_cost'     => ['nullable', 'numeric', 'min:0'],
                'note'            => ['nullable', 'string', 'max:2000'],
                'attachment'       => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
                'remaining_volume' => ['nullable', 'numeric', 'min:0'],
                'remaining_cost'   => ['nullable', 'numeric', 'min:0'],
            ],

            'reject' => [
                'reason' => ['required', 'string', 'min:5', 'max:1000'],
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'wbd_node_id.required'     => 'WBD node is required.',
            'wbd_node_id.exists'       => 'Selected WBD node does not exist.',
            'reason.required'          => 'Rejection reason is required.',
        ];
    }
}
