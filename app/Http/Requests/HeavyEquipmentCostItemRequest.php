<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HeavyEquipmentCostItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route()->getActionMethod() === 'index') {
            return true; // semua user login boleh melihat katalog
        }

        // Setup item biaya: Finance atau Administrator Sistem
        return $this->user()->isFinance() || $this->user()->isAdministratorSistem();
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'name'           => ['required', 'string', 'max:100'],
                'default_amount' => ['nullable', 'numeric', 'min:0'],
                'is_active'      => ['sometimes', 'boolean'],
                'sort_order'     => ['sometimes', 'integer', 'min:0'],
            ],

            'update' => [
                'name'           => ['sometimes', 'string', 'max:100'],
                'default_amount' => ['nullable', 'numeric', 'min:0'],
                'is_active'      => ['sometimes', 'boolean'],
                'sort_order'     => ['sometimes', 'integer', 'min:0'],
            ],

            default => [],
        };
    }
}
