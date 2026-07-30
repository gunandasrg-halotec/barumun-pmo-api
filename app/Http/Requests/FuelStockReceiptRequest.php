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
            'kebun'        => ['required', 'string', 'max:100'],
            'receipt_date' => ['required', 'date'],
            // receipts dikirim sebagai JSON string saat multipart/form-data
            'receipts'     => ['required', 'string'],
            'photos'       => ['nullable', 'array'],
            'photos.*'     => ['image', 'max:10240'],
        ];
    }

    /** Decode field receipts dari JSON string ke array setelah validasi. */
    public function decodedReceipts(): array
    {
        $decoded = json_decode($this->input('receipts'), true);
        return is_array($decoded) ? $decoded : [];
    }
}
