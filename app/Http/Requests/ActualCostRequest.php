<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActualCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();
        $user   = $this->user();

        return match ($method) {
            'index', 'show'    => true,
            'store'            => $user->canInputCost(),   // Finance or Admin Proyek (NOT PM)
            'approve', 'reject'=> $user->canApproveCost(), // Finance only
            default            => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'progress_entry_id' => ['required', 'uuid', 'exists:progress_entries,id'],
                'amount'            => ['required', 'numeric', 'gt:0'],
                'transaction_date'  => ['required', 'date'],
                'description'       => ['nullable', 'string', 'max:2000'],
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
            'progress_entry_id.required' => 'A linked progress entry is required.',
            'progress_entry_id.exists'   => 'The selected progress entry does not exist.',
            'amount.gt'                  => 'Cost amount must be greater than 0.',
            'reason.required'            => 'Rejection reason is required.',
        ];
    }
}
