<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FileCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route()->getActionMethod();

        if ($method === 'index') {
            return true; // all authenticated users can list
        }

        return $this->user()->isAdministratorSistem(); // write: Admin Sistem only
    }

    public function rules(): array
    {
        $category = $this->route('fileCategory');

        return match ($this->route()->getActionMethod()) {

            'store' => [
                'category_name' => [
                    'required', 'string', 'max:100',
                    Rule::unique('file_categories', 'category_name'),
                ],
                'description'   => ['nullable', 'string', 'max:500'],
                'is_active'     => ['sometimes', 'boolean'],
            ],

            'update' => [
                'category_name' => [
                    'sometimes', 'string', 'max:100',
                    Rule::unique('file_categories', 'category_name')
                        ->ignore($category?->id),
                ],
                'description'   => ['nullable', 'string', 'max:500'],
                'is_active'     => ['sometimes', 'boolean'],
            ],

            default => [],
        };
    }
}
