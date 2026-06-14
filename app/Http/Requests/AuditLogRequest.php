<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
    /**
     * Audit logs are read-only and restricted to Administrator Sistem.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdministratorSistem();
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string'],
            'entity_id'   => ['sometimes', 'uuid'],
            'action_type' => ['sometimes', 'string'],
            'action_by'   => ['sometimes', 'uuid'],
            'date_from'   => ['sometimes', 'date'],
            'date_to'     => ['sometimes', 'date'],
        ];
    }
}
