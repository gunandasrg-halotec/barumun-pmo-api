<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    /**
     * Only PM, Admin Proyek, and Administrator Sistem can write.
     * All authenticated users can read (index / show).
     */
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();

        // Read actions — all authenticated users allowed
        if (in_array($method, ['index', 'show'])) {
            return true;
        }

        // Write actions — PM, Admin Proyek, Administrator Sistem
        $user = $this->user();
        return $user->isProjectManager()
            || $user->isAdminProyek()
            || $user->isAdministratorSistem();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $method = $this->route()->getActionMethod();

        return match ($method) {

            'store' => [
                'project_code' => [
                    'required', 'string', 'max:100',
                    Rule::unique('projects', 'project_code'),
                ],
                'project_name' => ['required', 'string', 'min:3', 'max:200'],
                'client_name'  => ['required', 'string', 'min:2', 'max:200'],
                'location'     => ['required', 'string', 'min:2', 'max:200'],
                'start_date'   => ['required', 'date'],
                'end_date'     => ['required', 'date', 'gte:start_date'],
                'status'       => ['sometimes', 'string', Rule::in(ProjectStatus::values())],
                'description'  => ['nullable', 'string', 'max:2000'],
            ],

            'update' => [
                'project_name' => ['sometimes', 'string', 'min:3', 'max:200'],
                'client_name'  => ['sometimes', 'string', 'min:2', 'max:200'],
                'location'     => ['sometimes', 'string', 'min:2', 'max:200'],
                'start_date'   => ['sometimes', 'date'],
                'end_date'     => ['sometimes', 'date', 'gte:start_date'],
                'status'       => ['sometimes', 'string', Rule::in(ProjectStatus::values())],
                'description'  => ['nullable', 'string', 'max:2000'],
            ],

            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'project_code.unique'    => 'Project code already exists.',
            'end_date.gte'           => 'End date must be on or after start date.',
            'status.in'              => 'Status must be one of: ' . implode(', ', ProjectStatus::values()),
        ];
    }
}
