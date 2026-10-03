<?php

namespace App\Http\Requests;

use App\Models\PriceTier;
use App\Models\ProductPresentation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePriceTierRequest extends CatalogRequest
{
    public function rules(): array
    {
        return [
            'min_quantity' => ['required', 'numeric', 'min:0.000001', 'decimal:0,6'],
            'max_quantity' => ['nullable', 'numeric', 'gt:min_quantity', 'decimal:0,6'],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => [
                'nullable',
                'date',
                Rule::when($this->filled('starts_at'), ['after_or_equal:starts_at']),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->boolean('is_active') || $validator->errors()->hasAny(['min_quantity', 'max_quantity', 'starts_at', 'ends_at'])) {
                return;
            }

            $presentation = $this->route('presentation');
            $tier = $this->route('priceTier');

            if (! $presentation instanceof ProductPresentation && $tier instanceof PriceTier) {
                $presentation = $tier->presentation;
            }

            if (! $presentation instanceof ProductPresentation) {
                return;
            }

            $query = PriceTier::query()
                ->whereBelongsTo($presentation, 'presentation')
                ->active()
                ->when($tier instanceof PriceTier, fn ($builder) => $builder->whereKeyNot($tier->id))
                ->where(function ($builder): void {
                    $max = $this->input('max_quantity');

                    if ($max !== null && $max !== '') {
                        $builder->where('min_quantity', '<=', $max);
                    }
                })
                ->where(function ($builder): void {
                    $builder->whereNull('max_quantity')
                        ->orWhere('max_quantity', '>=', $this->input('min_quantity'));
                });

            if ($this->filled('ends_at')) {
                $query->where(function ($builder): void {
                    $builder->whereNull('starts_at')->orWhereDate('starts_at', '<=', $this->date('ends_at'));
                });
            }

            if ($this->filled('starts_at')) {
                $query->where(function ($builder): void {
                    $builder->whereNull('ends_at')->orWhereDate('ends_at', '>=', $this->date('starts_at'));
                });
            }

            if ($query->exists()) {
                $validator->errors()->add('min_quantity', 'El rango de cantidades y vigencia se cruza con otro precio activo de esta presentación.');
            }
        }];
    }
}
