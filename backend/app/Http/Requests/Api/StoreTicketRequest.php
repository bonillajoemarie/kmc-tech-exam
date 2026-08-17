<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_categories', 'id')->where('is_active', true),
            ],
            'priority_id' => [
                'nullable',
                'integer',
                Rule::exists('ticket_priorities', 'id')->where('is_active', true),
            ],
        ];
    }
}
