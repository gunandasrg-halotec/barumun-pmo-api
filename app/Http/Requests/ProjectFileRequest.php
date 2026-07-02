<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();

        return match ($method) {
            'index', 'show', 'download' => true,
            'store', 'destroy' => $this->user()->canManageFiles(), // PM or Admin Proyek
            default => false,
        };
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {

            'store' => [
                'file'                => ['required', 'file', 'max:20480'],  // 20 MB
                'file_category_id'    => ['required', 'uuid', 'exists:file_categories,id'],
                'file_type'           => ['required', Rule::in(['DOCUMENT', 'IMAGE'])],
                'related_entity_type' => ['nullable', Rule::in(['WBD_NODE', 'PROGRESS_ENTRY'])],
                'related_entity_id'   => ['nullable', 'uuid'],
                'caption'             => ['nullable', 'string', 'max:500'],
                'photo_date'          => ['nullable', 'date'],
                'note'                => ['nullable', 'string', 'max:2000'],
            ],

            default => [],
        };
    }

    /**
     * Business-rule validations after base rules pass:
     * - IMAGE requires caption + photo_date
     * - related_entity_type requires related_entity_id
     */
    public function withValidator($validator): void
    {
        if ($this->route()->getActionMethod() !== 'store') {
            return;
        }

        $validator->after(function ($v) {
            if ($this->input('file_type') === 'IMAGE') {
                if (empty($this->input('caption'))) {
                    $v->errors()->add('caption', 'Caption is required for IMAGE files.');
                }
                if (empty($this->input('photo_date'))) {
                    $v->errors()->add('photo_date', 'Photo date is required for IMAGE files.');
                }
            }

            if (!empty($this->input('related_entity_type')) && empty($this->input('related_entity_id'))) {
                $v->errors()->add('related_entity_id', 'related_entity_id is required when related_entity_type is set.');
            }
        });
    }
}
