<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FileCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = FileCategory::orderBy('category_name');

        if ($request->filled('active_only') && $request->active_only) {
            $query->where('is_active', true);
        }

        return response()->json([
            'success' => true,
            'message' => 'File categories fetched',
            'data' => $query->get()->map(fn ($c) => [
                'id' => $c->id,
                'category_name' => $c->category_name,
                'description' => $c->description,
                'is_active' => $c->is_active,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->isAdministratorSistem()) {
            abort(403, 'Only Administrator Sistem can manage file categories.');
        }

        $validated = $request->validate([
            'category_name' => ['required', 'string', 'max:100', 'unique:file_categories,category_name'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category = FileCategory::create([
            'category_name' => $validated['category_name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File category created',
            'data' => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'description' => $category->description,
                'is_active' => $category->is_active,
            ],
        ], 201);
    }

    public function update(Request $request, FileCategory $fileCategory): JsonResponse
    {
        if (!$request->user()->isAdministratorSistem()) {
            abort(403, 'Only Administrator Sistem can manage file categories.');
        }

        $validated = $request->validate([
            'category_name' => [
                'sometimes', 'string', 'max:100',
                Rule::unique('file_categories', 'category_name')->ignore($fileCategory->id),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $fileCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'File category updated',
            'data' => [
                'id' => $fileCategory->id,
                'category_name' => $fileCategory->category_name,
                'description' => $fileCategory->description,
                'is_active' => $fileCategory->is_active,
            ],
        ]);
    }
}
