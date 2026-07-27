<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HeavyEquipmentActivityTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route()->getActionMethod() === 'index') {
            return true;
        }

        return $this->user()->isAdministratorSistem();
    }

    public function rules(): array
    {
        $id = $this->route('activity_type')?->id;

        return match ($this->route()->getActionMethod()) {

            'store' => [
                'code'             => ['required', 'string', 'max:50', 'uppercase', Rule::unique('heavy_equipment_activity_types', 'code')],
                'name'             => ['required', 'string', 'max:200'],
                'unit'             => ['nullable', 'string', 'max:20'],
                'allow_date_range' => ['sometimes', 'boolean'],
                'sort_order'       => ['sometimes', 'integer', 'min:0'],
                'is_active'        => ['sometimes', 'boolean'],
            ],

            'update' => [
                'code'             => ['sometimes', 'string', 'max:50', 'uppercase', Rule::unique('heavy_equipment_activity_types', 'code')->ignore($id)],
                'name'             => ['sometimes', 'string', 'max:200'],
                'unit'             => ['nullable', 'string', 'max:20'],
                'allow_date_range' => ['sometimes', 'boolean'],
                'sort_order'       => ['sometimes', 'integer', 'min:0'],
                'is_active'        => ['sometimes', 'boolean'],
            ],

            default => [],
        };
    }
}
