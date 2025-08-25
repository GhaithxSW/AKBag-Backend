<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaginatedRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'per_page' => [
                'sometimes',
                'integer',
                'min:'.config('pagination.min_per_page'),
                'max:'.config('pagination.max_per_page'),
            ],
            'page' => 'sometimes|integer|min:1',
            'sort' => 'sometimes|string',
            'order' => 'sometimes|string|in:'.implode(',', config('pagination.allowed_sort_orders')),
        ];
    }

    public function getPerPage(): int
    {
        return $this->input('per_page', config('pagination.default_per_page'));
    }

    public function getSortColumn(): string
    {
        return $this->input('sort', config('pagination.default_sort_column'));
    }

    public function getSortOrder(): string
    {
        return $this->input('order', config('pagination.default_sort_order'));
    }
}
