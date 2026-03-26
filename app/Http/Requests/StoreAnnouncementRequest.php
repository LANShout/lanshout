<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', 'in:announcement.published'],
            'announcement' => ['required', 'array'],
            'announcement.id' => ['required', 'integer'],
            'announcement.title' => ['required', 'string', 'max:500'],
            'announcement.priority' => ['required', 'string', 'in:silent,normal,emergency'],
            'announcement.published_at' => ['required', 'string'],
        ];
    }
}
