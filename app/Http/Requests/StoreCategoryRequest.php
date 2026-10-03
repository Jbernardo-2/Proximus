<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends CatalogRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->string('name')->toString())]);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'ulid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = $this->route('category');
            $parentId = $this->input('parent_id');

            if (! $category instanceof Category || ! is_string($parentId)) {
                return;
            }

            $currentId = $parentId;

            while ($currentId !== null) {
                if ($currentId === $category->id) {
                    $validator->errors()->add('parent_id', 'Una categoría no puede depender de sí misma ni de una subcategoría suya.');

                    return;
                }

                $currentId = Category::query()->whereKey($currentId)->value('parent_id');
            }
        }];
    }
}
