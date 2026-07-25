<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeavyEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route()->getActionMethod() === 'index') {
            return true; // semua user login boleh melihat master
        }

        return $this->user()->isAdministratorSistem(); // write: Admin Sistem
    }

    public function rules(): array
    {
        $equipment = $this->route('heavyEquipment');

        return match ($this->route()->getActionMethod()) {

            'store' => [
                'code'      => ['required', 'string', 'max:50', Rule::unique('heavy_equipments', 'code')],
                'type'      => ['required', 'string', 'max:100'],
                'brand'     => ['required', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],

            'update' => [
                'code'      => ['sometimes', 'string', 'max:50', Rule::unique('heavy_equipments', 'code')->ignore($equipment?->id)],
                'type'      => ['sometimes', 'string', 'max:100'],
                'brand'     => ['sometimes', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],

            default => [],
        };
    }
}
