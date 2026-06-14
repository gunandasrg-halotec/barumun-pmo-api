<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WbdNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();

        return match ($method) {
            'index'            => true,
            'store', 'update',
            'destroy'          => $this->user()->canManageWbd(),
            default            => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'parent_node_id' => ['nullable', 'uuid', 'exists:wbd_nodes,id'],
                'node_type'      => ['required', Rule::in(['GROUP', 'ITEM'])],
                'code'           => ['required', 'string', 'max:100'],
                'name'           => ['required', 'string', 'min:2', 'max:255'],
                'description'    => ['nullable', 'string', 'max:2000'],
                'unit'           => ['nullable', 'string', 'max:50'],
                'volume'         => ['nullable', 'numeric', 'min:0'],
                'rate'           => ['nullable', 'numeric', 'min:0'],
                'start_date'     => ['nullable', 'date'],
                'duration_days'  => ['nullable', 'integer', 'min:1'],
                'status'         => ['sometimes', 'string', 'max:50'],
                'sort_order'     => ['sometimes', 'integer', 'min:0'],
            ],

            'update' => [
                'name'          => ['sometimes', 'string', 'min:2', 'max:255'],
                'description'   => ['nullable', 'string', 'max:2000'],
                'unit'          => ['sometimes', 'nullable', 'string', 'max:50'],
                'volume'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'rate'          => ['sometimes', 'nullable', 'numeric', 'min:0'],
                'start_date'    => ['sometimes', 'nullable', 'date'],
                'duration_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'status'        => ['sometimes', 'string', 'max:50'],
                'sort_order'    => ['sometimes', 'integer', 'min:0'],
            ],

            default => [],
        };
    }

    /**
     * Extra validation after base rules pass.
     * Enforces: ITEM requires unit, volume, rate, start_date, duration_days.
     */
    public function withValidator($validator): void
    {
        if ($this->route()->getActionMethod() !== 'store') {
            return;
        }

        $validator->after(function ($v) {
            if ($this->input('node_type') === 'ITEM') {
                foreach (['unit', 'volume', 'rate', 'start_date', 'duration_days'] as $field) {
                    if (empty($this->input($field))) {
                        $v->errors()->add($field, "The {$field} field is required for ITEM nodes.");
                    }
                }
            }
        });
    }
}
