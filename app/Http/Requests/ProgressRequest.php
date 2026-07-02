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
            'approve', 'reject'=> $user->canApproveProgress(),
            default            => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'wbd_node_id'     => ['required', 'uuid', 'exists:wbd_nodes,id'],
                'progress_date'   => ['required', 'date'],
                'progress_volume' => ['required', 'numeric', 'gt:0'],
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
            'progress_volume.gt'       => 'Progress volume must be greater than 0.',
            'reason.required'          => 'Rejection reason is required.',
        ];
    }
}
