<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FuelStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kebun'                  => ['required', 'string', 'max:100'],
            'receipt_date'           => ['required', 'date'],
            'receipts'               => ['required', 'array', 'min:1'],
            'receipts.*.fuel_type'   => ['required', 'in:solar,dex_lite'],
            'receipts.*.qty_20l'     => ['required', 'integer', 'min:0'],
            'receipts.*.qty_30l'     => ['required', 'integer', 'min:0'],
            'receipts.*.qty_40l'     => ['required', 'integer', 'min:0'],
        ];
    }
}
