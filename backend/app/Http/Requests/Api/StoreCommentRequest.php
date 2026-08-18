<?php

namespace App\Http\Requests\Api;

use App\Models\TicketComment;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimetypes:'.implode(',', TicketComment::ATTACHMENT_MIMES), 'max:10240'],
        ];
    }
}
