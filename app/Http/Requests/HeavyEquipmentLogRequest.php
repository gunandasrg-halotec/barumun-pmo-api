<?php

namespace App\Http\Requests;

use App\Enums\HeavyEquipmentActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Submit laporan harian dari lapangan (endpoint publik).
 * Otorisasi ditangani oleh middleware `pinAuth` (kode akses), bukan role,
 * karena request ini tidak memiliki user yang login.
 */
class HeavyEquipmentLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'heavy_equipment_id'   => ['required', 'string', Rule::exists('heavy_equipments', 'id')->where('is_active', true)],
            'log_date'             => ['required', 'date'],
            'kebun'                => ['required', 'string', 'max:100'],
            'area'                 => ['nullable', Rule::in(['TM', 'TBM'])],
            'operator'             => ['required', 'string', 'max:100'],
            'kenek'                => ['nullable', 'string', 'max:100'],
            'fuel_liters'          => ['nullable', 'numeric', 'min:0'],
            'work_morning_start'   => ['nullable', 'string', 'max:10'],
            'work_morning_end'     => ['nullable', 'string', 'max:10'],
            'work_afternoon_start' => ['nullable', 'string', 'max:10'],
            'work_afternoon_end'   => ['nullable', 'string', 'max:10'],
            'note'                 => ['nullable', 'string', 'max:2000'],

            'activities'                 => ['nullable', 'array'],
            'activities.*.activity_type' => ['required_with:activities', Rule::in(HeavyEquipmentActivityType::values())],
            'activities.*.start_date'    => ['nullable', 'date'],
            'activities.*.end_date'      => ['nullable', 'date'],
            'activities.*.start_time'    => ['nullable', 'string', 'max:10'],
            'activities.*.end_time'      => ['nullable', 'string', 'max:10'],
            'activities.*.volume'        => ['nullable', 'numeric', 'min:0'],

            'costs'                 => ['nullable', 'array'],
            'costs.*.cost_item_id'  => ['required_with:costs', 'string', Rule::exists('heavy_equipment_cost_items', 'id')],
            'costs.*.amount'        => ['required_with:costs', 'numeric', 'min:0'],

            'photos'   => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'max:8192'], // 8 MB / foto
        ];
    }
}
