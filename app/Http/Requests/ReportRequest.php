<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();

        return match ($method) {
            'index', 'show' => true,                            // all authenticated users
            'generate'      => $this->user()->canGenerateReport(), // PM or Admin Proyek
            default         => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'generate' => [
                'report_type'  => [
                    'required', 'string',
                    Rule::in(['WEEKLY', 'MONTHLY', 'PROGRESS', 'COST', 'SUMMARY']),
                ],
                'period_start' => ['required', 'date'],
                'period_end'   => ['required', 'date', 'gte:period_start'],
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'report_type.in'       => 'Report type must be one of: WEEKLY, MONTHLY, PROGRESS, COST, SUMMARY.',
            'period_end.gte'       => 'Period end must be on or after period start.',
        ];
    }
}
